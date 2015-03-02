<?php

namespace ch\metanet\cms\module\mod_core;

use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsElementApproachable;
use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\common\CmsModuleFrontendController;
use ch\metanet\cms\common\CmsModuleResponse;
use ch\metanet\cms\common\CmsView;
use ch\metanet\cms\controller\common\FrontendController;
use timesplinter\tsfw\db\DB;
use \stdClass;

/**
 * This element prints out a rendered page by a module frontend controller
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class DisplayModuleElement extends CmsElement
{
	public function __construct($ID, $pageID)
	{
		parent::__construct($ID, $pageID, 'element_display_mod');
	}

	public function render(FrontendController $frontendController, CmsView $view)
	{
		/** @var CmsModuleFrontendController $cmsMod */
		$cmsMod = $frontendController->getCmsModule();

		if($cmsMod === null)
			return $this->renderEditable($frontendController, '<p>No module active for this route to render: ' . $frontendController->getHttpRequest()->getPath() . '</p>');
		
		$moduleResponse = $cmsMod->getCurrentResponse();
		
		if($moduleResponse instanceof CmsModuleResponse)
			return $this->renderEditable($frontendController, $frontendController->getCmsView()->renderModuleResponse(
				$moduleResponse
			));
		elseif(is_scalar($cmsMod->getCurrentResponse()) === true) {
			return $this->renderEditable($frontendController, $moduleResponse);
		}
		
		return $this->renderEditable($frontendController, 'Illegal return type from CMS module.');
	}
}

/* EOF */