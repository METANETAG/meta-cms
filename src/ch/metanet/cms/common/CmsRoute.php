<?php

namespace ch\metanet\cms\common;

/**
 * This represents a CMS route for a frontend page/redirect
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class CmsRoute
{
	protected $ID;
	protected $pattern;
	protected $robots;
	protected $redirectRoute;
	protected $externalSource;
	protected $pageID;
	protected $modID;
	protected $regex;
	protected $sslRequired;
	protected $sslForbidden;

	/**
	 * @param int $ID
	 * @param string $pattern
	 * @param int $regex
	 * @param string|null $robots
	 * @param CmsRoute|null $redirectRoute
	 * @param string $externalSource
	 * @param int $pageID
	 * @param int $modID
	 * @param int $sslRequired
	 * @param int $sslForbidden
	 */
	public function __construct($ID, $pattern, $regex, $robots, $redirectRoute, $externalSource, $pageID, $modID, $sslRequired, $sslForbidden)
	{
		$this->ID = $ID;
		$this->pattern = $pattern;
		$this->regex = $regex;
		$this->robots = $robots;
		$this->redirectRoute = $redirectRoute;
		$this->externalSource = $externalSource;
		$this->modID = $modID;
		$this->pageID = $pageID;
		$this->sslRequired = $sslRequired;
		$this->sslForbidden = $sslForbidden;
	}

	/**
	 * Returns the route ID
	 * 
	 * @return int|null
	 */
	public function getID()
	{
		return $this->ID;
	}

	/**
	 * Returns the route pattern
	 * 
	 * @return string|null
	 */
	public function getPattern()
	{
		return $this->pattern;
	}

	/**
	 * Returns the CMS route to which this one should redirect or null if this route does not redirect to another route
	 * 
	 * @return CmsRoute|null
	 */
	public function getRedirectRoute()
	{
		return $this->redirectRoute;
	}

	/**
	 * Returns the external resource or null if this route does link to one
	 * 
	 * @return string|null
	 */
	public function getExternalSource()
	{
		return $this->externalSource;
	}

	/**
	 * Returns the module ID or null if there is no module linked to this route
	 * 
	 * @return int|null
	 */
	public function getModuleID()
	{
		return $this->modID;
	}

	/**
	 * Returns the linked page ID if this route links to a page else null
	 * 
	 * @return int|null
	 */
	public function getPageID()
	{
		return $this->pageID;
	}

	/**
	 * Checks if this route should be interpreted as regular expression
	 * 
	 * @return int 1 if it should be interpreted as regular expression else 0
	 */
	public function isRegex()
	{
		return $this->regex;
	}

	/**
	 * Checks if SSL is required for this route to access it
	 * 
	 * @return int 1 if SSL is required else 0
	 */
	public function isSSLRequired()
	{
		return $this->sslRequired;
	}

	/**
	 * Checks of SSL is forbidden for this route to access it
	 * 
	 * @return int 1 if SSL is forbidden else 0
	 */
	public function isSSLForbidden()
	{
		return $this->sslForbidden;
	}
}

/* EOF */