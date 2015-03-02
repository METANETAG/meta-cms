<?php

namespace ch\metanet\cms\controller\backend;

use ch\metanet\cms\common\BackendNavigationInterface;
use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\common\CMSException;
use ch\metanet\cms\common\CmsModuleBackendController;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\model\ElementModel;
use ch\metanet\cms\model\ModuleModel;
use ch\metanet\cms\model\PageModel;
use timesplinter\tsfw\common\JsonUtils;
use ch\timesplinter\core\Core;
use ch\timesplinter\core\FrameworkLoggerFactory;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\HttpResponse;
use ch\timesplinter\core\RequestHandler;
use ch\timesplinter\core\Route;
use ch\timesplinter\formhelper\FormHelper;
use ch\timesplinter\core\PHPException;

/**
 * The module controller handles installed modules
 *
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class ModuleController extends BackendController
{
	/** @var $formHelper FormHelper */
	protected $formHelper;
	protected $currentModuleInfo;
	private $logger;

	public function __construct(Core $core, HttpRequest $httpRequest, Route $route)
	{
		parent::__construct($core, $httpRequest, $route);

		$this->logger = FrameworkLoggerFactory::getLogger($this);
		$this->markHtmlIdAsActive('modules');
	}

	/**
	 * @return HttpResponse
	 * @throws CMSException
	 */
	public function getAjaxSettingsBox()
	{
		$pageModel = new PageModel($this->db);
		$cmsPage = $pageModel->getPageByID($this->route->getParam(1));

		$moduleModel = new ModuleModel($this->db);
		$elementInstance = $moduleModel->getElementInstanceByID($this->route->getParam(0), $cmsPage, false, false);

		if($elementInstance instanceof CmsElementSettingsLoadable === false)
			return $this->generateResponse(404, 'No configurable module');
		
		try {
			/** @var CmsElementSettingsLoadable $elementInstance */
			$res = $elementInstance->generateConfigBox($this, $cmsPage->getID());
		} catch(PHPException $e) {
			return $this->generateResponse(500, '<p><b>PHP Error:</b> ' . $e->getMessage() . ' in ' . $e->getFile() . ' (Line: ' . $e->getLine(). ')</p><pre>' . $e->getTraceAsString() . '</pre>');
		} catch(\Exception $e) {
			return $this->generateResponse(500, '<p>' . $e->getMessage() . '</p>');
		}

		return $this->generateResponse(200, $res);
	}

	/**
	 * @return HttpResponse
	 */
	public function getAjaxRevisionControlBox()
	{
		$pageModel = new PageModel($this->db);
		$cmsPage = $pageModel->getPageByID($this->route->getParam(1));

		$elementModel = new ElementModel($this->db);
		$elementTree = $elementModel->getElementTree($cmsPage);

		$elementInstance = $elementModel->findElementIDInTree($elementTree, $this->route->getParam(0));

		try {
			$res = $elementInstance->generateRevisionBox($this, $cmsPage->getID());
		} catch(PHPException $e) {
			return $this->generateResponse(500, '<p><b>PHP Error:</b> ' . $e->getMessage() . ' in ' . $e->getFile() . ' (Line: ' . $e->getLine(). ')</p><pre>' . $e->getTraceAsString() . '</pre>');
		} catch(\Exception $e) {
			return $this->generateResponse(500, '<p>' . $e->getMessage() . '</p>');
		}

		return $this->generateResponse(200, $res);
	}

	/**
	 * Shows all the pages and their dependencies
	 * 
	 * @return HttpResponse
	 */
	public function getModulesOverview()
	{
		if($this->httpRequest->getVar('deactivate') !== null)
			$this->setActivationOfModule($this->httpRequest->getVar('deactivate'), false);

		if($this->httpRequest->getVar('activate') !== null)
			$this->setActivationOfModule($this->httpRequest->getVar('activate'), true);

		$stmntMods = $this->db->prepare("
			SELECT ID, name identifier, active, path, manifest_content
			FROM cms_mod_available
			ORDER BY name
		");

		$resMods = $this->db->select($stmntMods);

		$lang = $this->core->getLocaleHandler()->getLanguage();

		foreach($resMods as $mod) {
			$mod->version = null;
			$mod->author = null;
			$mod->description = null;
			$mod->name = null;
			$mod->active_link = ($mod->active == 1)?'yes [<a href="?deactivate=' . $mod->ID . '">deactivate</a>]':'no [<a href="?activate=' . $mod->ID . '">activate</a>]';

			try {
				$manifestObj = JsonUtils::decode($mod->manifest_content);

				if(isset($manifestObj->version))
					$mod->version = $manifestObj->version;

				if(isset($manifestObj->author->name))
					$mod->author = $manifestObj->author->name;

				if(isset($manifestObj->desciption->{$lang}))
					$mod->description = $manifestObj->description->{$lang};
				elseif(isset($manifestObj->description->en))
					$mod->description = $manifestObj->description->de;

				if(isset($manifestObj->name->{$lang}))
					$mod->name = $manifestObj->name->{$lang};
				elseif(isset($manifestObj->name->en))
					$mod->name = $manifestObj->name->de;
			} catch(\Exception $e) {
				$this->logger->error('Could not load module information from json string', $e);
				continue;
			}
		}

		return $this->generatePageFromTemplate('backend-modules-overview.html', array(
			'modules' => $resMods,
			'siteTitle' => 'Modules'
		));
	}

	/**
	 * @param int $modID Module ID to activate
	 * @param bool $active "true" for setting it active "false" for disabling it
	 */
	public function setActivationOfModule($modID, $active)
	{
		$stmntActivate = $this->db->prepare("
			UPDATE cms_mod_available SET active = ? WHERE ID = ?
		");

		$this->db->update($stmntActivate, array(
			($active)?1:0,
			$modID
		));
	}

	/**
	 * @return HttpResponse
	 */
	public function getModuleDetail()
	{
		$modInfo = null;
		
		try {
			$modInfo = $this->moduleModel->getModuleByName($this->route->getParam(0));
			
			if($modInfo === null)
				throw new HttpException('The module <b>' . $this->route->getParam(0) . '</b> does not have a backend controller', 500);
			elseif(class_exists($modInfo->backendcontroller) === false)
				throw new HttpException('The <b>' . $this->route->getParam(0) . '</b> modules backend controller <b>' . $modInfo->backendcontroller . '</b> could not been found', 500);
				
			/** @var CmsModuleBackendController $modController */
			$modController = new $modInfo->backendcontroller($this, $modInfo->name);

			if(($modController instanceof CmsModuleBackendController) === false)
				throw new CMSException('This is no CmsModuleBackendController class for module: ' . $modInfo->name);

			$this->currentModuleInfo = $modInfo;
			$this->markHtmlIdAsActive($modInfo->name);
			
			$moduleResponse = $modController->callMethodByPath($this->route->getParam(1));
					
			if($moduleResponse instanceof HttpResponse)
				return $moduleResponse;
			
			return new HttpResponse(200, $this->renderTemplate(
				$moduleResponse->getTplFile(), 
				$moduleResponse->getTplVars()
			));
		} catch(HttpException $e) {
			$controller = isset($modInfo->backendcontroller)?$modInfo->backendcontroller:'&lt;unknown&gt;';
			
			return $this->generatePageFromTemplate('mod-error.html', array(
				'siteTitle' => 'Error occurred',
				'controller' => $controller,
				'error' => $e
			), $e->getCode());
		} catch(\Exception $e) {
			$controller = isset($modInfo->backendcontroller)?$modInfo->backendcontroller:'&lt;unknown&gt;';
			
			return $this->generatePageFromTemplate('mod-error.html', array(
				'siteTitle' => 'Error occurred',
				'controller' => $controller,
				'error' => $e
			), 500);
		}
	}

	/**
	 * @return HttpResponse
	 */
	public function postModuleDetail()
	{
		$modEditLang = $this->getHttpRequest()->getVar('mod_edit_lang', 'strip_tags');

		if($modEditLang !== null) {
			$_SESSION['mod_edit_lang'] = $modEditLang;

			RequestHandler::redirect($_SERVER['REQUEST_URI']);
		}

		return $this->getModuleDetail();
	}

	/**
	 * @param string $tplFile
	 * @param array $tplVars
	 *
	 * @return string
	 */
	protected function renderTemplate($tplFile, array $tplVars = array())
	{
		$pageHtml = '';
			
		if($this->currentModuleInfo !== null)
			$pageHtml = $this->renderModuleNavigation(
				$this->currentModuleInfo->backendcontroller,
				'/backend/module/' . $this->currentModuleInfo->name,
				BackendNavigationInterface::DISPLAY_IN_MOD_NAV
			);
		
		$pageHtml .= $this->cmsView->render($tplFile, $tplVars);
		
		return preg_replace_callback('/\s+id="nav-(.+?)"/', array($this,'setCSSActive'), $this->renderBasicTemplate(
			$pageHtml, $tplVars
		));
	}
}

/* EOF */