<?php

namespace ch\metanet\cms\controller\common;

use ch\metanet\cms\common\CMSException;
use ch\metanet\cms\common\CmsModuleController;
use ch\metanet\cms\common\CmsModuleFrontendController;
use ch\metanet\cms\common\CmsRoute;
use ch\metanet\cms\common\CmsTemplateEngine;
use ch\metanet\cms\event\PageAccessDeniedEvent;
use ch\metanet\cms\event\PageNotFoundEvent;
use ch\metanet\cms\model\ElementModel;
use ch\metanet\cms\model\ModuleModel;
use ch\metanet\cms\model\PageModel;
use ch\timesplinter\core\Core;
use ch\timesplinter\core\FrameworkLoggerFactory;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\HttpResponse;
use ch\timesplinter\core\Route;
use ch\metanet\cms\common\CmsPage;
use ch\metanet\cms\common\CmsView;
use ch\timesplinter\logger\Logger;
use timesplinter\tsfw\common\JsonUtils;
use ch\timesplinter\core\RequestHandler;
use timesplinter\tsfw\template\DirectoryTemplateCache;
use timesplinter\tsfw\template\TemplateEngine;

/**
 * Entry point for frontend requests and services
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * 
 * @property CmsModuleFrontendController[] $loadedModules
 */
class FrontendController extends CmsController
{
	/** @var CmsPage|null $cmsPage */
	protected $cmsPage;
	/** @var CmsModuleController|null */
	protected $cmsModule;
	/** @var  CmsRoute|null $cmsRoute */
	protected $cmsRoute;
	/** @var Logger */
	protected $logger;
	/** @var TemplateEngine */
	protected $templateEngine;

	/**
	 * @param Core $core The framework core instance
	 * @param HttpRequest $httpRequest Frameworks HTTP request object
	 * @param Route $route Frameworks route object
	 */
	public function __construct(Core $core, HttpRequest $httpRequest, Route $route)
	{
		parent::__construct($core, $httpRequest, $route);

		$this->logger = FrameworkLoggerFactory::getLogger($this);
		
		if($this->auth->isLoggedIn() && $this->httpRequest->getProtocol() !== HttpRequest::PROTOCOL_HTTPS)
			RequestHandler::redirect($this->httpRequest->getURL(HttpRequest::PROTOCOL_HTTPS));
		
		$cacheDir = $this->core->getSiteCacheDir() . 'templates' . DIRECTORY_SEPARATOR;
		$templateBaseDir = $this->core->getSiteRoot() . 'templates' . DIRECTORY_SEPARATOR;
		
		$tplCache = new DirectoryTemplateCache($cacheDir, $templateBaseDir);
		$this->templateEngine = new CmsTemplateEngine($tplCache, 'tst');
		
		$this->cmsView = new CmsView(
			$this->templateEngine,
			$templateBaseDir . $this->currentDomain->template . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR
		);
	}

