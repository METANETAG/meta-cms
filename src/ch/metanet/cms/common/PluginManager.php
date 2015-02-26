<?php

namespace ch\metanet\cms\common;

use ch\metanet\cms\controller\common\CmsController;
use ch\metanet\cms\model\PluginModel;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class PluginManager
{
	protected $cmsController;
	protected $activePlugins;

	public function __construct(CmsController $cmsController)
	{
		$this->cmsController = $cmsController;

		$this->registerPlugins();
	}

	private function registerPlugins()
	{
		$this->activePlugins = array();

		$pluginModel = new PluginModel($this->cmsController->getDB());

		foreach($pluginModel->getAllActivePlugins() as $p) {
			$this->activePlugins[$p->name] = new $p->class($this->cmsController);
		}
	}

	public function invokeHook($hookName)
	{
		$args = func_get_args();
		array_shift($args);
		
		$returnValue = array();
		
		foreach($this->activePlugins as $p) {
			if(method_exists($p, $hookName) === false)
				continue;

			$returnValue[$p] = call_user_func_array(array($p, $hookName), $args);
		}
		
		return $returnValue;
	}

	public function getAllPlugins()
	{
		return $this->activePlugins;
	}
}

/* EOF */