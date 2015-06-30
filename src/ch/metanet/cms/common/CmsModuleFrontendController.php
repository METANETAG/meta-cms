<?php

namespace ch\metanet\cms\common;

use ch\metanet\cms\controller\common\FrontendController;
use timesplinter\tsfw\common\StringUtils;
use ch\timesplinter\core\FrameworkLoggerFactory;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpResponse;

/**
 * The basic controller which should each frontend controller from a CMS module extend. This class provides some basic
 * and fundamental backend features and make a frontend controller of a module recognizable for the CMS as such.
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * 
 * @property FrontendController $cmsController
 */
abstract class CmsModuleFrontendController extends CmsModuleController
{
	protected $services;
	
	/**
	 * @param FrontendController $frontendController
	 * @param string $moduleName
	 */
	public function __construct(FrontendController $frontendController, $moduleName)
	{
		parent::__construct($frontendController, $moduleName);

		$this->services = array();
	}

	/**
	 * @param string $serviceName
	 *
	 * @return HttpResponse
	 */
	public function callServiceByName($serviceName)
	{
		$methodName = isset($this->services[$serviceName])?$this->services[$serviceName]:null;
		
		if($methodName === null || is_callable(array($this, $methodName)) === false)
			return new HttpResponse(404, 'The service "' . $serviceName . '" is not registered for this module or isn\'t a valid method');

		try {
			return $this->$methodName();
		} catch(HttpException $e) {
			return new HttpResponse($e->getCode(), $e->getMessage());
		} catch(\Exception $e) {
			return new HttpResponse(500, 'There has been a problem with this service: ' . $e->getMessage());
		}
	}

	/**
	 * Checks if a user has one of the given cms right (XOR)
	 * @param array|string $rights Single right or array of rights
	 * @return bool True if user has right, false if not
	 */
	protected function hasUserRights($rights)
	{
		foreach((array)$rights as $r) {
			if($this->cmsController->getAuth()->hasCmsRight($r) === true)
				return true;
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function renderModuleContent($tplFile, array $tplVars = array())
	{
		$tplVars['module_settings'] = $this->moduleSettings;
		$tplVars['base_link'] = $this->moduleRoute->getPattern();

		return new CmsModuleResponse($tplFile, $tplVars);
	}

	protected function getBaseURI()
	{
		return ($this->cmsController->getCmsRoute() instanceof CmsRoute) ? $this->cmsController->getCmsRoute()->getPattern() : null;
	}

	protected function registerService($name, $methodName)
	{
		$this->services[$name] = $methodName;
	}
}

/* EOF */