	/**
	 * Generates a CmsPage object according to the given route, renders it and creates the framework
	 * response for it.
	 * 
	 * @return HttpResponse The rendered page with all headers set ready to send back to the client
	 * @throws CMSException
	 * @throws HttpException
	 * @throws \Exception
	 */
	public function deliverCMSPage()
	{
		$pageModel = new PageModel($this->db);
		$this->cmsRoute = $pageModel->getRouteByURI($this->httpRequest->getPath());
		
		if($this->cmsRoute === null)
			//throw new HttpException('Could not find route: ' . $this->httpRequest->getPath(), 404);
			return $this->deliverPreviewCMSPage();

		if($this->cmsRoute->isSSLRequired() && $this->httpRequest->getProtocol() !== HttpRequest::PROTOCOL_HTTPS)
			RequestHandler::redirect($this->httpRequest->getURL(HttpRequest::PROTOCOL_HTTPS));
		elseif($this->auth->isLoggedIn() === false && $this->cmsRoute->isSSLForbidden() && $this->httpRequest->getProtocol() !== HttpRequest::PROTOCOL_HTTP)
			RequestHandler::redirect($this->httpRequest->getURL(HttpRequest::PROTOCOL_HTTP));

		// Update httpRequest object
		if($this->cmsRoute->isRegex()) {
			preg_match('@^' . $this->cmsRoute->getPattern() . '$@', $this->httpRequest->getPath(), $res);
			array_shift($res);

			$this->route->setParams($res);
		} elseif($this->cmsRoute->getModuleID() !== null) {
			preg_match('@^' . $this->cmsRoute->getPattern() . '(/.+)?$@', $this->httpRequest->getPath(), $res);
			array_shift($res);

			$this->route->setParams($res);
		}

		if($this->cmsRoute->getPageID() !== null) {
			$this->cmsPage = $pageModel->getPageByID($this->cmsRoute->getPageID());

			if($this->cmsRoute->getModuleID() !== null) {
				try {
					$modId = $this->cmsRoute->getModuleID();
					$modInfo = $this->moduleModel->getModuleById($modId);

					if($modInfo === null)
						throw new CMSException('The module with ID ' . $modId . ' has no frontend controller defined');
					
					if(isset($this->loadedModules[$modInfo->name]) === false)
						$cmsModuleInstance = new $modInfo->frontendcontroller($this, $modInfo->name);
					else
						$cmsModuleInstance = $this->loadedModules[$modInfo->name];
					
					if($cmsModuleInstance instanceof CmsModuleFrontendController === false)
						throw new CMSException('The module frontend controller for module ' . $modInfo->name . ' is none of type CmsModuleFrontendController');

					/** @var CmsModuleFrontendController $cmsModuleInstance */
					$this->cmsModule = $cmsModuleInstance;
					
					if(($response = $this->cmsModule->callMethodByPath($path = $this->route->getParam(0))) instanceof HttpResponse)
						return $response;
				} catch(HttpException $e) {
					if($e->getCode() === 404)
						$this->eventDispatcher->dispatch('cms.page_not_found', new PageNotFoundEvent($this->httpRequest));
					elseif($e->getCode() === 403)
						$this->eventDispatcher->dispatch('cms.page_access_denied', new PageAccessDeniedEvent($this->httpRequest));
					
					throw $e;
				}
			}

			return $this->generateCMSPage($pageModel);
		} elseif($this->cmsRoute->getExternalSource() !== null) {
			if($this->cmsRoute->isRegex())
				$redirectLocation = preg_replace('@' . str_replace('@', '\\@', $this->cmsRoute->getPattern()) . '@', $this->cmsRoute->getExternalSource(), $this->httpRequest->getPath());
			else
				$redirectLocation = $this->cmsRoute->getExternalSource();

			return new HttpResponse(301, null, array(
				'Location' => $redirectLocation
			));
		} elseif($this->cmsRoute->getRedirectRoute() !== null) {
			return new HttpResponse(301, null, array(
				'Location' => $this->cmsRoute->getRedirectRoute()->getPattern()
			));
		}
	}

	/**
	 * Delivers a page which is only available trough a preview URL
	 * 
	 * @return HttpResponse The rendered preview CMS page ready to send it to the client
	 * @throws HttpException if the preview page does not exist or is not accessible for the current user
	 */
	public function deliverPreviewCMSPage()
	{
		$pageModel = new PageModel($this->db);

		$pagePrevID = $this->getRoute()->getParam(0);

		if($pagePrevID === null) {
			$pathParts = explode('/', $this->httpRequest->getPath());
			$pagePrevID = array_pop($pathParts);
		}

		$this->cmsPage = $pageModel->getPageByUniqueID($pagePrevID);
		
		return $this->generateCMSPage($pageModel);
	}

