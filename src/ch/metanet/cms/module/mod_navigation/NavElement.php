<?php

namespace ch\metanet\cms\module\mod_navigation;

use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\common\CmsView;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\controller\common\FrontendController;
use ch\metanet\cms\module\mod_navigation\events\SubNavLoadedEvent;
use ch\metanet\cms\module\mod_navigation\model\NavigationModel;
use timesplinter\tsfw\db\DB;
use \stdClass;

/**
 * The navigation module shows a cms navigation with the chosen display settings.
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class NavElement extends CmsElementSettingsLoadable
{
	protected $activeRoutes;
	protected $modes;

	public function __construct($ID, $pageID)
	{
		parent::__construct($ID, $pageID, 'element_nav');

		$this->activeRoutes = array();
		$this->modes = array(
			1 => 'Navigation list',
			2 => 'Breadcrumb'
		);
	}

	public function render(FrontendController $frontendController, CmsView $view)
	{
		if($this->settingsFound === false)
			return $this->renderEditable($frontendController, '<p>Please set up this navigation</p>');

		$navStruct = $this->generateNavigation(
			$frontendController,
			$this->settings->navigation_IDFK
		);

		$cuttedNavStruct = ($this->settings->level_from !== null)?$this->getLevelFromArray($navStruct, $this->settings->level_from):$navStruct;

		if($this->settings->mode == 1) {
			$html = $this->getNavigationAsHtml(
				$frontendController,
				$cuttedNavStruct,
				$this->settings->level_to,
				($frontendController->getCmsRoute() !== null)?$frontendController->getCmsRoute()->getID():null,
				($this->settings->show_active_stages_only != 0)
			);
		} else {
			$breadcrumbEntries = $this->getNavigationAsBreadcrumb($frontendController, $cuttedNavStruct, $this->settings->level_to, ($frontendController->getCmsRoute() !== null)?$frontendController->getCmsRoute()->getID():null);

			$html = '<div' . (($this->settings->class_name !== null)?' class="' . $this->settings->class_name . '"':null) . '>'; //<a href="/">Home</a>

			if($breadcrumbEntries !== null) {
				$lastBreadcrumbEntry = array_pop($breadcrumbEntries);

				foreach($breadcrumbEntries as $bce)
					$html .= '<a href="' . $bce['link'] . '">' . $bce['title'] . '</a> / ';

				$html .= '<strong>' . $lastBreadcrumbEntry['title'] . '</strong>';
			}

			$html .= '</div>';
		}

		return $this->renderEditable($frontendController, $html);
	}

	public function generateNavigation(FrontendController $fec, $navigationID)
	{
		$lang = $fec->getLocaleHandler()->getLanguage();

		$navigationModel = new NavigationModel($fec->getDB());
		$resNav = $navigationModel->getAllEntriesByNavID($navigationID, $lang);

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
	private function generateNavigationReal($fec, $navEntries, $activeRouteID, $parentNavID = null)
	{
		$navArr = array();

		foreach($navEntries as $n) {
			if($n->parent_navigation_entry_IDFK != $parentNavID)
				continue;

			if($activeRouteID !== null && $n->route_IDFK == $activeRouteID)
				$this->getActiveRoutes($fec->getDB(), $n->ID, $n->navigation_IDFK, $fec->getLocaleHandler()->getLanguage());

			// Call HOOK HERE!
			$n->subNav = array();

			$subNavLoadedEvent = new SubNavLoadedEvent($n, in_array($n->route_IDFK, $this->activeRoutes), $this->activeRoutes, $this->settings);

			$fec->getEventDispatcher()->dispatch($this->identifier . '.beforeSubNavLoaded', $subNavLoadedEvent);

			$n->subNav += $this->generateNavigationReal($fec, $navEntries, $activeRouteID, $n->ID);

			$fec->getEventDispatcher()->dispatch($this->identifier . '.afterSubNavLoaded', $subNavLoadedEvent);

			$n->active = (in_array($n->route_IDFK, $this->activeRoutes));

			$navArr[] = $n;
		}

		return $navArr;
	}

	/**
	 * Sets module default settings if no settings in the DB exists, class this method
	 * @return mixed
	 */
	public function setDefaultSettings()
	{
		$this->settings = new stdClass();
		$this->settings->navigation_IDFK = null;
		$this->settings->level_from = null;
		$this->settings->level_to = null;
		$this->settings->mode = 0;
		$this->settings->class_name = null;
		$this->settings->show_active_stages_only = 0;
	}

	public function remove(DB $db)
	{
		parent::remove($db);

		// Do all the things do cleanly remove the module (e.x. delete something in db, clean up cached files etc)
		$stmntRemoveSettings = $db->prepare("DELETE FROM element_nav WHERE element_instance_IDFK = ? AND page_IDFK = ?");
		$db->delete($stmntRemoveSettings, array($this->ID, $this->pageID));
	}


	public function update(DB $db, stdClass $newSettings, $pageID) {
		$stmntUpdate = $db->prepare("
			REPLACE INTO element_nav
			SET element_instance_IDFK = ?, page_IDFK = ?, navigation_IDFK = ?, level_from = ?, level_to = ?, mode = ?, class_name = ?, show_active_stages_only = ?
		");

		$db->update($stmntUpdate, array(
			$this->ID,
			$pageID,
			$newSettings->navigation_IDFK,
			(strlen($newSettings->level_from) > 0 && is_numeric($newSettings->level_from))?$newSettings->level_from:null,
			(strlen($newSettings->level_to) > 0 && is_numeric($newSettings->level_to))?$newSettings->level_to:null,
			$newSettings->mode,
			(strlen($newSettings->class_name) > 0)?$newSettings->class_name:null,
			isset($newSettings->show_active_stages_only)?1:0
		));
	}

	public function getActiveRoutes(DB $db, $navEntryID, $navID, $lang) {
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

	/**
	 * @param FrontendController $fec
	 * @param $navStruct
	 * @param $levelTo
	 * @param $currentRouteID
	 * @param int $levelCounter
	 * @param null $parentID
	 * @param bool $showActiveStagesOnly Show only navigation stages which have at least one active navigation entry
	 * @return null|string The navigation as HTML structure
	 */
	private function getNavigationAsHtml(FrontendController $fec, $navStruct, $levelTo, $currentRouteID, $showActiveStagesOnly, $levelCounter = 1, $parentID = null)
	{
		if(!is_array($navStruct) || ($navCount = count($navStruct)) === 0)
			return null;

		$isOneActive = false;

		$listClasses = array('nav-entries-' . $navCount);

		if($this->settings->class_name !== null && strlen($this->settings->class_name) > 0)
			array_unshift($listClasses, $this->settings->class_name);

		$className = ' class="' .  implode(' ', $listClasses)  . '"';

		$navHtml = "\n" . str_repeat("\t", ($levelCounter - 1)) . '<ul' . (($levelCounter === 1)?$className .' role="navigation"':null) . ">\n";

		$navCounter = 1;

		foreach($navStruct as $nav) {
			$activeClass = null;

			$lastElmntClass = ($navCounter === $navCount);
			$firstElmntClass = ($navCounter === 1);
			$classAttr = null;

			if($lastElmntClass && $firstElmntClass) {
				$classAttr = ' class="last nav-entry-' . $navCounter . ' first"';
			} elseif($firstElmntClass) {
				$classAttr = ' class="nav-entry-' . $navCounter . ' first"';
			} elseif($lastElmntClass) {
				$classAttr = ' class="nav-entry-' . $navCounter . ' last"';
			} else {
				$classAttr = ' class="nav-entry-' . $navCounter . '"';
			}

			if(isset($nav->active) === true && $nav->active === true/* && $nav->hidden == 0*/) {
				$activeClass = ' class="active"';
				$isOneActive = true;
			}

			// Prepare pattern with defaults
			// @TODO default param replacement needs enhancement
			$pattern = ($nav->route_IDFK !== null)?$nav->pattern:$nav->external_link;

			if(isset($nav->default_params) === false || $nav->default_params === null) {
				$pattern = preg_replace('@\(.+?\)\??@', null, $pattern);
			}

			if(isset($nav->hidden) === false || $nav->hidden == 0) {
				$navHtml .= str_repeat("\t", $levelCounter) . '<li' . $classAttr . '><a href="' . $pattern . '"' . $activeClass . '>' . $nav->title . '</a>';

				if(isset($nav->subNav) === true && $nav->subNav !== null && ($levelTo === null || $levelTo > $levelCounter)) {
					$renderSubNav = false;

					foreach($nav->subNav as $ne) {
						if(isset($ne->hidden) === true && $ne->hidden == 1)
							continue;

						$renderSubNav = true;
						break;
					}

					$navHtml .= ($renderSubNav)?$this->getNavigationAsHtml($fec, $nav->subNav, $levelTo, $currentRouteID, $showActiveStagesOnly, ($levelCounter + 1), $nav->route_IDFK):null;
				}

				$navHtml .= "</li>\n";

				++$navCounter;
			}
		}

		$navHtml .= str_repeat("\t", ($levelCounter - 1)) . "</ul>\n" . str_repeat("\t", ($levelCounter - 1));

		return (!$showActiveStagesOnly || $isOneActive || $currentRouteID == $parentID || $levelCounter == 1)?$navHtml:null;
	}

	private function getNavigationAsBreadcrumb(FrontendController $fec, $navStruct, $levelTo, $levelCounter = 1)
	{
		if(!is_array($navStruct))
			return null;

		foreach($navStruct as $nav) {
			if(isset($nav->active) === false || $nav->active === false)
				continue;

			$pattern = ($nav->route_IDFK !== null)?$nav->pattern:$nav->external_link;

			// @TODO enhance and combine with the other statement
			if(isset($nav->default_params) === false || $nav->default_params === null) {
				$pattern = preg_replace('@\(.+?\)\??@', null, $pattern);
			}

			$navHtml = array(array('link' => $pattern, 'title' =>  $nav->title));

			if(isset($nav->subNav) === true && $nav->subNav !== null && ($levelTo === null || $levelTo > $levelCounter)) {
				$subBreadcrumb = $this->getNavigationAsBreadcrumb($fec, $nav->subNav, $levelTo, ($levelCounter + 1), $nav->route_IDFK);

				if($subBreadcrumb !== null)
					$navHtml = array_merge( $navHtml, $subBreadcrumb );
			}

			return $navHtml;
		}

		return null;
	}

	private function getLevelFromArray($array, $levelFrom, $levelCount = 1)
	{
		$arrayCount = count($array);

		for($i = 0; $i < $arrayCount; ++$i) {
			if(!in_array($array[$i]->route_IDFK, $this->activeRoutes)/* && $levelFrom < 1*/)
				continue;

			if($levelFrom == $levelCount + 1) {
				return $array[$i]->subNav;
			} elseif($levelFrom == $levelCount) {
				return $array;
			} else {
				if($array[$i]->subNav !== null)
					return $this->getLevelFromArray($array[$i]->subNav, $levelFrom, ($levelCount + 1));
			}
		}

		return $array;
	}

	protected function getNavigations(BackendController $backendController)
	{
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
	 * @return array The settings found sorted by module IDs and page IDs
	 */
	public static function getSettingsForElements(DB $db, array $modIDs, array $pageIDs)
	{
		$params = array_merge($modIDs, $pageIDs, $pageIDs);

		$stmntSettings = $db->prepare("
			SELECT element_instance_IDFK, page_IDFK, navigation_IDFK, level_from, level_to, mode, class_name, show_active_stages_only
			FROM element_nav
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