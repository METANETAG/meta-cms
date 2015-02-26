<?php


namespace ch\metanet\cms\searchengine;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class CmsSearchResult {
	private $title;
	private $language;
	private $summary;
	private $link;
	private $relevance;

	public function __construct($title, $link, $language) {
		$this->title = $title;
		$this->link = $link;
		$this->language = $language;
	}

	/**
	 * @param string $language
	 */
	public function setLanguage($language) {
		$this->language = $language;
	}

	/**
	 * @return string
	 */
	public function getLanguage() {
		return $this->language;
	}

	/**
	 * @param string $link
	 */
	public function setLink($link) {
		$this->link = $link;
	}

	/**
	 * @return string
	 */
	public function getLink() {
		return $this->link;
	}

	/**
	 * @param string $summary
	 */
	public function setSummary($summary) {
		$this->summary = $summary;
	}

	/**
	 * @return string
	 */
	public function getSummary() {
		return $this->summary;
	}

	/**
	 * @param string $title
	 */
	public function setTitle($title) {
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @param mixed $relevance
	 */
	public function setRelevance($relevance) {
		$this->relevance = $relevance;
	}

	/**
	 * @return mixed
	 */
	public function getRelevance() {
		return $this->relevance;
	}
}

/* EOF */