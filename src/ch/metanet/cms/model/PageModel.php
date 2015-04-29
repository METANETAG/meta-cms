<?php

namespace ch\metanet\cms\model;

use ch\metanet\cms\common\CmsAuthHandlerDB;
use ch\metanet\cms\common\CmsPage;
use ch\metanet\cms\common\CmsRoute;
use ch\metanet\cms\common\CmsTemplate;
use timesplinter\tsfw\db\DB;
use ch\timesplinter\core\Route;
use timesplinter\tsfw\db\DBMySQL;

/**
 * @author Pascal Muenst
 * @copyright Copyright (c) 2013, METANET AG
 */
class PageModel extends Model
{
	private $stmntStaticRoute;
	private $stmntPage;
	private $stmntDynRoute;

	public function __construct(DB $db)
	{
		parent::__construct($db);

		$this->prepareStmnts();
	}
	
	/**
	 * @param int $pageID
	 * @param CmsPage|null $basePage
	 * @return CmsPage|null
	 */
	public function getPageByID($pageID, $basePage = null)
	{
		$resPage = $this->db->select($this->stmntPage, array($pageID));

		if(count($resPage) <= 0)
			return null;

		$pageData = $resPage[0];

		$page = new CmsPage();

		$page->setID($pageData->ID);
		$page->setTitle($pageData->title);
		$page->setDescription($pageData->description);
		$page->setLanguage($pageData->language_codeFK);
		$page->setModifierID($pageData->modifier_ID);
		$page->setModifierName($pageData->modifier_name);
		$page->setLastModified($pageData->last_modified);
		$page->setCreatorID($pageData->creator_ID);
		$page->setCreatorName($pageData->creator_name);
		$page->setCreated($pageData->created);
		$page->setInheritRights($pageData->inhert_rights);
		$page->setRole($pageData->role);
		$page->setErrorCode($pageData->error_code);

		// Set parent page
		$page->setParentPage(($pageData->base_page_IDFK !== null)?$this->getPageByID($pageData->base_page_IDFK, $basePage):null);

		// Set child pages
		//$page->setChildPages($this->getChildPagesByPageId($pageData->ID));

		$pageIdsRecursive = self::getPageIdsRecursive($page);

		// Set rights
		$page->setRights($this->getRightsByPageID($pageIdsRecursive, $pageData->inhert_rights));
		$page->setCacheMode($this->getCacheModeByPageID($pageIdsRecursive));

		$layoutID = ($page->hasParentPage() === false)?$pageData->layout_IDFK:$pageData->base_layout_IDFK;

		$page->setLayoutID($layoutID);

		return $page;
	}

