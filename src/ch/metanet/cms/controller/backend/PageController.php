<?php

namespace ch\metanet\cms\controller\backend;

use ch\metanet\cms\common\CmsElementSearchable;
use ch\metanet\cms\common\CmsElementSortable;
use ch\metanet\cms\common\CMSException;
use ch\metanet\cms\common\CmsPage;
use ch\metanet\cms\common\CmsTemplateEngine;
use ch\metanet\cms\common\RevisionControl;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\controller\common\FrontendController;
use ch\metanet\cms\model\CoreModel;
use ch\metanet\cms\model\ElementModel;
use ch\metanet\cms\model\ModuleModel;
use ch\metanet\cms\model\PageModel;
use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\model\RightGroupModel;
use ch\metanet\cms\module\layout\LayoutElement;
use ch\metanet\cms\module\mod_core\events\PageEvent;
use ch\metanet\cms\tablerenderer\BooleanColumnDecorator;
use ch\metanet\cms\tablerenderer\CallbackColumnDecorator;
use ch\metanet\cms\tablerenderer\Column;
use ch\metanet\cms\tablerenderer\DateColumnDecorator;
use ch\metanet\cms\tablerenderer\LinkColumnDecorator;
use ch\metanet\cms\tablerenderer\RewriteColumnDecorator;
use ch\metanet\cms\tablerenderer\TableRenderer;
use ch\timesplinter\core\Core;
use ch\timesplinter\core\FrameworkLoggerFactory;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\HttpResponse;
use ch\timesplinter\core\RequestHandler;
use ch\timesplinter\core\Route;
use ch\timesplinter\core\RouteUtils;
use timesplinter\tsfw\db\DBException;
use ch\timesplinter\formhelper\FormHelper;
use timesplinter\tsfw\common\StringUtils;
use ch\metanet\cms\common\CmsView;
use ch\metanet\cms\common\CmsUtils;
use timesplinter\tsfw\template\DirectoryTemplateCache;
use \DateTime;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class PageController extends BackendController
{
	private $logger;
	protected $pageModel;
	/** @var $formHelper FormHelper */
	protected $formHelper;

	private $moduleView;

	public function __construct(Core $core, HttpRequest $httpRequest, Route $route)
	{
		parent::__construct($core, $httpRequest, $route);

		$this->pageModel = new PageModel($this->db);

		$this->logger = FrameworkLoggerFactory::getLogger($this);
		$this->markHtmlIdAsActive('pages');

		$cacheDir = $this->core->getSiteCacheDir() . 'templates' . DIRECTORY_SEPARATOR;
		$templateBaseDir = $this->core->getSiteRoot() . 'templates' . DIRECTORY_SEPARATOR;
		$tplCache = new DirectoryTemplateCache($cacheDir, $templateBaseDir);

		$this->moduleView = new CmsView(
			new CmsTemplateEngine($tplCache, 'tst'),
			$templateBaseDir . $this->currentDomain->template . DIRECTORY_SEPARATOR . 'elements' . DIRECTORY_SEPARATOR
		);
	}

	/**
	 * Shows all the pages and their dependencies
	 * @return HttpResponse
	 */
	public function getPagesOverview()
	{
		if($this->httpRequest->getVar('delete') !== null)
			$this->deletePage();

		$pageQueryStr = "
			SELECT
				p.ID, p.title, p.description, bp.ID base_page_ID, bp.title base_page_title, l.name lang_name, lc.username creator_name, p.created,
				lm.username last_modifier, IF(p.last_modified IS NULL, 'never', p.last_modified) last_modified, p.uniqid,p.inhert_rights
			FROM page p
			LEFT JOIN login lc ON lc.ID = p.creator_IDFK
			LEFT JOIN login lm ON lm.ID = p.modifier_IDFK
			LEFT JOIN page bp ON bp.ID = p.base_page_IDFK
			LEFT JOIN language l ON l.code = p.language_codeFK
			ORDER BY p.language_codeFK, p.title
		";

		$columnTitle = new Column('p.title', 'Title', array(new RewriteColumnDecorator('<a href="/backend/page/{ID}">{title}</a>')), true);
		$columnTitle->setFilter();

		$tableRenderer = new TableRenderer('pages', $this->db, $pageQueryStr);
		$tableRenderer->setColumns(array(
			new Column('ID', '#', array(), true, 'p.ID'),
			$columnTitle,
			new Column('lang_name', 'Language', array(), true),
			/*new Column('base_page_ID', 'Base page', array(new RewriteColumnDecorator('<a href="#">{base_page_title} (#{base_page_ID})</a>')), true),*/
			new Column(null, 'Page path', array(new CallbackColumnDecorator(array($this,'getPagePathTableRenderer'))), true, 'bp.title'),
			new Column('inhert_rights', 'Inhert rights', array(new BooleanColumnDecorator()), true, 'p.inhert_rights'),
			new Column('created', 'Created', array(new DateColumnDecorator($this->core->getLocaleHandler()->getDateTimeFormat())), true, 'p.created'),
			new Column('creator_name', 'Creator', array(new RewriteColumnDecorator('<a href="#">{creator_name}</a>')), true, 'lc.username'),
			new Column('last_modified', 'Last modified', array(new DateColumnDecorator($this->core->getLocaleHandler()->getDateTimeFormat())), true, 'p.last_modified'),
			new Column('last_modifier', 'Last modifieder', array(new RewriteColumnDecorator('<a href="#">{last_modifier}</a>')), true, 'lm.username')
		));
		$tableRenderer->setOptions(array('preview' => '/preview/page/{uniqid}', 'delete' => '?delete={ID}'));

		$tplVars = array(
			'siteTitle' => 'Pages',
			'pages' => $tableRenderer->display(),
			'pages_tree' => $this->getPagesHierarchically()
		);

		return $this->generatePageFromTemplate('backend-pages-overview', $tplVars);
	}

	public function getPagePathTableRenderer($value, $record, $selector, $tableRenderer) {
		$htmlRes = array();

		foreach($this->pageModel->getPagePath($record->ID) as $id => $title)
			$htmlRes[] = '<a href="/backend/page/' . $id . '">' . $title . '</a>';

		return implode(' > ', $htmlRes);
	}

	public function getPageDetails() {
		$pageID = $this->route->getParam(0);

		$pageData = $this->pageModel->getPageByID($pageID);

		$moduleModel = new ElementModel($this->db);
		//$modules = $moduleModel->getModulesByPage($pageData);
		$modules = $moduleModel->getElementTree($pageData);

		$rights = array();

		foreach($pageData->getRights() as $r) {
			$rightEntry = new \stdClass();

			$rightEntry->ID = $r->ID;
			$rightEntry->groupname = $r->groupname;
			$rightEntry->rights = CmsUtils::getRightsAsString($r->rights);

			$rightEntry->inherted_page = ($r->inherted_page == $pageID)?null:'<a href="/backend/page/' . $r->inherted_page . '">Page #' . $r->inherted_page . '</a>';

			$dtStart = new DateTime($r->start_date);
			$dtFormat = $this->core->getLocaleHandler()->getDateTimeFormat();

			$rightEntry->duration = ($r->end_date === null)?'since ' . $dtStart->format($dtFormat):$dtStart->format($dtFormat) . ' - ' . $r->end_date;
			$rights[] = $rightEntry;
		}

		$sqlQueryStr = "SELECT ID, pattern
			FROM route
			WHERE page_IDFK = ?
		";

		$tableRenderer = new TableRenderer('route', $this->db, $sqlQueryStr);
		$tableRenderer->setColumns(array(
			new Column('ID', 'ID'),
			new Column('pattern', 'Path', array(new LinkColumnDecorator()))
		));
		$tableRenderer->setOptions(array(
			'edit' => '/backend/route/{ID}/edit',
			'delete' => '/backend/route/{ID}/delete'
		));

		$tplVars = array(
			'siteTitle' => 'Page "' . $pageData->getTitle() . '"',
			'page' => $pageData,
			'rights' => $rights,
			'modules' => $this->generateModuleList($modules, $pageData->getID()),
			'routes' => $tableRenderer->display(array($pageID))
		);

		return $this->generatePageFromTemplate('backend-page-details', $tplVars);
	}

	/*public function getPageEdit()
	{
		$this->abortIfUserHasNotRights('CMS_PAGES_EDIT');

		$pageID = $this->route->getParam(0);

		$pageData = $this->pageModel->getPageByID($pageID);

		$coreModel = new CoreModel($this->db);

		$basePagesOpts = array(0 => '- no base page -');

		foreach($this->pageModel->getBasePagesForPage($this->route->getParam(0)) as $p) {
			$basePagesOpts[$p->ID] = $p->language_codeFK . ', ' . $p->title;
		}
		
		$basePagesOpts = $this->pageModel->generatePageTreeOpts($this->pageModel->getBasePagesForPage($this->route->getParam(0)), CmsPage::ROLE_TEMPLATE);
		
		$tplVars = array(
			'siteTitle' => ($pageData !== null)?'Edit general page settings "' . $pageData->getTitle() . '"':'Create new page',
			'page' => $pageData,
			'form_status' => ($this->formHelper !== null && $this->formHelper->hasErrors())?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()):null,
			'opts_language' => $coreModel->getLanguages(),
			'opts_base_page' => $basePagesOpts,

			'form_title' => ($pageData !== null)?$pageData->getTitle():null,
			'form_language' => ($pageData !== null)?$pageData->getLanguage():null,
			'form_base_page' => ($pageData !== null && $pageData->getParentPage() !== null)?$pageData->getParentPage()->getID():null,
			'form_description' => ($pageData !== null)?$pageData->getDescription():null,
			'form_inhert_rights' => ($pageData !== null)?$pageData->getInheritRights():1,

			'page_rights' => ($this->httpRequest->getParam(0) !== null)?$tableRenderer->display(array($this->httpRequest->getParam(0))):null
		);

		if($this->formHelper !== null && $this->formHelper->sent()) {
			$tplVars['form_title'] = $this->formHelper->getFieldValue('title');
			$tplVars['form_language'] = $this->formHelper->getFieldValue('language');
			$tplVars['form_description'] = $this->formHelper->getFieldValue('description');
			$tplVars['form_inhert_rights'] = $this->formHelper->getFieldValue('inhert_rights');
		}


		return $this->generatePageFromTemplate('backend-page-edit', $tplVars);
	}

	public function processPageEdit() {
		$this->abortIfUserHasNotRights('CMS_PAGES_EDIT');

		$coreModel = new CoreModel($this->db);

		$basePagesOpts = array(0 => '- no base page -');

		foreach($this->pageModel->getBasePagesForPage($this->route->getParam(0)) as $p) {
			$basePagesOpts[$p->ID] = $p->language_codeFK . ', ' . $p->title;
		}

		$this->formHelper = new FormHelper(FormHelper::METHOD_POST);
		$this->formHelper->addField('title', null, FormHelper::TYPE_STRING, true, array(
			'missingError' => 'Please insert a title for this page'
		));
		$this->formHelper->addField('language', null, FormHelper::TYPE_OPTION, true, array(
			'missingError' => 'Please select a language this page',
			'invalidError' => 'Please select a valid language for this page',
			'options' => $coreModel->getLanguages()
		));
		$this->formHelper->addField('base_page', null, FormHelper::TYPE_OPTION, false, array(
			'invalidError' => 'Please select a valid base page for this page',
			'options' => $basePagesOpts
		));
		$this->formHelper->addField('description', null, FormHelper::TYPE_STRING, false);
		$this->formHelper->addField('inhert_rights', null, FormHelper::TYPE_CHECKBOX);

		if(!$this->formHelper->sent() || !$this->formHelper->validate())
			return $this->getPageEdit();

		// save settings
		$layoutIDFK = null;

		if($this->formHelper->getFieldValue('base_page') !== null) {
			$stmntLayout = $this->db->prepare("SELECT layout_IDFK FROM page WHERE ID = ?");
			$resLayout = $this->db->select($stmntLayout, array(
				$this->formHelper->getFieldValue('base_page')
			));

			if(count($resLayout) > 0)
				$layoutIDFK = $resLayout[0]->layout_IDFK;
		}

		$pageID = $this->route->getParam(0);

		try {
			if($pageID !== null) {
				$stmntUpdate = $this->db->prepare("
					UPDATE page SET title = ?, language_codeFK = ?, description = ?, base_page_IDFK = ?, layout_IDFK = ?, inhert_rights = ?, modifier_IDFK = ?, last_modified = NOW() WHERE ID = ?
				");

				$this->db->update($stmntUpdate, array(
					$this->formHelper->getFieldValue('title'),
					$this->formHelper->getFieldValue('language'),
					$this->formHelper->getFieldValue('description'),
					($this->formHelper->getFieldValue('base_page') != 0)?$this->formHelper->getFieldValue('base_page'):null,
					$layoutIDFK,
					$this->formHelper->getFieldValue('inhert_rights'),
					$this->auth->getUserID(),
					$this->httpRequest->getParam(0)
				));
			} else {
				$stmntUpdate = $this->db->prepare("
					INSERT INTO page SET title = ?, language_codeFK = ?, description = ?, base_page_IDFK = ?, layout_IDFK = ?, inhert_rights = ?, creator_IDFK = ?, created = NOW(), uniqid = ?
				");

				$pageID = $this->db->insert($stmntUpdate, array(
					$this->formHelper->getFieldValue('title'),
					$this->formHelper->getFieldValue('language'),
					$this->formHelper->getFieldValue('description'),
					($this->formHelper->getFieldValue('base_page') != 0)?$this->formHelper->getFieldValue('base_page'):null,
					$layoutIDFK,
					$this->formHelper->getFieldValue('inhert_rights'),
					$this->auth->getUserID(),
					uniqid()
				));
			}
		} catch(\Exception $e ) {
			$this->formHelper->addError(null, $e->getMessage());
			return $this->getPageEdit();
		}

		RequestHandler::redirect('/backend/page/' . $pageID);
	}

	public function deletePage() {
		$this->abortIfUserHasNotRights('CMS_PAGES_DELETE');

		$this->pageModel->deletePageByID($this->httpRequest->getVar('delete'));
	}*/

	public function duplicatePage() {

		$pageID = $this->httpRequest->getParam(0);

		$this->pageModel->duplicatePage($pageID);

		$this->core->getRequestHandler()->redirect('/backend/pages');
	}

	public function reorderModuleAjax()
	{
		list($parentType, $parentElementID, $parentElementPageID) = explode('-', $this->core->getHttpRequest()->getVar('parent_element'));

		if(isset($parentElementID) === false || isset($parentElementPageID) === false)
			return new HttpResponse(500, 'Sorry, no parent element submitted');

		$elementModel = new ElementModel($this->db);

		$pageInstance = $this->pageModel->getPageByID($parentElementPageID);

		$elementTree = $elementModel->getElementTree($pageInstance);

		$elementInstance = $elementModel->findElementIDInTree($elementTree, $parentElementID);

		if(($elementInstance instanceof CmsElementSortable) === false)
			return new HttpResponse(500, 'Sorry, this parent element is not sortable!');

		list($movedType, $movedElementID, $movedElementPageID) = explode('-', $this->core->getHttpRequest()->getVar('moved_element'));

		if(isset($movedElementID) === false || isset($movedElementPageID) === false)
			return new HttpResponse(500, 'Sorry, no moved element submitted');

		$movedElementInstance = $elementModel->findElementIDInTree($elementTree, $movedElementID);

		try {
			$this->db->beginTransaction($elementInstance->getIdentifier() . '.' . $elementInstance->getID() . '-' . $elementInstance->getPageID() . '.' . date('YmdHis') . '.reorder');

			/** @var CmsElementSortable $elementInstance */
			$elementInstance->reorderElements($this->db, $movedElementInstance, $this->core->getHttpRequest()->getVar('dropzone'), $this->core->getHttpRequest()->getVar('mod'));

			$this->updatePage($this->pageModel->getPageByID($parentElementPageID));

			$this->db->commit();
		} catch(\Exception $e) {
			$this->logger->error('Could not reorder elements', $e);

			$this->db->rollBack();

			return new HttpResponse(500, 'Could not reorder elements: ' . $e->getMessage());
		}

		return new HttpResponse(200, 'Keep em! module: ' . print_r($elementInstance, true));
	}

	/**
	 * Creates a new module and returns the render of them (for ajax call only)
	 * @return HttpResponse
	 */
	public function createNewModuleAjax() {
		$dropZoneID = $this->core->getHttpRequest()->getVar('dropzone');
		$modType = StringUtils::afterLast($this->core->getHttpRequest()->getVar('mod_type'), '-');
		$referrerPath = StringUtils::beforeFirst($this->httpRequest->getVar('referrer', 'strip_tags'), '?');

		$elementModel = new ElementModel($this->db);

		try {
			$revDate = date('YmdHis');

			if(isset($_POST['parent_module'])) {
				list($parentElementType, $parentElementID, $parentElementPageID) = explode('-',  $this->core->getHttpRequest()->getVar('parent_module'));

				$cmsPage = $this->pageModel->getPageByID($parentElementPageID);

				$pageElements = $elementModel->getElementTree($cmsPage);
				$elementInstance = $elementModel->findElementIDInTree($pageElements, $parentElementID);

				$newModInstance = $elementModel->createElement($modType, $elementInstance, $parentElementPageID, $this->auth->getUserID());

				$this->db->beginTransaction($newModInstance->getIdentifier() . '.' . $newModInstance->getID() . '-' . $newModInstance->getPageID() . '.' . $revDate . '.create');

				/** @var HttpRequest $httpRequestFrontend */
				$httpRequestFrontend = clone $this->httpRequest;
				$httpRequestFrontend->setPath($referrerPath);
				$httpRequestFrontend->setRequestMethod('GET');

				$matchedRoutes = RouteUtils::matchRoutesAgainstPath($this->core->getSettings()->core->routes, $httpRequestFrontend);
				$matchedRoute = $matchedRoutes[$httpRequestFrontend->getRequestMethod()];

				$frontendController = new FrontendController($this->core, $httpRequestFrontend, $matchedRoute);

				// Check if you use the site in preview mode or real
				if($matchedRoute->id == 'cms-site-preview') {
					preg_match($matchedRoute->pattern, $referrerPath, $res);

					$frontendController->getRoute()->setParams(array(0 => $res[1]));

					$frontendController->deliverPreviewCMSPage();
				} else {
					$frontendController->deliverCMSPage();
				}
				
				if($this->isAllowedElement($dropZoneID, $elementInstance, $newModInstance) === false)
					throw new CMSException('This module type is not allowed in dropzone with ID ' . $dropZoneID);

				$elementInstance->addedChildElement($this->db, $newModInstance, $dropZoneID, $parentElementPageID);

				$this->updatePage($cmsPage);

				$pageElements = $elementModel->getElementTree($cmsPage);
				$elementInstance = $elementModel->findElementIDInTree($pageElements, $parentElementID);

				$html = $elementInstance->render($frontendController, $this->moduleView);
			} else {
				// Basemodule for page
				$dropzoneParts = explode('-', $dropZoneID);
				$pageID = $dropzoneParts[1];

				$newModInstance = $elementModel->createElement($modType, null, $pageID, $this->auth->getUserID());

				$this->db->beginTransaction($newModInstance->getIdentifier() . '.' . $newModInstance->getID() . '-' . $newModInstance->getPageID() . '.' . date('YmdHis') . '.create');

				$httpRequestFrontend = clone $this->httpRequest;

				$httpRequestFrontend->setPath($referrerPath);
				$httpRequestFrontend->setRequestMethod('GET');

				$frontendController = new FrontendController($this->core, $httpRequestFrontend, $this->route);
				$frontendController->deliverCMSPage();

				$pageUpdateStmnt = $this->db->prepare("UPDATE page SET layout_IDFK = ? WHERE ID = ?");
				$this->db->update($pageUpdateStmnt, array($newModInstance->getID(), $pageID));

				$this->updatePage($this->pageModel->getPageByID($pageID));

				$html = $newModInstance->render($frontendController, $this->moduleView);
			}

			$this->updateElementRevision($newModInstance, $revDate);

			$this->db->commit();
		} catch(DBException $e) {
			$this->db->rollBack();
			$this->logger->error('Could not create module: ' . $e->getQueryString(), $e);

			return new HttpResponse(500, $e->getMessage(), array(
				'Content-Type' => 'text/html; charset=utf-8',
				'Content-Language' => $this->getLocaleHandler()->getLanguage()
			));
		} catch(\Exception $e) {
			//$this->db->rollBack();
			$this->logger->error('Could not create module', $e);

			return new HttpResponse(500, $e->getMessage(), array(
				'Content-Type' => 'text/html; charset=utf-8',
				'Content-Language' => $this->getLocaleHandler()->getLanguage()
			));
		}

		return new HttpResponse(200, $html, array(
			'Content-Type' => 'text/html; charset=utf-8',
			'Content-Language' => $this->getLocaleHandler()->getLanguage()
		));
	}

	private function isAllowedElement($dropzoneID, CmsElement $parentElement, CmsElement $newElement) {
		$dropzoneRestrictions = $parentElement->getDropzone($dropzoneID);

		if($dropzoneRestrictions === null)
			return true;

		$countWhitelist = count($dropzoneRestrictions['whitelist']);
		$countBlacklist = count($dropzoneRestrictions['blacklist']);

		if($countWhitelist === 0 && $countBlacklist === 0)
			return true;

		if($countWhitelist > 0 && $countBlacklist > 0)
			throw new CMSException('You can not specify a black and a white list at the same time for the same dropzone (' . $dropzoneID . ')');

		if($countWhitelist > 0)
			return in_array(get_class($newElement), $dropzoneRestrictions['whitelist']);

		if($countBlacklist > 0)
			return !in_array(get_class($newElement), $dropzoneRestrictions['blacklist']);

		return true;
	}

	public function deleteModuleAjax() {
		$module = explode('-',  $_POST['module']);
		$referrerPath = StringUtils::beforeFirst($this->httpRequest->getVar('referrer', 'strip_tags'), '?');
		$html = null;


		/** @var HttpRequest $httpRequestFrontend */
		$httpRequestFrontend = clone $this->httpRequest;
		$httpRequestFrontend->setPath($referrerPath);
		$httpRequestFrontend->setRequestMethod('GET');

		$matchedRoutes = RouteUtils::matchRoutesAgainstPath($this->core->getSettings()->core->routes, $httpRequestFrontend);
		$matchedRoute = $matchedRoutes[$httpRequestFrontend->getRequestMethod()];

		$frontendController = new FrontendController($this->core, $httpRequestFrontend, $matchedRoute);

		// Check if you use the site in preview mode or real
		if($matchedRoute->id == 'cms-site-preview') {
			preg_match($matchedRoute->pattern, $referrerPath, $res);

			$frontendController->getRoute()->setParams(array(0 => $res[1]));

			$frontendController->deliverPreviewCMSPage();
		} else {
			$frontendController->deliverCMSPage();
		}

		$pageID = $module[2];
		$elementToDeleteID = $module[1];

		$cmsPage = $this->pageModel->getPageByID($pageID);

		$frontendController->setCmsPage($cmsPage);

//var_dump($cmsPage->getTitle()); exit;
		$elementModel = new ElementModel($this->db);
		$pageElements = $elementModel->getElementTree($cmsPage);

		$elementToDeleteInstance = $elementModel->findElementIDInTree($pageElements, $elementToDeleteID);

		if($elementToDeleteInstance === null)
			throw new CMSException('Could not find module to delete: #' . $elementToDeleteID);

		$parentElement = $elementToDeleteInstance->getParentModule();

		try {
			// Delete element
			$this->db->beginTransaction($elementToDeleteInstance->getIdentifier() . '.' . $elementToDeleteInstance->getID() . '-' . $elementToDeleteInstance->getPageID() . '.' . date('YmdHis') . '.delete');

			$elementToDeleteInstance->remove($this->db);
			
			if($parentElement instanceof LayoutElement)
				$parentElement->removedChildElement($this->db, $elementToDeleteInstance, $pageID);

			$this->updatePage($cmsPage);

			// Reload page elements
			$pageElements = $elementModel->getElementTree($cmsPage);
			$parentElement = $elementModel->findElementIDInTree($pageElements, $parentElement->getID());

			// Render new parent element
			$html = $parentElement->render($frontendController, $this->moduleView);

			$this->db->commit();
		} catch(\Exception $e) {
			$this->db->rollBack();
			$this->logger->error('Could not delete module', $e);

			return new HttpResponse(500, 'Could not delete module: ' . $e->getMessage(), array(
				'Content-Type' => 'text/html; charset=utf-8',
				'Content-Language' => $this->getLocaleHandler()->getLanguage()
			));
		}

		return new HttpResponse(200, $html, array(
			'Content-Type' => 'text/html; charset=utf-8',
			'Content-Language' => $this->getLocaleHandler()->getLanguage()
		));
	}

	private function toggleElementAjax($elementID, $pageID, $hide) {

		try {
			if($hide === true) {
				$stmntHideElement = $this->db->prepare("
					INSERT IGNORE INTO cms_element_instance_hidden
						SET element_instance_IDFK = ?, page_IDFK = ?, login_IDFK = ?
				");

					$this->db->insert($stmntHideElement, array(
						$elementID, $pageID, $this->auth->getUserID()
					));
				} else {
					$stmntHideElement = $this->db->prepare("
						DELETE FROM cms_element_instance_hidden
							WHERE element_instance_IDFK = ? AND page_IDFK = ?
					");

					$this->db->delete($stmntHideElement, array(
						$elementID, $pageID
					));
				}
		} catch(\Exception $e) {
			$msg = (($hide)?'Could not hide element':'Could not reveal element') . ': ' . $e->getMessage();

			return new HttpResponse(500, $msg, array(
				'Content-Type' => 'text/html; charset=utf-8',
				'Content-Language' => $this->getLocaleHandler()->getLanguage()
			));
		}

		$cmsPage = $this->pageModel->getPageByID($pageID);
		
		$this->updatePage($cmsPage);

		$moduleModel = new ModuleModel($this->db);
		$elementInstance = $moduleModel->getElementInstanceByID($elementID, $cmsPage);

		$referrerPath = StringUtils::beforeFirst($this->httpRequest->getVar('referrer', 'strip_tags'), '?');

		$httpRequestFrontend = clone $this->httpRequest;
		$httpRequestFrontend->setPath($referrerPath);
		$httpRequestFrontend->setRequestMethod('GET');
		$frontendController = new FrontendController($this->core, $httpRequestFrontend, $this->route);
		$frontendController->deliverCMSPage();

		$newElementHtml = $elementInstance->render($frontendController, $this->moduleView);

		return new HttpResponse(200, $newElementHtml);
	}

	public function hideElementAjax() {
		$mod = explode('-', $_POST['module']);
		$elementID = $mod[1];
		$pageID = $mod[2];

		return $this->toggleElementAjax($elementID, $pageID, true);
	}

	public function revealElementAjax() {
		$mod = explode('-', $_POST['module']);
		$elementID = $mod[1];
		$pageID = $mod[2];

		return $this->toggleElementAjax($elementID, $pageID, false);
	}

	public function updateModuleAjax() {
		list($elementType, $elementID, $elementPageID) = explode('-', $this->core->getHttpRequest()->getVar('module'));

		if(($updateMethod = $this->core->getHttpRequest()->getVar('method')) === null)
			$updateMethod = 'update';

		$cmsPage = $this->pageModel->getPageByID($elementPageID);

		$moduleModel = new ModuleModel($this->db);
		$modInstance = $moduleModel->getElementInstanceByID($elementID, $cmsPage);

		try {
			$revDate = date('YmdHis');
			$this->db->beginTransaction($modInstance->getIdentifier() . '.' . $modInstance->getID() . '-' . $modInstance->getPageID() . '.' . $revDate . '.update');

			if($this->core->getHttpRequest()->getVar('delete_settings') != 1) {
				if(method_exists($modInstance, $updateMethod) === false)
					return new HttpResponse(500, 'Update method \'' . $updateMethod . '\' does not exists');

				$modInstance->$updateMethod($this->db, (object)$_POST, $cmsPage->getID());
			} else {
				$modInstance->deleteSettingsSelf($this->db, $cmsPage->getID());
			}

			$this->updateElementRevision($modInstance, $revDate);
			$this->updatePage($cmsPage);

			$this->db->commit();
		} catch(\Exception $e) {
			$this->db->rollBack();
			$this->logger->error('Could not update element', $e);
			return new HttpResponse(500, 'Could not update element: ' . $e->getMessage());
		}

		if($modInstance instanceof CmsElementSettingsLoadable) {
			//$settings = $modInstance->getSettingsForModules($this->db, array($mod[1]), array($mod[2]));
			/** @var CmsElement $modInstance */
			$moduleModel->reloadSettings($modInstance, $cmsPage);
		}

		$referrerPath = StringUtils::beforeFirst($this->httpRequest->getVar('referrer', 'strip_tags'), '?');

		$httpRequestFrontend = clone $this->httpRequest;
		$httpRequestFrontend->setPath($referrerPath);
		$httpRequestFrontend->setRequestMethod('GET');
		$frontendController = new FrontendController($this->core, $httpRequestFrontend, $this->route);

		$frontendController->deliverCMSPage();

		return new HttpResponse(200, $modInstance->render($frontendController, $this->moduleView));
	}

	private function updateElementRevision(CmsElement $cmsElement, $revision) {
		$stmntSetRev = $this->db->prepare("
			UPDATE cms_element_instance
			SET revision = ?
			WHERE ID = ? AND page_IDFK = ?
		");

		$this->db->update($stmntSetRev, array(
			$revision,
			$cmsElement->getID(),
			$cmsElement->getPageID()
		));
	}

	public function restoreElementAjax() {
		$mod = explode('-', $this->httpRequest->getVar('module', 'strip_tags'));

		$revisionFile = $this->httpRequest->getVar('revision', 'strip_tags');

		$cmsPage = $this->pageModel->getPageByID($mod[2]);

		$moduleModel = new ModuleModel($this->db);
		$modInstance = $moduleModel->getElementInstanceByID($mod[1], $cmsPage);

		try {
			$this->db->setListenersMute(true);
			$this->db->beginTransaction();

			$revisionControl = new RevisionControl($this->db);
			$revisionControl->restoreFromFile($revisionFile);

			$fileNameParts = explode('.', StringUtils::afterLast($revisionFile, '/'));

			$this->updateElementRevision($modInstance, $fileNameParts[2]);

			$this->db->commit();
			$this->db->setListenersMute(false);
		} catch(\Exception $e) {
			$this->db->setListenersMute(false);
			$this->db->rollBack();

			$this->logger->error('Could not restore element ' . $e->getMessage());

			return new HttpResponse(500, 'Could not restore element: ' . $e->getMessage());
		}

		// RENDER ELEMENT AGAIN, SEND BACK
		if($modInstance instanceof CmsElementSettingsLoadable) {
			/** @var CmsElement $modInstance */
			$moduleModel->reloadSettings($modInstance, $cmsPage);
		}

		$referrerPath = StringUtils::beforeFirst($this->httpRequest->getVar('referrer', 'strip_tags'), '?');

		$httpRequestFrontend = clone $this->httpRequest;
		$httpRequestFrontend->setPath($referrerPath);
		$httpRequestFrontend->setRequestMethod('GET');
		$frontendController = new FrontendController($this->core, $httpRequestFrontend, $this->route);
		$frontendController->deliverCMSPage();

		// @TODO render and replace parent module of this one
		$newModuleHtml = $modInstance->render($frontendController, $this->moduleView);

		return new HttpResponse(200, $newModuleHtml);
	}

	protected function updatePage(CmsPage $cmsPage)
	{
		$stmntUpdatePage = $this->db->prepare("UPDATE page SET last_modified = NOW(), modifier_IDFK = ? WHERE ID = ?");
		
		$this->db->update($stmntUpdatePage, array(
			$this->auth->getUserID(),
			$cmsPage->getID()
		));

		$this->eventDispatcher->dispatch(
			'mod_core.pageModified', 
			new PageEvent($cmsPage)
		);
	}

	protected function generatePageList($pages) {
		$htmlList = '<ul>';

		foreach($pages as $p) {
			$pID = $p['page_data']->ID;
			$htmlList .= '<li><a href="/backend/page/' . $pID . '">' . $p['page_data']->title . ' (#' . $pID . ', ' . $p['page_data']->language_codeFK. ')</a> <a href="?duplicate=' . $pID . '">edit</a> <a href="/backend/page/' . $pID . '/duplicate">duplicate</a> <a href="?delete=' . $pID . '">delete</a>';

			if(count($p['sub_pages']) > 0)
				$htmlList .= $this->generatePageList($p['sub_pages']);

			$htmlList .= '</li>';
		}

		return $htmlList . '</ul>';
	}

	/*private function generateModuleList($modules, $pageID) {
		$htmlList = '<ul>';

		foreach($modules as $m) {
			$inheritedFrom = ($m->getPageID() !== $pageID)?', inherited from page #' . $m->getPageID():null;

			$htmlList .= '<li data="icon: \'/images/icon-' . $m->getIdentifier() . '.png\'"><a href="/backend/module-instance/' . $m->getID() . '" title="' . $this->getSettingsAsStr($m) . '">' . $m->getIdentifier() . ' <em>(#' . $m->getID() . $inheritedFrom . ')</em> <a href="" class="delete">delete</a></a> ';

			if($m instanceof LayoutElement && $m->hasModules())
				$htmlList .= $this->generateModuleList($m->getElements(), $pageID);

			$htmlList .= '</li>';
		}

		return $htmlList . '</ul>';
	}*/

	private function getPagesHierarchically($pageID = null) {
		$htmlList = '<ul>';

		$pagesStmnt = $this->db->prepare("
			SELECT ID, title, language_codeFK lang FROM page WHERE " . (($pageID === null)?"base_page_IDFK IS NULL":"base_page_IDFK = ?") . " ORDER BY title
		");

		$params = ($pageID === null)?array():array($pageID);

		$pages = $this->db->select($pagesStmnt, $params);

		foreach($pages as $p) {
			$htmlList .= '<li><a href="/backend/page/' . $p->ID . '">' . $p->title . ' <em>(#' . $p->ID . ', ' . $p->lang . ')</em></a>';

			$htmlList .= $this->getPagesHierarchically($p->ID);

			$htmlList .= '</li>';
		}

		return $htmlList . '</ul>';
	}

	/*private static function getSettingsAsStr(CmsElement $mod) {
		$settingsStr = '';

		if($mod instanceof CmsElementSettingsLoadable === false)
			return '(no settings)';

		
		
		if($mod->hasSettings() === false)
			return '(no settings found)';

		foreach($mod->getSettings() as $k => $v) {
			if(in_array($k, array('mod_instance_IDFK')))
				continue;

			$value = htmlentities(strip_tags(print_r($v, true)));

			$value = (strlen($value) > 254)?substr($value, 0, 254) . ' [...]':$value;


			$settingsStr .= $k . ': ' . $value . "\n";
		}

		return $settingsStr;
	}*/
}

/* EOF */