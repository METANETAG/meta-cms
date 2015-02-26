<?php

namespace ch\metanet\cms\common;
use ch\metanet\cms\controller\common\CmsController;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
interface BackendNavigationInterface
{
	const DISPLAY_IN_ADMIN_BAR = 1;
	const DISPLAY_IN_MOD_NAV = 2;

	/**
	 * Returns an array with relative link and corresponding label as key-value
	 *
	 * @param CmsController $cmsController
	 *
	 * @return array The navigation entries for this module
	 */
	public static function getNavigationEntries(CmsController $cmsController);
}

/* EOF */ 