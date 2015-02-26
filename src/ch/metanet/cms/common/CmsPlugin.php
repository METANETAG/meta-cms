<?php

namespace ch\metanet\cms\common;

use ch\metanet\cms\controller\common\CmsController;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class CmsPlugin
{
    protected $cmsController;

	public function __construct(CmsController $cmsController)
	{
		$this->cmsController = $cmsController;
	}
}

/* EOF */