	/**
	 * Renders the current frontend page into the basic frontend template.html file and adds some useful data to the
	 * template (e.x. logged in user if there is any, site title, etc.)
	 * 
	 * @param PageModel $pageModel
	 *
	 * @return HttpResponse The rendered CMS page ready to send it to the client
	 * @throws HttpException if the page does not exist or is not accessible for the current user
	 */
	protected function generateCMSPage(PageModel $pageModel)
	{
		if($this->cmsPage === null) {
			$this->eventDispatcher->dispatch('cms.page_not_found', new PageNotFoundEvent($this->httpRequest));
			
			throw new HttpException('Could not find cms page', 404);
		}

		if(!$pageModel->hasUserReadAccess($this->cmsPage, $this->auth)) {
			$this->eventDispatcher->dispatch('cms.page_access_denied', new PageAccessDeniedEvent($this->httpRequest));
			
			throw new HttpException('You don\'t have access to this page', 403);
		}

		$currentEnv = $this->core->getCurrentDomain()->environment;

		$lastChanged = new \DateTime($this->cmsPage->getLastModified());
		$cacheDuration = 18000; //31536000; <-- a year

		$responseHeaders = array(
			'Content-Type' => 'text/html; charset=utf-8',
			'Content-Language' => $this->cmsPage->getLanguage(),
			/*'Cache-Control' => 'public, max-age=' . $cacheDuration ,
			'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + $cacheDuration),
			'Last-Modified' => gmdate('D, d M Y H:i:s \G\M\T', $lastChanged->getTimestamp()),*/
			'Cache-Control' => 'No-Cache' ,
			'Last-Modified' => null,
			'Vary' => 'Accept-Encoding'
		);

		// Don't cache if a module controls page content
		/*if($this->cmsRoute->getModuleID() === null)
			$responseHeaders['Last-Modified'] = gmdate('D, d M Y H:i:s \G\M\T', $lastChanged->getTimestamp());*/

		/*if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $this->cmsPage->getLastModified() <= date('Y-m-d H:i:s', strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])))
			return new HttpResponse(304, null, $responseHeaders);*/

		$lang = $this->getLocaleHandler()->getLanguage();

		// HTML Cache here!
		$pageCacheActive = isset($this->core->getSettings()->cms->{$currentEnv}->page_cache)
			?$this->core->getSettings()->cms->{$currentEnv}->page_cache
			:false;

		$pageCacheMode = $this->cmsPage->getCacheMode();
		$cacheDir = $this->core->getSiteCacheDir() . 'html' . DIRECTORY_SEPARATOR;
		$pageCacheFile = $cacheDir . 'page-' . $this->cmsPage->getID() . '.html';

		if($pageCacheActive === true && $this->auth->isLoggedIn() === false && in_array($pageCacheMode, array(CmsPage::CACHE_MODE_PRIVATE, CmsPage::CACHE_MODE_PUBLIC)) && stream_resolve_include_path($pageCacheFile)) {
			$fileTime = filemtime($pageCacheFile);
			// TODO: check if a parent page has changed not only the current


			if($this->cmsPage->getLastModified() === null || $fileTime >= $lastChanged->getTimestamp()) {
				if($currentEnv === 'dev')
					echo 'Cached page found from: ' . date('d.m.Y H:i:s', $fileTime);

				return new HttpResponse(200, $pageCacheFile, $responseHeaders, true);
			}
		}


		$pageElementCache = (isset($this->core->getSettings()->cms->{$currentEnv}) && $this->core->getSettings()->cms->{$currentEnv}->page_cache === true);
		$elementModel = new ElementModel($this->db);
		$elements = $elementModel->getElementTree($this->cmsPage, $pageElementCache);
		
		if($this->cmsPage->getLayoutID() !== null && $elements->offsetExists($this->cmsPage->getLayoutID()))
			$this->cmsPage->setLayout($elements->offsetGet($this->cmsPage->getLayoutID()));

		$elementView = new CmsView(
			$this->templateEngine,
			$this->core->getSiteRoot() . 'templates' . DIRECTORY_SEPARATOR . $this->currentDomain->template . DIRECTORY_SEPARATOR . 'elements' . DIRECTORY_SEPARATOR
		);

		// Last modified
		$dtLastModified = new \DateTime($this->cmsPage->getLastModified());
		$dtCreated = new \DateTime($this->cmsPage->getCreated());

		$pageHtml = $this->cmsPage->render($this, $elementView);

		/*$dtLastModifiedRender = new \DateTime($this->cmsPage->getLastModified());

		$responseHeaders['Last-Modified'] = gmdate('D, d M Y H:i:s \G\M\T', $dtLastModifiedRender->getTimestamp());*/

		$tplVars = array(
			'siteTitle' => $this->cmsPage->getTitle(),
			'meta_description' => $this->cmsPage->getDescription(),
			'page_id' => $this->cmsPage->getID(),
			'page_html' => $pageHtml,
			'modifier_name' => $this->cmsPage->getModifierName(),
			'creator_name' => $this->cmsPage->getCreatorName(),
			'date_modified' => $dtLastModified->format($this->getLocaleHandler()->getDateTimeFormat()),
			'date_created' => $dtCreated->format($this->getLocaleHandler()->getDateTimeFormat()),
			'logged_in' => $this->auth->isLoggedIn(),
			'cms_page' => true,
			/*'js_revision' => isset($this->core->getSettings()->cms->{$currentEnv}->js_revision)?$this->core->getSettings()->cms->{$currentEnv}->js_revision:'v1',
			'css_revision' => isset($this->core->getSettings()->cms->{$currentEnv}->css_revision)?$this->core->getSettings()->cms->{$currentEnv}->css_revision:'v1',*/
			'area_head' => $this->generateAreaHead($this->cmsPage),
			'area_body' => $this->generateAreaBody($this->cmsPage),
			'current_uri' => urlencode($this->httpRequest->getURI())
		);

		if($this->auth->isLoggedIn()) {
			$stmntElements = $this->db->prepare("SELECT ID, name, class FROM cms_element_available WHERE active = '1' ORDER BY name");

			$resElements = $this->db->select($stmntElements);

			$elementList = array();

			foreach($resElements as $mod) {
				$mod->author = null;
				$mod->version = null;
				$mod->description = null;

				$settingsFile = $this->core->getSiteRoot() . 'settings' . DIRECTORY_SEPARATOR . 'elements' . DIRECTORY_SEPARATOR . $mod->name . '.config.json';

				if(stream_resolve_include_path($settingsFile) === false) {
					$elementList[] = $mod;
					continue;
				}

				try {
					$jsonObj = JsonUtils::decodeFile($settingsFile);

					if(isset($jsonObj->name) === true)
						$mod->name = isset($jsonObj->name->$lang)?$jsonObj->name->$lang:$jsonObj->name->en;

					if(isset($jsonObj->description) === true)
						$mod->description = isset($jsonObj->description->$lang)?$jsonObj->description->$lang:$jsonObj->description->en;
				} catch(\Exception $e) {
					$this->logger->error('Could not parse config file of element: ' . $mod->name, $e);
				}

				$elementList[] = $mod;
			}

			usort($elementList, function($a, $b) {
				return (strtolower($a->name) > strtolower($b->name));
			});

			$tplVars['elements'] = $elementList;
		}

		$html = $this->renderBasicTemplate('template.html', $tplVars);

		if($pageCacheActive === true && $this->auth->isLoggedIn() === false && in_array($pageCacheMode, array(CmsPage::CACHE_MODE_PRIVATE, CmsPage::CACHE_MODE_PUBLIC)))
			file_put_contents($pageCacheFile, $html);

		$httpStatusCode = ($this->cmsPage->getErrorCode() !== null)?$this->cmsPage->getErrorCode():200;
		
		return new HttpResponse($httpStatusCode, $html, $responseHeaders);
	}

