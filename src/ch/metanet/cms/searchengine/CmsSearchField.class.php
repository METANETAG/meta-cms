<?php


namespace ch\metanet\cms\searchengine;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class CmsSearchField {
	const FLD_TEXT = 1;
	const FLD_UNSORTED = 2;
	const FLD_BINARY = 3;

	private $name;
	private $content;
	private $type;

	public function __construct($name, $content, $type) {
		$this->name = $name;
		$this->content = $content;
		$this->type = $type;
	}

	/**
	 * @param mixed $content
	 */
	public function setContent($content) {
		$this->content = $content;
	}

	/**
	 * @return mixed
	 */
	public function getContent() {
		return $this->content;
	}

	/**
	 * @param mixed $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
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
}

/* EOF */