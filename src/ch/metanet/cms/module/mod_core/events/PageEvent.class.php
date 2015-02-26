<?php

namespace ch\metanet\cms\module\mod_core\events;

use ch\metanet\cms\common\CmsPage;
use Symfony\Component\EventDispatcher\Event;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class PageEvent extends Event
{
	protected $page;
	
	public function __construct(CmsPage $page)
	{
		$this->page = $page;
	}

	/**
	 * @return CmsPage
	 */
	public function getPage()
	{
		return $this->page;
	}
}

/* EOF */ 