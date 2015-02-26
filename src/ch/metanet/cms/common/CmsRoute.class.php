<?php

namespace ch\metanet\cms\common;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class CmsRoute
{
	private $ID;
	private $pattern;
	private $robots;
	private $redirectRoute;
	private $externalSource;
	private $pageID;
	private $modID;
	private $regex;
	private $sslRequired;
	private $sslForbidden;

	public function __construct($ID, $pattern, $regex, $robots, $redirectRoute, $externalSource, $pageID, $modID, $sslRequired, $sslForbidden) {
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
	 * @return int
	 */
	public function getID() {
		return $this->ID;
	}

	/**
	 * @return string
	 */
	public function getPattern() {
		return $this->pattern;
	}

	/**
	 * @return CmsRoute|null
	 */
	public function getRedirectRoute() {
		return $this->redirectRoute;
	}

	/**
	 * @return string|null
	 */
	public function getExternalSource() {
		return $this->externalSource;
	}

	/**
	 * @return int|null
	 */
	public function getModuleID() {
		return $this->modID;
	}

	/**
	 * @return int|null
	 */
	public function getPageID() {
		return $this->pageID;
	}

	public function isRegex() {
		return $this->regex;
	}

	public function isSSLRequired() {
		return $this->sslRequired;
	}

	public function isSSLForbidden() {
		return $this->sslForbidden;
	}
}

/* EOF */