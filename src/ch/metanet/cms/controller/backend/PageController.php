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
use ch\timesplinter\core\HttpException;
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
use Exception;

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
		} catch(Exception $e) {
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
	public function createNewModuleAjax()
	{
		$dropZoneID = $this->core->getHttpRequest()->getVar('dropzone');
		$modType = StringUtils::afterLast($this->core->getHttpRequest()->getVar('mod_type'), '-');
		$referrerPath = StringUtils::beforeFirst($this->httpRequest->getVar('referrer', 'strip_tags'), '?');

		$elementModel = new ElementModel($this->db);

		try {
			$revDate = date('YmdHis');

			if($this->core->getHttpRequest()->getVar('parent_module') !== null) {
				list($parentElementType, $parentElementID, $parentElementPageID) = explode('-',  $this->core->getHttpRequest()->getVar('parent_module'));

				$cmsPage = $this->pageModel->getPageByID($parentElementPageID);

				$pageElements = $elementModel->getElementTree($cmsPage);
				/** @var LayoutElement $elementInstance */
				$elementInstance = $elementModel->findElementIDInTree($pageElements, $parentElementID);

				$newModInstance = $elementModel->createElement($modType, $elementInstance, $parentElementPageID, $this->auth->getUserID());

				$this->db->beginTransaction($newModInstance->getIdentifier() . '.' . $newModInstance->getID() . '-' . $newModInstance->getPageID() . '.' . $revDate . '.create');

				/** @var HttpRequest $httpRequestFrontend */
				$httpRequestFrontend = clone $this->httpRequest;
				$httpRequestFrontend->setPath($referrerPath);
				$httpRequestFrontend->setRequestMethod('GET');

				$matchedRoutes = RouteUtils::matchRoutesAgainstPath($this->core->getSettings()->core->routes, $httpRequestFrontend);
				$filteredRoutes = RouteUtils::filterRoutesByMethod($matchedRoutes, $httpRequestFrontend->getRequestMethod());

				$route = $filteredRoutes[key($filteredRoutes)];

				$frontendController = new FrontendController($this->core, $httpRequestFrontend, $route);

				// Check if you use the site in preview mode or real
				if($route->id == 'cms-site-preview') {
					preg_match($route->pattern, $referrerPath, $res);

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
		} catch(Exception $e) {
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

	/**
	 * @param string $dropZoneID
	 * @param LayoutElement $parentElement
	 * @param CmsElement $newElement
	 *
	 * @return bool
	 * @throws CMSException
	 */
	protected function isAllowedElement($dropZoneID, LayoutElement $parentElement, CmsElement $newElement)
	{
		$dropZoneRestrictions = $parentElement->getDropzone($dropZoneID);

		if($dropZoneRestrictions === null)
			return true;

		$countWhiteList = count($dropZoneRestrictions['whitelist']);
		$countBlackList = count($dropZoneRestrictions['blacklist']);

		if($countWhiteList === 0 && $countBlackList === 0)
			return true;

		if($countWhiteList > 0 && $countBlackList > 0)
			throw new CMSException('You can not specify a black and a white list at the same time for the same dropzone (' . $dropZoneID . ')');

		if($countWhiteList > 0)
			return in_array(get_class($newElement), $dropZoneRestrictions['whitelist']);

		if($countBlackList > 0)
			return !in_array(get_class($newElement), $dropZoneRestrictions['blacklist']);

		return true;
	}

	/**
	 * @return HttpResponse
	 * @throws CMSException if element can not be found
	 * @throws \Exception
	 * @throws HttpException
	 */
	public function deleteModuleAjax()
	{
		list($elementType, $elementID, $pageID) = explode('-',  $this->httpRequest->getVar('module'));

		$referrerPath = StringUtils::beforeFirst($this->httpRequest->getVar('referrer', 'strip_tags'), '?');
		$html = null;

		/** @var HttpRequest $httpRequestFrontend */
		$httpRequestFrontend = clone $this->httpRequest;
		$httpRequestFrontend->setPath($referrerPath);
		$httpRequestFrontend->setRequestMethod('GET');

		$matchedRoutes = RouteUtils::matchRoutesAgainstPath($this->core->getSettings()->core->routes, $httpRequestFrontend);
		$matchedRoute = current($matchedRoutes);

		$frontendController = new FrontendController($this->core, $httpRequestFrontend, $matchedRoute);

		// Check if you use the site in preview mode or real
		if($matchedRoute->id == 'cms-site-preview') {
			preg_match($matchedRoute->pattern, $referrerPath, $res);

			$frontendController->getRoute()->setParams(array(0 => $res[1]));

			$frontendController->deliverPreviewCMSPage();
		} else {
			$frontendController->deliverCMSPage();
		}

		$cmsPage = $this->pageModel->getPageByID($pageID);

		$frontendController->setCmsPage($cmsPage);

		$elementModel = new ElementModel($this->db);
		$pageElements = $elementModel->getElementTree($cmsPage);

		$elementToDeleteInstance = $elementModel->findElementIDInTree($pageElements, $elementID);

		if($elementToDeleteInstance === null)
			throw new CMSException('Could not find module to delete: #' . $elementID);

		$parentElement = $elementToDeleteInstance->getParentElement();

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
		} catch(Exception $e) {
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

	private function toggleElementAjax($elementID, $pageID, $hide)
	{
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
		} catch(Exception $e) {
			$msg = ($hide ? 'Could not hide element' : 'Could not reveal element') . ': ' . $e->getMessage();

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

	/**
	 * @return HttpResponse
	 */
	public function hideElementAjax()
	{
		list($elementType, $elementID, $pageID) = explode('-', $this->core->getHttpRequest()->getVar('module'));

		return $this->toggleElementAjax($elementID, $pageID, true);
	}

	/**
	 * @return HttpResponse
	 */
	public function revealElementAjax()
	{
		list($elementType, $elementID, $pageID) = explode('-', $this->core->getHttpRequest()->getVar('module'));

		return $this->toggleElementAjax($elementID, $pageID, false);
	}

	/**
	 * @return HttpResponse
	 * @throws CMSException
	 * @throws HttpException
	 * @throws Exception
	 */
	public function updateModuleAjax()
	{
		list($elementType, $elementID, $elementPageID) = explode('-', $this->core->getHttpRequest()->getVar('module'));

		if(($updateMethod = $this->core->getHttpRequest()->getVar('method')) === null)
			$updateMethod = 'update';

		$cmsPage = $this->pageModel->getPageByID($elementPageID);
		$moduleModel = new ModuleModel($this->db);
		$modInstance = $moduleModel->getElementInstanceByID($elementID, $cmsPage);
		if($modInstance instanceof CmsElementSettingsLoadable === false)
			return new HttpResponse(500, 'This element has no settings which could be updated');
		/** @var CmsElementSettingsLoadable $modInstance */
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
			$this->updateInlineEditble(intval($elementID));
			$this->updateElementRevision($modInstance, $revDate);
			$this->updatePage($cmsPage);

			$this->db->commit();
		} catch(Exception $e) {
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

	/**
	 * @param $id
	 */
	private function updateInlineEditble($id)
	{
		$editQuery = $this->db->prepare("SELECT ei.editable FROM cms_element_instance ei WHERE ei.ID = ?");
		$editRes = $this->db->select($editQuery, array($id));
		$postEdit = $this->core->getHttpRequest()->getVar('editable');
		if($postEdit != $editRes) {
			$updateQry = $this->db->prepare("UPDATE cms_element_instance SET editable = ? WHERE ID = ?");
			$this->db->update($updateQry, array($postEdit ,$id));
		}
	}

	/**
	 * @param CmsElement $cmsElement
	 * @param string $revision
	 */
	private function updateElementRevision(CmsElement $cmsElement, $revision)
	{
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

	/**
	 * @return HttpResponse
	 * @throws CMSException
	 * @throws HttpException
	 * @throws Exception
	 */
	public function restoreElementAjax()
	{
		list($elementType, $elementID, $elementPageID) = explode('-', $this->httpRequest->getVar('module', 'strip_tags'));

		$revisionFile = $this->httpRequest->getVar('revision', 'strip_tags');

		$cmsPage = $this->pageModel->getPageByID($elementPageID);

		$moduleModel = new ModuleModel($this->db);
		$modInstance = $moduleModel->getElementInstanceByID($elementID, $cmsPage);

		try {
			$this->db->setListenersMute(true);
			$this->db->beginTransaction();

			$revisionControl = new RevisionControl($this->db);
			$revisionControl->restoreFromFile($revisionFile);

			$fileNameParts = explode('.', StringUtils::afterLast($revisionFile, '/'));

			$this->updateElementRevision($modInstance, $fileNameParts[2]);

			$this->db->commit();
			$this->db->setListenersMute(false);
		} catch(Exception $e) {
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

	/**
	 * Sets the last modified time to now and the last modifier to the authenticated user
	 *
	 * @param CmsPage $cmsPage The CMS page to update
	 */
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
}

/* EOF */