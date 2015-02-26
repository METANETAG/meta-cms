<?php


namespace ch\metanet\cms\module\mod_search\model;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class Document {
	private $ID;
	private $internalID;
	private $type;
	private $title;
	private $description;
	private $path;
	private $language;

	/**
	 * @param mixed $ID
	 */
	public function setID($ID) {
		$this->ID = $ID;
	}

	/**
	 * @return mixed
	 */
	public function getID() {
		return $this->ID;
	}

	/**
	 * @param mixed $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * @return mixed
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @param string $language
	 */
	public function setLanguage($language) {
		$this->language = $language;
	}

	/**
	 * @return mixed
	 */
	public function getLanguage() {
		return $this->language;
	}

	/**
	 * @param mixed $path
	 */
	public function setPath($path) {
		$this->path = $path;
	}

	/**
	 * @return mixed
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @param mixed $title
	 */
	public function setTitle($title) {
		$this->title = $title;
	}

	/**
	 * @return mixed
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @param mixed $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * @return mixed
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param mixed $internalID
	 */
	public function setInternalID($internalID) {
		$this->internalID = $internalID;
	}

	/**
	 * @return mixed
	 */
	public function getInternalID() {
		return $this->internalID;
	}
}

/* EOF */