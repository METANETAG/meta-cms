<?php

namespace ch\metanet\cms\event;

use ch\timesplinter\core\HttpRequest;
use Symfony\Component\EventDispatcher\Event;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class PageAccessDeniedEvent extends Event
{
	protected $httpRequest;
	
	public function __construct(HttpRequest $httpRequest)
	{
		$this->httpRequest = $httpRequest;
	}

	/**
	 * @return HttpRequest
	 */
	public function getHttpRequest()
	{
		return $this->httpRequest;
	}
}

/* EOF */