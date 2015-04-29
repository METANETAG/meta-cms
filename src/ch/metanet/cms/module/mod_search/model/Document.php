<?php

namespace ch\metanet\cms\module\mod_search\model;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class Document
{
	protected $ID;
	protected $internalID;
	protected $type;
	protected $title;
	protected $description;
	protected $path;
	protected $language;

	/**
	 * @param int $ID
	 */
	public function setID($ID)
	{
		$this->ID = $ID;
	}

	/**
	 * @return int
	 */
	public function getID()
	{
		return $this->ID;
	}

	/**
	 * @param string $description
	 */
	public function setDescription($description)
	{
		$this->description = $description;
	}

	/**
	 * @return string
	 */
	public function getDescription()
	{
		return $this->description;
	}

	/**
	 * @param string $language
	 */
	public function setLanguage($language)
	{
		$this->language = $language;
	}

	/**
	 * @return string
	 */
	public function getLanguage()
	{
		return $this->language;
	}

	/**
	 * @param string $path
	 */
	public function setPath($path)
	{
		$this->path = $path;
	}

	/**
	 * @return string
	 */
	public function getPath()
	{
		return $this->path;
	}

	/**
	 * @param string $title
	 */
	public function setTitle($title)
	{
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * @param string $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @param int $internalID
	 */
	public function setInternalID($internalID)
	{
		$this->internalID = $internalID;
	}

	/**
	 * @return int
	 */
	public function getInternalID()
	{
		return $this->internalID;
	}
}

/* EOF */