	/**
	 * Calls a service and returns the result of it back to the client. This is needed for API related things which
	 * should not be handled over the template rendering CMS page part {@see generateCMSPage}. There is also no route
	 * needed. The services map to whatever you defined to call this method in settings/routes.json.
	 * E.x. http://example.com/{your_path_to_this_method}/module_name/service_name
	 * 
	 * @return HttpResponse With the information which should be sent back to the client
	 * @throws CMSException if the called service module has no FrontendController defined
	 */
	public function callService()
	{
		$moduleId = $this->route->getParam(0);
		$serviceName = $this->route->getParam(1);

		$modInfo = $this->moduleModel->getModuleByName($moduleId);
		
		if(isset($modInfo->frontendcontroller) === false || $modInfo->frontendcontroller === null)
			throw new CMSException('Service error! No FrontendController defined for module: ' . $moduleId);

		/** @var CmsModuleFrontendController $controllerInstance */
		$controllerInstance = new $modInfo->frontendcontroller($this, $modInfo->name);

		return $controllerInstance->callServiceByName($serviceName);
	}

	/**
	 * Generates a string with all the JS/CSS stored using {@see CmsPage::addJs} and {@see CmsPage::addCss} using the
	 * target {@see CmsPage::PAGE_AREA_HEAD}
	 * 
	 * @param CmsPage $cmsPage The requested CMS page
	 *
	 * @return string The HTML for all the JS/CSS which should ne present in the footer of the page
	 */
	protected function generateAreaHead(CmsPage $cmsPage)
	{
		$env = $this->currentDomain->environment;
			
		return ($this->core->getSettings()->core->environments->{$env}->debug === true) ? '<!-- AREA HEAD -->' . PHP_EOL : null;
	}

