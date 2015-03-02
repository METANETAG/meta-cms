<?php

namespace ch\metanet\cms\common;

use ch\metanet\cms\controller\common\CmsController;
use ch\metanet\cms\model\ModuleModel;
use timesplinter\tsfw\common\StringUtils;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpResponse;

/**
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

		$this->controllerRoutes = array();
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
			if(method_exists($this, $m) === false)
				throw new CMSException('The callback method ' . $m . ' does not exist in ' . get_class($this));

			$this->currentResponse = call_user_func(array($this, $m), $params);
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
	 * Returns the modules base URI to generate new relative URIs on that base
	 * 
	 * @return string Modules base URI as string
	 */
	protected abstract function getBaseURI();
	
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