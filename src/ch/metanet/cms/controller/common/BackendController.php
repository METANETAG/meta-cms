<?php

namespace ch\metanet\cms\controller\common;

use ch\metanet\cms\common\BackendControllerUnprotected;
use ch\metanet\cms\common\CmsModuleBackendController;
use ch\metanet\cms\common\CmsTemplateEngine;
use ch\metanet\cms\model\ModuleModel;
use ch\timesplinter\core\Core;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\HttpResponse;
use ch\timesplinter\core\RequestHandler;
use ch\timesplinter\core\Route;
use ch\metanet\cms\common\CmsView;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use timesplinter\tsfw\template\DirectoryTemplateCache;

/**
 * Entry point for backend requests
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * 
 * @property CmsModuleBackendController[] $loadedModules
 */
class BackendController extends CmsController
{
	public function __construct(Core $core, HttpRequest $httpRequest, Route $route)
	{
		parent::__construct($core, $httpRequest, $route);
		
		$cacheDir = $this->core->getSiteCacheDir() . 'templates' . DIRECTORY_SEPARATOR;
		$templateBaseDir = $this->core->getSiteRoot() . 'templates' . DIRECTORY_SEPARATOR;
		$tplCache = new DirectoryTemplateCache($cacheDir, $templateBaseDir);

		$this->cmsView = new CmsView(
			new CmsTemplateEngine($tplCache, 'tst'),
			$templateBaseDir . $this->currentDomain->template . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR
		);
		
		$this->checkAccess();
	}

	public function checkAccess()
	{
		if($this->isProtected() === true) {
			if(!$this->auth->isLoggedIn())
				RequestHandler::redirect($this->core->getSettings()->logincontroller->login_page);

			$this->abortIfUserHasNotRights('CMS_BACKEND_ACCESS');
		}
	}

	/**
	 * The render method for the backend
	 * 
	 * @param string $tplFile
	 * @param array $tplVars
	 * @return string
	 */
	protected function renderTemplate($tplFile, array $tplVars = array())
	{
		$pageHtml = $this->cmsView->render($tplFile . '.html', $tplVars);
		
		$tplVars['runtime'] = 0;

		if(isset($tplVars['scripts_footer']) === false)
			$tplVars['scripts_footer'] = null;
		
		if(isset($tplVars['siteTitle']) === false)
			$tplVars['siteTitle'] = null;
		
		return preg_replace_callback('/\s+id="nav-(.+?)"/', array($this, 'setCSSActive'), $this->renderBasicTemplate(
			$pageHtml, $tplVars
		));
	}

	/**
	 * Checks if a backend controller method is protected and the authenticated user has the needed  
	 * CMS_BACKEND_ACCESS right
	 * 
	 * @return bool
	 */
	private function isProtected()
	{
		$routeCallback = $this->route->methods[$this->httpRequest->getRequestMethod()];
		
		$controllerName = $routeCallback->controllerClass;
		$refClass = new \ReflectionClass($controllerName);
		
		if($refClass->implementsInterface(BackendControllerUnprotected::class) === false)
			return true;

		/** @var BackendControllerUnprotected $controllerName */
		
		$controllerMethod = $routeCallback->controllerMethod;
		$unprotectedMethods = $controllerName::getUnprotectedMethods();
		
		return (count($unprotectedMethods) > 0 && in_array($controllerMethod, $unprotectedMethods) === false);
	}

	/**
	 * Decorates active navigation items with the "active" CSS class
	 * 
	 * @param array $m
	 *
	 * @return string
	 */
	protected function setCSSActive(array $m)
	{
		return $m[0] . (($activeHtmlIds = $this->getActiveHtmlIds()) !== null && in_array($m[1], $activeHtmlIds)?' class="active"':null);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function loadNeededModules()
	{
		$moduleModel = new ModuleModel($this->db);
		
		foreach($moduleModel->getAllModules() as $module) {
			if(
				$module->backendcontroller === null ||
				class_exists($module->backendcontroller) === false ||
				($implementedInterfaces = class_implements($module->backendcontroller)) === false ||
				in_array(EventSubscriberInterface::class, $implementedInterfaces) === false
			) continue;

			$moduleControllerInstance =  new $module->backendcontroller($this, $module->name);

			$this->eventDispatcher->addSubscriber($moduleControllerInstance);
			$this->loadedModules[$module->name] = $moduleControllerInstance;
		}
	}
}

/* EOF */