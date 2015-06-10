<?php

namespace ch\metanet\cms\common;

use ch\metanet\cms\controller\common\CmsController;
use ch\metanet\cms\model\ModuleModel;
use ch\metanet\cms\model\PageModel;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpResponse;

/**
 * The basic CMS module controller class which gives both backend and frontend modules some basic features.
 * 
 * @see CmsModuleFrontendController
 * @see CmsModuleBackendController
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
abstract class CmsModuleController
{
	protected $cmsController;
	protected $moduleSettings;
	protected $moduleName;
	
	protected $moduleModel;
	protected $controllerRoutes;

	protected $currentResponse;

	/** @var CmsRoute|null */
	protected $moduleRoute;

	/**
	 * @param CmsController $cmsController
	 * @param string $moduleName
	 */
	public function __construct(CmsController $cmsController, $moduleName)
	{
		$this->cmsController = $cmsController;
		$this->moduleName = $moduleName;
		$this->moduleSettings = $cmsController->getModuleSettings($moduleName);
		
		$this->moduleModel = new ModuleModel($this->cmsController->getDB());
		$this->pageModel = new PageModel($this->cmsController->getDB());

		$this->controllerRoutes = array();

		$this->moduleRoute = $this->pageModel->getRouteByModule($moduleName);
	}

	/**
	 * Matches a controller method to the given route
	 *
	 * @param string|null $path
	 *
	 * @throws CMSException
	 * @throws HttpException
	 * @return CmsModuleResponse|HttpResponse|string
	 */
	public function callMethodByPath($path)
	{
		$params = null;

		if($path === null)
			$path = '/';

		$methodToCall = null;

		foreach($this->controllerRoutes as $pattern => $func) {
			if(preg_match('@^' . $pattern . '$@', $path, $res) === 0)
				continue;

			$methodToCall = $func;
			array_shift($res);
			$params = $res;

			break;
		}

		if($methodToCall === null)
			throw new HttpException('There is no route which matches path "' . $path . '" in controller ' . get_class($this), 404);

		$requestMethod = $this->cmsController->getHttpRequest()->getRequestMethod();

		$methodsToCall = array();

		if(isset($methodToCall[$requestMethod]) === true) {
			$methodsToCall[] = $methodToCall[$requestMethod];
		}

		if(isset($methodToCall['*']) === true) {
			$methodsToCall[] = $methodToCall['*'];
		}

		if(count($methodsToCall) === 0)
			throw new HttpException('There is no method for path "' . $path . '" and requesttyp ' . $requestMethod . ' in controller ' . get_class($this), 405);

		$this->currentResponse = null;

		foreach($methodsToCall as $m) {
			if(is_array($m) === false)
				$callable = array($this, $m);
			else
				$callable = $m;

			if(is_callable($callable) === false)
				throw new CMSException('The callback method ' . get_class($callable[0]) . '::' . $callable[1] . ' does not exist in ' . get_class($this));

			$this->currentResponse = call_user_func($callable, $params);
		}

		return $this->currentResponse;
	}

	/**
	 * Renders the module content and add module specific information to the template
	 *
	 * @param string $tplFile The template file to render
	 * @param array $tplVars The template variables
	 * @return CmsModuleResponse
	 */
	protected abstract function renderModuleContent($tplFile, array $tplVars = array());
	
	/**
	 * @param string $moduleIdentifier
	 *
	 * @return bool
	 */
	public function isModuleInstalled($moduleIdentifier)
	{
		return ($this->moduleModel->getModuleByName($moduleIdentifier, false) !== null);
	}

	/**
	 * @param string $moduleIdentifier
	 *
	 * @return bool
	 */
	public function isModuleActive($moduleIdentifier)
	{
		return ($module = $this->moduleModel->getModuleByName($moduleIdentifier) !== null);
	}

	/**
	 * @return CmsModuleResponse
	 */
	public function getCurrentResponse()
	{
		return $this->currentResponse;
	}
}

/* EOF */