	/**
	 * Generates a string with all the JS/CSS stored using {@see CmsPage::addJs} using the
	 * target {@see CmsPage::PAGE_AREA_BODY}
	 * 
	 * @param CmsPage $cmsPage The requested CMS page
	 *
	 * @return string The HTML for all the JS which should ne present in the footer of the page
	 */
	protected function generateAreaBody(CmsPage $cmsPage)
	{
		$env = $this->currentDomain->environment;
		$isDebug = ($this->core->getSettings()->core->environments->{$env}->debug === true);
			
		$areaBodyHtml = $isDebug ? '<!-- AREA BODY START -->' . PHP_EOL : null;
		$jsEntries = $cmsPage->getJs(CmsPage::PAGE_AREA_BODY);

		foreach($jsEntries as $jsGroup) {
			foreach($jsGroup as $jsEntry)
				$areaBodyHtml .= $jsEntry . PHP_EOL;
		}
		
		$areaBodyHtml .= $isDebug ? '<!-- AREA BODY STOP -->' . PHP_EOL : null;

		return $areaBodyHtml . PHP_EOL;
	}

	/**
	 * Generates an error page for a thrown exception which was not caught anywhere else in the code
	 * 
	 * @param \Exception $e The uncaught exception
	 *
	 * @return HttpResponse The HTTP response for sending back to the client (e.x. a nice styled error message)
	 */
	public function generateErrorPage(\Exception $e)
	{
		$errorCode = 500;

		if($e instanceof HttpException)
			$errorCode = $e->getCode();

		if($e instanceof HttpException === false || $e->getCode() === 500)
			$this->logger->error('An uncaught error occurred', $e);

		$stmntErrorPage = $this->db->prepare("SELECT ID FROM page WHERE role = 'error' AND error_code = ?");
		$resErrorPage = $this->db->select($stmntErrorPage, array($errorCode));

		$env = $this->currentDomain->environment;
		
		if($this->core->getSettings()->core->environments->{$env}->debug === true || count($resErrorPage) <= 0)
			return parent::generateErrorPage($e);

		$pageModel = new PageModel($this->db);
		$this->cmsPage = $pageModel->getPageByID($resErrorPage[0]->ID);

		return $this->generateCMSPage($pageModel);
	}

	/**
	 * The render method for the frontend
	 * 
	 * @param string $tplFile The template file to render
	 * @param array $tplVars The template data to relay on
	 * 
	 * @return string The rendered content
	 */
	protected function renderTemplate($tplFile, array $tplVars = array())
	{
		return $this->renderBasicTemplate(
			$this->cmsView->render($tplFile, $tplVars), $tplVars
		);
	}

	/**
	 * Returns the currently requested CMS page
	 * 
	 * @return CmsPage|null The CMS page which currently gets processed
	 */
	public function getCmsPage()
	{
		return $this->cmsPage;
	}

	/**
	 * The view which renders the frontend templates
	 * 
	 * @return CmsView The view
	 */
	public function getCmsView()
	{
		return $this->cmsView;
	}

	/**
	 * Sets the currently requested CMS page
	 * 
	 * @param CmsPage $cmsPage The CMS page which should get processed
	 */
	public function setCmsPage(CmsPage $cmsPage)
	{
		$this->cmsPage = $cmsPage;
	}

	/**
	 * Returns the CMS route which matched against the requested URI
	 * 
	 * @return CmsRoute|null The CMS route or null if no route did match
	 */
	public function getCmsRoute()
	{
		return $this->cmsRoute;
	}

	/**
	 * Returns an instance of the module bound to the requested URI
	 * 
	 * @return CmsModuleFrontendController|null The module or null if no module is bound
	 */
	public function getCmsModule()
	{
		return $this->cmsModule;
	}

	
	/**
	 * {@inheritdoc}
	 */
	protected function loadNeededModules()
	{
		$moduleModel = new ModuleModel($this->db);
		
		foreach($moduleModel->getAllModules() as $module)
		{
			if(
				$module->frontendcontroller === null ||
				class_exists($module->frontendcontroller) === false ||
				($implementedInterfaces = class_implements($module->frontendcontroller)) === false || 
				in_array('Symfony\Component\EventDispatcher\EventSubscriberInterface', $implementedInterfaces) === false
			) continue;

			$moduleControllerInstance =  new $module->frontendcontroller($this, $module->name);
			
			$this->eventDispatcher->addSubscriber($moduleControllerInstance);
			$this->loadedModules[$module->name] = $moduleControllerInstance;
		}
	}
}

/* EOF */