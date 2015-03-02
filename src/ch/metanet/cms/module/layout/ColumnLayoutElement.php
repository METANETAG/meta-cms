<?php

namespace ch\metanet\cms\module\layout;

use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsElementSortable;
use ch\metanet\cms\common\CmsView;
use ch\metanet\cms\controller\common\FrontendController;
use ch\timesplinter\common\StringUtils;
use ch\timesplinter\core\FrameworkLoggerFactory;
use timesplinter\tsfw\db\DB;
use stdClass;

/**
 * A layout module which lets you define n other modules and floats them to the left or the right.
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class ColumnLayoutElement extends LayoutElement implements CmsElementSortable
{
	private $logger;

	public function __construct($ID, $pageID)
	{
		parent::__construct($ID, $pageID, 'element_column_layout');

		$this->logger = FrameworkLoggerFactory::getLogger($this);
	}

	public function render(FrontendController $frontendController, CmsView $view)
	{
		if(!$this->settingsFound)
			return $this->renderEditable($frontendController, '<p>no settings found</p>');

		$columnsArr = new \ArrayObject();

		for($i = 1; $i <= $this->settings->cols; ++$i) {
			$colMods = new \ArrayObject();

			if(isset($this->settings->columns[$i]) === true) {
				foreach($this->settings->columns[$i] as $mod) {
					if(isset($this->elements[$mod]) === false)
						continue;

					/** @var CmsElement $modInstance */
					$modInstance = $this->elements[$mod];

					$colMods->append($modInstance->render($frontendController, $view));
				}
			}

			$columnsArr->offsetSet($i, $colMods);
		}

		$this->tplVars->offsetSet('columns', $columnsArr);
		$this->tplVars->offsetSet('column_count', $this->settings->cols);
		$this->tplVars->offsetSet('logged_in', $frontendController->getAuth()->isLoggedIn());

		$html = $view->render($this->identifier . '.html', (array)$this->tplVars);

		return $this->renderEditable($frontendController, $html);
	}

	public function update(DB $db, stdClass $newSettings, $pageID)
	{
		$stmntUpdate = $db->prepare("
				INSERT INTO element_column_layout SET element_instance_IDFK = ?, page_IDFK = ?, cols = ?
					ON DUPLICATE KEY UPDATE cols = ?

			");

		$db->update($stmntUpdate, array(
			// INSERT
			$this->ID, $pageID, $newSettings->cols,

			// UPDATE
			$newSettings->cols
		));
	}

	/**
	 * @param DB $db The database object
	 * @param array $modIDs The IDs of the modules for which the settings should be loaded
	 * @param array $pageIDs The nested page IDs context
	 * @return array The settings found sorted by module IDs
	 */
	public static function getSettingsForElements(DB $db, array $modIDs, array $pageIDs)
	{
		$params = array_merge($modIDs, $pageIDs, $pageIDs);

		$stmntSettings = $db->prepare("
			SELECT element_instance_IDFK, page_IDFK, cols
			FROM element_column_layout
			WHERE element_instance_IDFK IN (" . DB::createInQuery($modIDs) . ")
			AND page_IDFK IN (" . DB::createInQuery($pageIDs) . ")
			ORDER BY FIELD(page_IDFK, " . DB::createInQuery($pageIDs) . ")
		");

		$resSettings = $db->select($stmntSettings, $params);

		$stmntMods = $db->prepare("
			SELECT element_column_layout_IDFK, page_IDFK, element_instance_IDFK, col, sort
			FROM element_column_layout_module eclm
			WHERE element_column_layout_IDFK IN (" . DB::createInQuery($modIDs) . ")
			AND page_IDFK IN (" . DB::createInQuery($pageIDs) . ")
			ORDER BY FIELD(page_IDFK, " . DB::createInQuery($pageIDs) . "), col, sort
		");

		$resMods = $db->select($stmntMods, $params);

		$modColList = array();

		foreach($resMods as $res) {
			$modColList[$res->element_column_layout_IDFK][$res->col][] = $res->element_instance_IDFK;
		}

		$settingsArr = array();

		foreach($resSettings as $res) {
			$settingsArr[$res->element_instance_IDFK][$res->page_IDFK] = $res;
			$settingsArr[$res->element_instance_IDFK][$res->page_IDFK]->columns = array();

			for($i = 1; $i <= $res->cols; ++$i) {
				$settingsArr[$res->element_instance_IDFK][$res->page_IDFK]->columns[$i] = array();
			}

			if(isset($modColList[$res->element_instance_IDFK]) === false)
				continue;

			$settingsArr[$res->element_instance_IDFK][$res->page_IDFK]->columns = $modColList[$res->element_instance_IDFK];
		}

		return $settingsArr;
	}

	public function addedChildElement(DB $db, CmsElement $cmsElement, $dropzoneID, $pageID)
	{
		$col = (StringUtils::afterLast($dropzoneID, 'column-') + 1);

		$highestCountStmnt = $db->prepare("SELECT MAX(sort) highval FROM element_column_layout_module WHERE col = ?");
		$highestCount = $db->select($highestCountStmnt, array($col));

		$stmntInsert = $db->prepare("
			INSERT INTO element_column_layout_module
			SET element_column_layout_IDFK = ?, page_IDFK = ?, element_instance_IDFK = ?, col = ?, sort = ?
		");

		$db->insert($stmntInsert, array($this->ID, $pageID, $cmsElement->getID(), $col, ($highestCount[0]->highval + 1)));
	}

	public function removedChildElement(DB $db, CmsElement $cmsElement, $pageID)
	{
		$removeStmnt = $db->prepare("DELETE FROM element_column_layout_module WHERE element_instance_IDFK = ? AND element_column_layout_IDFK = ?");
		$db->delete($removeStmnt, array($cmsElement->getID(), $cmsElement->getParentElementID()));
	}

	public function reorderElements(DB $db, CmsElement $movedCmsElement, $dropzoneID, $elementOrder)
	{
		// Remove all from column
		$colNo = ((int)StringUtils::afterFirst($dropzoneID, 'column-') + 1);

		$this->logger->debug('-- Reorder module in column: ' . $colNo);
		
		$stmntRemove = $db->prepare("
			DELETE FROM element_column_layout_module WHERE col = ? AND page_IDFK = ? AND element_column_layout_IDFK = ?
		");

		$deletedElements = $db->delete($stmntRemove, array(
			$colNo, $this->pageID, $this->ID
		));
		
		$this->logger->debug('Deleted ' . $deletedElements . ' elements');

		// Remove the element itself (cause of PK crashes)
		$stmntRemoveOriginal = $db->prepare("
			DELETE FROM element_column_layout_module WHERE element_column_layout_IDFK = ? AND page_IDFK = ? AND element_instance_IDFK = ?
		");

		$deletedOriginal = $db->delete($stmntRemoveOriginal, array(
			$this->ID,
			$movedCmsElement->getPageID(),
			$movedCmsElement->getID()
		));
		
		$this->logger->debug('Deleted ' . $deletedOriginal . ' original element');

		// Add the new order
		$stmntInsert = $db->prepare("
			INSERT IGNORE INTO element_column_layout_module
				SET element_column_layout_IDFK = ?, page_IDFK = ?, col = ?, element_instance_IDFK = ?, sort = ?
		");

		$stmntUpdate = $db->prepare("UPDATE cms_element_instance SET parent_mod_IDFK = ? WHERE ID = ?");

		$this->logger->debug('Add those modules', array($elementOrder));
		
		foreach($elementOrder as $k => $e) {
			$this->logger->debug('Added module: ' . $e);

			$eParts = explode('-', $e);

			if(isset($eParts[0]) === false)
				continue;

			$db->update($stmntUpdate, array(
				$this->ID,
				$eParts[0]
			));

			$db->insert($stmntInsert, array(
				$this->ID,
				$this->pageID,
				$colNo,
				$eParts[0],
				($k+1)
			));
		}
	}
}

/* EOF */