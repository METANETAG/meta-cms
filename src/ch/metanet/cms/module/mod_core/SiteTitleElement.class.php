<?php

namespace ch\metanet\cms\module\mod_core;

use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\common\CmsView;
use ch\metanet\cms\controller\common\FrontendController;
use ch\timesplinter\db\DB;
use \stdClass;

/**
 * A very simple module which just prints out the sitetitle.
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class SiteTitleElement extends CmsElement {
	public function __construct($ID, $pageID) {
		parent::__construct($ID, $pageID, 'element_sitetitle');
	}

	public function render(FrontendController $frontendController, CmsView $view) {
		$siteTitle = '<h2>' . $frontendController->getCmsPage()->getTitle() . '</h2>';

		return $this->renderEditable($frontendController, $siteTitle);
	}
}

/* EOF */