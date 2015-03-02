<?php

namespace ch\metanet\cms\module\mod_core;

use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsElementSearchable;
use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\common\CmsView;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\controller\common\CmsController;
use ch\metanet\cms\controller\common\FrontendController;
use timesplinter\tsfw\db\DB;
use \stdClass;

/**
 * A very simple module which just prints out a given text. Nothing more nothing less than this.
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class TextElement extends CmsElementSettingsLoadable implements CmsElementSearchable {
	public function __construct($ID, $pageID) {
		parent::__construct($ID, $pageID, 'element_text');
	}

	public function render(FrontendController $frontendController, CmsView $view) {
		return $this->renderEditable($frontendController, $this->settings->text);
	}

	/**
	 * Sets module default settings if no settings in the DB exists, class this method
	 * @return mixed
	 */
	public function setDefaultSettings() {
		$this->settings = new stdClass();
		$this->settings->text = '<p>Enter your text here...</p>';
	}

	public function remove(DB $db) {
		parent::remove($db);

		// Do all the things do cleanly remove the module (e.x. delete something in db, clean up cached files etc)
		$stmntRemoveSettings = $db->prepare("DELETE FROM element_text WHERE element_instance_IDFK = ? AND page_IDFK = ?");
		$db->delete($stmntRemoveSettings, array($this->ID, $this->pageID));
	}

	public function updateInlineEdit(DB $db, stdClass $newSettings, $pageID) {
		$stmntUpdate = $db->prepare("
			REPLACE INTO element_text SET element_instance_IDFK = ?, page_IDFK = ?, text = ?
		");

		$db->update($stmntUpdate, array($this->ID, $pageID, $newSettings->text));
	}

	/**
	 * @param DB $db The database object
	 * @param array $modIDs The IDs of the modules for which the settings should be loaded
	 * @param array $pageIDs The nested page IDs context
	 * @return array The settings found sorted by module IDs
	 */
	public static function getSettingsForElements(DB $db, array $modIDs, array $pageIDs) {
		$params = array_merge($modIDs, $pageIDs, $pageIDs);

		$stmntSettings = $db->prepare("
			SELECT element_instance_IDFK, page_IDFK, text
			FROM element_text
			WHERE element_instance_IDFK IN (" . DB::createInQuery($modIDs) . ")
			AND page_IDFK IN (" . DB::createInQuery($pageIDs) . ")
			ORDER BY FIELD(page_IDFK, " . DB::createInQuery($pageIDs) . ")
		");

		$resSettings = $db->select($stmntSettings, $params);

		$settingsArr = array();

		foreach($resSettings as $res) {
			$settingsArr[$res->element_instance_IDFK][$res->page_IDFK] = $res;
		}

		return $settingsArr;
	}

	/**
	 * {@inheritdoc}
	 */
	public function renderSearchIndexContent(DB $db, $language)
	{
		return str_replace(
			array("\r\n", "\n", "\t"),
			array(' ', ' ', null),
			strip_tags($this->settings->text)
		);
	}

	public function update(DB $db, \stdClass $newSettings, $pageID) {
		$stmntUpdate = $db->prepare("
			INSERT INTO " . $this->identifier . "
				SET element_instance_IDFK = ?, page_IDFK = ?, text = ?
			ON DUPLICATE KEY
				UPDATE text = ?
		");

		$db->update($stmntUpdate, array(
			// INSERT
			$this->ID,
			$pageID,
			$newSettings->text,

			// UPDATE
			$newSettings->text
		));
	}
}

/* EOF */