	public function getChildPagesByPageId($parentPageID, $childPageIds = array()) {
		$stmntChildPages = $this->db->prepare("
			SELECT ID FROM page WHERE base_page_IDFK = ?
		");

		$resChildPages = $this->db->select($stmntChildPages, array($parentPageID));

		foreach($resChildPages as $cp){
			$childPageIds = $this->getChildPagesByPageId($cp->ID, $childPageIds);

			$childPageIds[] = $cp->ID;
		}

		return $childPageIds;
	}

	public static function getPageIdsRecursive($cmsPage, $pageIds = array())
	{
		/** @var CmsPage $cmsPage */
		$pageIds[] = $cmsPage->getID();

		if($cmsPage->hasParentPage()) {
			$pageIds = self::getPageIdsRecursive($cmsPage->getParentPage(), $pageIds);
		}

		return $pageIds;
	}

	public function getRightsByPageID(array $pageIDs, $inhertRights)
	{
		$stmntRights = $this->db->prepare("
			SELECT r.ID, r.groupname, r.groupkey, rights, start_date, end_date, page_IDFK inherted_page
			FROM page_has_rightgroup phr
			LEFT JOIN rightgroup r ON r.ID = phr.rightgroup_IDFK
			WHERE page_IDFK = ?
			ORDER BY r.groupname
		");

		$resRights = $this->db->select($stmntRights, array($pageIDs[0]));

		if($inhertRights == 0)
			return $resRights;

		$stmntRightsRecursive = $this->db->prepare("
			SELECT r.ID, r.groupname, r.groupkey, rights, start_date, end_date, page_IDFK inherted_page
			FROM page_has_rightgroup phr
			LEFT JOIN rightgroup r ON r.ID = phr.rightgroup_IDFK
			WHERE page_IDFK IN(" . DB::createInQuery($pageIDs) . ")
			ORDER BY FIELD (page_IDFK, " . DB::createInQuery($pageIDs) . "), r.groupname
		");

		$resRightsRecursive = $this->db->select($stmntRightsRecursive, array_merge($pageIDs, $pageIDs));

		return $resRightsRecursive;
	}

	public function getCacheModeByPageID(array $pageIDs)
	{
		$stmntCacheModes = $this->db->prepare("
			SELECT ID, cache_mode
			FROM page
			WHERE ID IN (" . DB::createInQuery($pageIDs) . ")
		");

		$resCacheModes = $this->db->select($stmntCacheModes, $pageIDs);

		$currentCacheMode = 9;

		foreach($resCacheModes as $cm) {
			if($currentCacheMode <= $cm->cache_mode)
				continue;

			$currentCacheMode = $cm->cache_mode;
		}

		return $currentCacheMode;
	}

	public function getRightEntryByPageID($rightgroupID, $pageID)
	{
		$stmntRightgroupEntry = $this->db->prepare("
			SELECT rights, start_date, end_date
			FROM page_has_rightgroup phr
			WHERE page_IDFK = ? AND rightgroup_IDFK = ?
		");

		$resRightgroupEntry = $this->db->select($stmntRightgroupEntry, array(
			$pageID,
			$rightgroupID
		));

		if(count($resRightgroupEntry) <= 0)
			return null;

		return $resRightgroupEntry[0];
	}

	public function getBasePagesForPage($pageID = null)
	{
		$cond = '';
		$params = array();

		if($pageID !== null) {
			$cond .= "ID != ?";
			$params[] = $pageID;
		}

		$stmntBasePages = $this->db->prepare("
			SELECT ID, title, language_codeFK, base_page_IDFK, role
			FROM page
			" . (strlen($cond) > 0 ? 'WHERE ' . $cond : null) . "
			ORDER BY language_codeFK, title
		");

		return $this->db->select($stmntBasePages, $params);
	}

	public function getPagesWithDependencies($userID, $parentPageID = null)
	{
		$pages = array();

		$stmntParams = array();
		$whereCond = "base_page_IDFK IS NULL";

		if($parentPageID !== null) {
			$whereCond = "base_page_IDFK  = ?";
			$stmntParams[] = $parentPageID;
		}

		$stmntBasePages = $this->db->prepare("
			SELECT ID, title, description, language_codeFK, base_page_IDFK
			FROM page
			WHERE " . $whereCond . "
		");

		$resBasePages = $this->db->select($stmntBasePages, $stmntParams);

		foreach($resBasePages as $basePage) {
			$cmsPage = $this->getPageByID($basePage->ID);

			if(!$this->hasUserReadAccess($cmsPage, $userID))
				continue;

			$pages[] = array(
				'page_data' => $basePage,
				'sub_pages' => $this->getPagesWithDependencies($userID, $basePage->ID)
			);
		}

		return $pages;
	}

	public function hasUserWriteAccess(CmsPage $page, CmsAuthHandlerDB $auth)
	{
		return $this->hasUserAccess($page, $auth, 'write');
	}

	public function hasUserReadAccess(CmsPage $page, CmsAuthHandlerDB $auth)
	{
		return $this->hasUserAccess($page, $auth, 'read');
	}

	private function hasUserAccess(CmsPage $page, CmsAuthHandlerDB $auth, $mode)
	{
		if($auth->hasRootAccess())
			return true;

		$pageRightsCompressed = array();
		
		if(count($page->getRights()) <= 0)
			return false;

		foreach(array_reverse($page->getRights()) as $pr) {
			$pageRightsCompressed[$pr->groupkey] = $pr->rights;
		}
		
		foreach($pageRightsCompressed as $id => $right) {
			if($auth->hasRightGroup($id) === false)
				continue;

			if(($right == 3) || ($mode === 'read' && $right == 2) || ($mode === 'write' && $right == 1))
				return true;
		}

		return false;
	}

	public function isUserRoot($userID)
	{
		$stmntRoot = $this->db->prepare("
			SELECT ID
			FROM login_has_rightgroup lhr
			LEFT JOIN rightgroup rg ON rg.ID = lhr.rightgroupIDFK
			WHERE rg.root = 1 AND lhr.loginIDFK = ?
		");

		$resRoot = $this->db->select($stmntRoot, array($userID));

		if(count($resRoot) > 0)
			return true;

		return false;
	}

	/**
	 * @param string $uri
	 *
	 * @return CmsRoute|null
	 */
	public function getRouteByURI($uri)
	{
		$resStaticRoute = $this->db->select($this->stmntStaticRoute, array($uri));

		if(count($resStaticRoute) > 0)
			return $this->createRoute($resStaticRoute[0]);

		$resDynRoute = $this->db->select($this->stmntDynRoute, array($uri, $uri));

		if(count($resDynRoute) > 0)
			return $this->createRoute($resDynRoute[0]);

		return null;
	}

	public function getAllPages($role = null)
	{
		$condStr = null;
		$params = array();
		
		if($role !== null) {
			$condStr  = "WHERE role = ?";
			$params[] = $role;
		}
		
		$stmntAllPages = $this->db->prepare("
			SELECT ID, title, language_codeFK, created, creator_IDFK, last_modified, modifier_IDFK, base_page_IDFK
			FROM page
			" . $condStr . "
			ORDER BY language_codeFK, title
		");
		
		return $this->db->select($stmntAllPages, $params);
	}

	public function getPageByUniqueID($uniqueID)
	{
		$stmntPage = $this->db->prepare("SELECT ID FROM page WHERE uniqid = ?");

		$resPage = $this->db->select($stmntPage, array($uniqueID));

		if(count($resPage) <= 0)
			return null;

		return $this->getPageByID($resPage[0]->ID);
	}

	public function deletePageByID($pageID)
	{
		$stmntDeletePage = $this->db->prepare("
			DELETE FROM page WHERE ID = ?
		");

		$this->db->delete($stmntDeletePage, array($pageID));
	}

	public function getPagePath($pageID)
	{
		$res = array_reverse($this->getPagePathRecursive($pageID), true);

		array_pop($res);

		return $res;
	}

	public function generatePageTreeOpts($pages = null, $pageRole = null)
	{
		return $this->generatePageTreeOptsRecursive(($pages !== null)?$pages:$this->getAllPages(), $pageRole);
	}

	private function generatePageTreeOptsRecursive($pages, $pageRole, $pageID = null, $stage = 0)
	{
		$options = array(0 => '- no base page -');

		foreach($pages as $p) {
			if($p->base_page_IDFK != $pageID)
				continue;

			if($pageRole === null || $p->role == $pageRole) {
				$hirStr = '';

				for($i = 1; $i <= $stage; ++$i) {
					$hirStr .= '|' . ($i == $stage ? '&mdash;' : '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');
				}

				$options[$p->ID] = $hirStr . ' ' . $p->title;
			}

			$options += $this->generatePageTreeOptsRecursive($pages, $pageRole, $p->ID, ($stage + 1));
		}

		return $options;
	}

	private function getPagePathRecursive($pageID)
	{
		$resArr = array();

		$stmnt = $this->db->prepare("
			SELECT ID, title, base_page_IDFK FROM page WHERE ID = ?
		");

		$res = $this->db->select($stmnt, array($pageID));

		if(count($res) > 0) {
			$resArr[$res[0]->ID] = $res[0]->title;

			if($res[0]->base_page_IDFK !== null)
				$resArr += $this->getPagePathRecursive($res[0]->base_page_IDFK);
		}

		return $resArr;
	}

	private function getRouteByID($routeID)
	{
		$stmntRoute = $this->db->prepare("
			SELECT ID, pattern, page_IDFK, robots, external_source, redirect_route_IDFK, mod_IDFK, regex, ssl_required, ssl_forbidden
			FROM route
			WHERE ID = ?
		");

		$resRoute = $this->db->select($stmntRoute, array($routeID));

		if(count($resRoute) <= 0)
			return null;

		return $this->createRoute($resRoute[0]);
	}

	/**
	 * @param \stdClass $res
	 * @return CmsRoute
	 */
	protected function createRoute(\stdClass $res)
	{
		$redirectRoute = ($res->redirect_route_IDFK === null)?null:$this->getRouteByID($res->redirect_route_IDFK);

		return new CmsRoute(
			$res->ID,
			$res->pattern,
			$res->regex,
			$res->robots,
			$redirectRoute,
			$res->external_source,
			$res->page_IDFK,
			$res->mod_IDFK,
			$res->ssl_required,
			$res->ssl_forbidden
		);
	}

	protected function prepareStmnts()
	{
		$this->stmntStaticRoute = $this->db->prepare("
			SELECT ID, pattern, page_IDFK, robots, external_source, redirect_route_IDFK, mod_IDFK, regex, ssl_required, ssl_forbidden
			FROM route
			WHERE regex = 0 AND mod_IDFK IS NULL AND pattern = ?
		");

		$this->stmntDynRoute = $this->db->prepare("
			SELECT
				r.ID,
				r.pattern,
				r.page_IDFK,
				r.robots,
				r.external_source,
				r.redirect_route_IDFK,
				r.mod_IDFK,
				r.regex,
				r.ssl_required,
				r.ssl_forbidden
			FROM (SELECT ID, pattern, page_IDFK, robots, external_source, redirect_route_IDFK, mod_IDFK, regex, ssl_required, ssl_forbidden FROM route WHERE regex = 1 OR mod_IDFK IS NOT NULL) r
			WHERE (mod_IDFK IS NULL AND ? REGEXP CONCAT('^', pattern, '$')) OR (mod_IDFK IS NOT NULL AND ? REGEXP CONCAT('^', pattern, '(/.+)?$'))
		");
		
		$this->stmntPage = $this->db->prepare("
			SELECT p1.ID, p1.title, p1.description, p1.base_page_IDFK, p1.language_codeFK, p1.layout_IDFK, p1.inhert_rights, p1.role, p1.error_code, p2.layout_IDFK base_layout_IDFK, p1.last_modified, p1.created, lm.username modifier_name, lc.username creator_name, lc.ID creator_ID, lm.ID modifier_ID, p1.cache_mode
			FROM page p1
			LEFT JOIN page p2 ON p2.ID = p1.base_page_IDFK
			LEFT JOIN login lm ON lm.ID = p1.modifier_IDFK
			LEFT JOIN login lc ON lc.ID = p1.creator_IDFK
			WHERE p1.ID = ?
		");
	}
}

/* EOF */