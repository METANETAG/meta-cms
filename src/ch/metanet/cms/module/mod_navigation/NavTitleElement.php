<?php

namespace ch\metanet\cms\module\mod_navigation;

use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\common\CmsView;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\model\PageModel;
use ch\metanet\cms\controller\common\FrontendController;
use timesplinter\tsfw\common\ArrayUtils;
use timesplinter\tsfw\db\DB;
use \stdClass;

/**
 * Prints out a title of a navigation on a specific level
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class NavTitleElement extends CmsElementSettingsLoadable {
	private $activeRoutes;
	protected $modes;

	public function __construct($ID, $pageID) {
		parent::__construct($ID, $pageID, 'element_nav_title');

		$this->activeRoutes = array();
	}

	public function render(FrontendController $frontendController, CmsView $view) {
		if(!$this->settingsFound)
			return $this->renderEditable($frontendController, '<p>Please set up this title</p>');

		$navStruct = $this->generateNavigation(
			$frontendController,
			$this->settings->navigation_IDFK
		);

		$beadcrumbs = $this->getNavigationAsBreadcrumb($navStruct, ($frontendController->getCmsRoute() !== null)?$frontendController->getCmsRoute()->getID():null);

		if($beadcrumbs !== null && isset($beadcrumbs[($this->settings->level-1)]) === true) {
			$html = '<a href="' . $beadcrumbs[($this->settings->level-1)]['link'] . '">' . $beadcrumbs[($this->settings->level-1)]['title'] . '</a>';
		} else {
			$html = '(undefined)';
		}

		return $this->renderEditable($frontendController, $html);
	}

	public function generateNavigation(FrontendController $fec, $navigationID) {
		$stmntNav = $fec->getDB()->prepare("
			SELECT ne.ID, ne.title, ne.route_IDFK, r.pattern, ne.external_link, nhe.navigation_IDFK, nhe.parent_navigation_entry_IDFK, nhe.sort
			FROM navigation_has_entry nhe
			LEFT JOIN navigation_entry ne ON ne.ID = nhe.navigation_entry_IDFK

			LEFT JOIN route r ON r.ID = ne.route_IDFK

			WHERE nhe.navigation_IDFK = ?
			AND ne.language_codeFK = ?
			ORDER BY nhe.parent_navigation_entry_IDFK, nhe.sort ASC
		");

		$lang = $fec->getLocaleHandler()->getLanguage();
		$params = array($navigationID, $lang);

		$resNav = $fec->getDB()->select($stmntNav, $params);

		if(count($resNav) === 0)
			return null;

		return $this->generateNavigationReal($fec, $resNav, ($fec->getCmsRoute() !== null)?$fec->getCmsRoute()->getID():null);
	}

	/**
	 * @param FrontendController $fec
	 * @param $navEntries
	 * @param $activeRouteID
	 * @param null $parentNavID
	 * @return array
	 */
	private function generateNavigationReal($fec, $navEntries, $activeRouteID, $parentNavID = null) {
		$navArr = array();

		foreach($navEntries as $n) {
			if($n->parent_navigation_entry_IDFK != $parentNavID)
				continue;

			if($n->route_IDFK == $activeRouteID)
				$this->getActiveRoutes($fec->getDB(), $n->ID, $n->navigation_IDFK, $fec->getLocaleHandler()->getLanguage());

			$n->subNav = $this->generateNavigationReal($fec, $navEntries, $activeRouteID, $n->ID);

			$navArr[] = $n;
		}

		return $navArr;
	}

	/**
	 * Sets module default settings if no settings in the DB exists, class this method
	 * @return mixed
	 */
	public function setDefaultSettings() {
		$this->settings = new stdClass();
		$this->settings->navigation_IDFK = null;
		$this->settings->level = null;
	}

	public function remove(DB $db) {
		parent::remove($db);

		// Do all the things do cleanly remove the module (e.x. delete something in db, clean up cached files etc)
		$stmntRemoveSettings = $db->prepare("DELETE FROM element_nav_title WHERE element_instance_IDFK = ? AND page_IDFK = ?");
		$db->delete($stmntRemoveSettings, array($this->ID, $this->pageID));
	}


	public function update(DB $db, stdClass $newSettings, $pageID) {
		$stmntUpdate = $db->prepare("
			REPLACE INTO element_nav_title
			SET element_instance_IDFK = ?, page_IDFK = ?, navigation_IDFK = ?, level = ?
		");
		$db->update($stmntUpdate, array(
			$this->ID,
			$pageID,
			$newSettings->navigation_IDFK,
			$newSettings->level
		));
	}

	private function getActiveRoutes(DB $db, $navEntryID, $navID, $lang) {
		$stmntActiveRoute = $db->prepare("
			SELECT ne.route_IDFK, nhe.parent_navigation_entry_IDFK, nhe.navigation_IDFK, ne.ID
			FROM navigation_entry ne
			LEFT JOIN navigation_has_entry nhe ON nhe.navigation_entry_IDFK = ne.ID
			WHERE nhe.navigation_IDFK = ?
			AND ne.language_codeFK = ?
		");

		$resActiveRoute = $db->select($stmntActiveRoute, array($navID, $lang));

		if(count($resActiveRoute) <= 0)
			return;

		$this->generateActiveRoutes($resActiveRoute, $navEntryID);
		array_reverse($this->activeRoutes);
	}

	private function generateActiveRoutes($routes, $navEntryID) {
		foreach($routes as $r) {
			if($r->ID != $navEntryID)
				continue;

			$this->activeRoutes[] = $r->route_IDFK;

			if($r->parent_navigation_entry_IDFK !== null)
				$this->generateActiveRoutes($routes, $r->parent_navigation_entry_IDFK);

			break;
		}
	}

	private function getNavigationAsBreadcrumb($navStruct, $levelCounter = 1) {
		if(!is_array($navStruct))
			return null;


		foreach($navStruct as $nav) {
			if(!in_array($nav->route_IDFK, $this->activeRoutes))
				continue;

			$navHtml = array(array('link' => $nav->pattern, 'title' =>  $nav->title));

			if($nav->subNav !== null) {
				$subBreadcrumb = $this->getNavigationAsBreadcrumb($nav->subNav, ($levelCounter + 1));

				if($subBreadcrumb !== null)
					$navHtml = array_merge( $navHtml, $subBreadcrumb );
			}

			return $navHtml;
		}

		return null;
	}

	protected function getNavigations(BackendController $backendController) {
		$stmntNavs = $backendController->getDB()->prepare("
			SELECT ID, name FROM navigation ORDER BY name
		");

		$resNavs = $backendController->getDB()->select($stmntNavs, array(
			$this->ID,
			$backendController->getLocaleHandler()->getLanguage()
		));

		$catsArr = array();

		foreach($resNavs as $c) {
			$catsArr[$c->ID] = $c->name;
		}

		return $catsArr;
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
			SELECT element_instance_IDFK, page_IDFK, navigation_IDFK, level
			FROM element_nav_title
			WHERE element_instance_IDFK IN (" . DB::createInQuery($modIDs) . ")
			AND page_IDFK IN (" . DB::createInQuery($pageIDs) . ")
			ORDER BY FIELD (page_IDFK, " . DB::createInQuery($pageIDs) . ")
		");

		$resSettings = $db->select($stmntSettings, $params);

		$settingsArr = array();

		foreach($resSettings as $res) {
			$settingsArr[$res->element_instance_IDFK][$res->page_IDFK] = $res;
		}

		return $settingsArr;
	}
}

/* EOF */