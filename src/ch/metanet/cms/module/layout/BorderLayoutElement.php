<?php

namespace ch\metanet\cms\module\layout;

use ch\metanet\cms\common\CmsView;
use ch\metanet\cms\controller\common\FrontendController;
use timesplinter\tsfw\db\DB;
use stdClass;

/**
 * A layout module which lets you set five other modules in five different places (north, south, west, east and center).
 * You can also set another LayoutModule in one of this five areas.
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class BorderLayoutElement extends LayoutElement
{
	public function __construct($ID, $pageID) {
		parent::__construct($ID, $pageID, 'mod_border_layout');
	}

	public function render(FrontendController $frontendController, CmsView $view) {
		$this->tplVars->offsetSet('mod_north',
			($this->settings->north_mod_IDFK !== null)?$this->elements->offsetGet($this->settings->north_mod_IDFK)->render($frontendController, $view):null
		);
		$this->tplVars->offsetSet('mod_south',
			($this->settings->south_mod_IDFK !== null)?$this->elements->offsetGet($this->settings->south_mod_IDFK)->render($frontendController, $view):null
		);
		$this->tplVars->offsetSet('mod_east',
			($this->settings->east_mod_IDFK !== null)?$this->elements->offsetGet($this->settings->east_mod_IDFK)->render($frontendController, $view):null
		);
		$this->tplVars->offsetSet('mod_center',
			($this->settings->center_mod_IDFK !== null)?$this->elements->offsetGet($this->settings->center_mod_IDFK)->render($frontendController, $view):null
		);
		$this->tplVars->offsetSet('mod_west',
			($this->settings->west_mod_IDFK !== null)?$this->elements->offsetGet($this->settings->west_mod_IDFK)->render($frontendController, $view):null
		);

		$this->tplVars->offsetSet('east_grid_size', $this->settings->east_grid_size);
		$this->tplVars->offsetSet('center_grid_size', $this->settings->center_grid_size);
		$this->tplVars->offsetSet('west_grid_size', $this->settings->west_grid_size);

		$html = $view->render($this->identifier . '.html', (array)$this->tplVars);

		return $html;
	}

	public function create(DB $db) {
		try {
			parent::create($db);

			$stmntInsert = $db->prepare("
				INSERT mod_border_layout SET
					mod_instance_IDFK = ?,
					page_IDFK = ?,
					east_grid_size = ?,
					center_grid_size = ?,
					west_grid_size = ?,
					north_mod_IDFK = ?,
					south_mod_IDFK = ?,
					east_mod_IDFK = ?,
					west_mod_IDFK = ?,
					center_mod_IDFK = ?
			");

			$db->insert($stmntInsert, array(
				$this->ID,
				$this->pageID,
				$this->settings->east_grid_size,
				$this->settings->center_grid_size,
				$this->settings->west_grid_size,
				$this->settings->north_mod_IDFK,
				$this->settings->south_mod_IDFK,
				$this->settings->east_mod_IDFK,
				$this->settings->west_mod_IDFK,
				$this->settings->center_mod_IDFK
			));
		} catch(\Exception $e) {
			return false;
		}

		return true;
	}

	public function remove(DB $db) {
		// Do all the things do cleanly remove the module (e.x. delete something in db, clean up cached files etc)
	}

	public function update(DB $db, stdClass $newSettings, $pageID)
	{
		// TODO: Implement update() method.
	}

	/**
	 * Sets module default settings if no settings in the DB exists, class this method
	 * @return mixed
	 */
	public function setDefaultSettings() {
		$this->settings = new stdClass();

		$this->settings->north_mod_IDFK = null;
		$this->settings->south_mod_IDFK = null;
		$this->settings->west_mod_IDFK = null;
		$this->settings->east_mod_IDFK = null;
		$this->settings->center_mod_IDFK = null;

		$this->settings->east_grid_size = 2;
		$this->settings->west_grid_size = 4;
		$this->settings->center_grid_size = 2;
	}

	/**
	 * @param DB $db The database object
	 * @param array $modIDs The IDs of the modules for which the settings should be loaded
	 * @param array $pageIDs The nested page IDs context
	 * @return array The settings found sorted by module IDs
	 */
	public static function getSettingsForElements(DB $db, array $modIDs, array $pageIDs) {
		//try {
			/*$pageIds = array(1);*/
			$params = array_merge($modIDs, $pageIDs, $pageIDs);

			$stmntSettings = $db->prepare("
				SELECT mod_instance_IDFK, page_IDFK, east_grid_size, center_grid_size, west_grid_size,
					north_mod_IDFK, south_mod_IDFK, east_mod_IDFK, west_mod_IDFK, center_mod_IDFK
				FROM mod_border_layout
				WHERE mod_instance_IDFK IN (" . DB::createInQuery($modIDs) . ")
				AND page_IDFK IN (" . DB::createInQuery($pageIDs) . ")
				ORDER BY FIELD(page_IDFK, " . DB::createInQuery($pageIDs) . ")
			");

			$resSettings = $db->select($stmntSettings, $params);

			$settingsArr = array();

			foreach($resSettings as $res) {
				$settingsArr[$res->mod_instance_IDFK][$res->page_IDFK] = $res;
			}

			return $settingsArr;
		/*} catch(\Exception $e) {
			return false;
		}*/
	}
}

/* EOF */