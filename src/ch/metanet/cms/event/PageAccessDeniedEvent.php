<?php

namespace ch\metanet\cms\event;

use ch\timesplinter\core\HttpRequest;
use Symfony\Component\EventDispatcher\Event;

/**
 * Gets dispatched if there are no rights for the current user to visit a resource
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class PageAccessDeniedEvent extends Event
{
	protected $httpRequest;

	/**
	 * The related HTTP request
	 * 
	 * @param HttpRequest $httpRequest
	 */
	public function __construct(HttpRequest $httpRequest)
	{
		$this->httpRequest = $httpRequest;
	}

	/**
	 * The http request which lead to the access denied event
	 * 
	 * @return HttpRequest
	 */
	public function getHttpRequest()
	{
		return $this->httpRequest;
	}
}

/* EOF */