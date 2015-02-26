<?php

namespace ch\metanet\cms\event;

use ch\timesplinter\core\HttpRequest;
use Symfony\Component\EventDispatcher\Event;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class PageNotFoundEvent extends Event
{
	protected $httpRequest;

	/**
	 * @param HttpRequest $httpRequest
	 */
	public function __construct(HttpRequest $httpRequest)
	{
		$this->httpRequest = $httpRequest;
	}

	/**
	 * The http request which lead to the page not found event
	 * 
	 * @return HttpRequest The according http request object
	 */
	public function getHttpRequest()
	{
		return $this->httpRequest;
	}
}

/* EOF */