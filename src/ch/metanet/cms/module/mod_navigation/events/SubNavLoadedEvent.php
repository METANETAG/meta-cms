<?php

namespace ch\metanet\cms\module\mod_navigation\events;

use Symfony\Component\EventDispatcher\Event;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class SubNavLoadedEvent extends Event
{
	protected $currentEntry;
	protected $isActive;
	protected $activeRouteIds;
	protected $elementSettings;

	public function __construct($currentEntry, $isActive, $activeRouteIds, $elementSettings)
	{
		$this->currentEntry = $currentEntry;
		$this->isActive = $isActive;
		$this->activeRouteIds = $activeRouteIds;
		$this->elementSettings = $elementSettings;
	}

	/**
	 * Get all active routes
	 * @return array
	 */
	public function getActiveRouteIds()
	{
		return $this->activeRouteIds;
	}

	/**
	 * Get all information about the current navigation entry
	 * @return \stdClass
	 */
	public function getCurrentEntry()
	{
		return $this->currentEntry;
	}

	/**
	 * Check if the current navigation entry is active
	 * @return bool Returns true if active else false
	 */
	public function getIsActive()
	{
		return $this->isActive;
	}

	/**
	 * Current navigation elements settings
	 *
	 * @return \stdClass
	 */
	public function getElementSettings()
	{
		return $this->elementSettings;
	}
}

/* EOF */