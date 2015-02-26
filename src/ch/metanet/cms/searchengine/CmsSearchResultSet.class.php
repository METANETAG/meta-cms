<?php


namespace ch\metanet\cms\searchengine;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class CmsSearchResultSet {
	private $results;
	private $resultSetName;

	public function __construct($resultSetName) {
		$this->resultSetName = $resultSetName;
		$this->results = array();
	}

	public function push(CmsSearchResult $cmsSearchResult) {
		$this->results[] = $cmsSearchResult;
	}

	public function getSearchResults() {
		return $this->results;
	}

	public function getResultSetName() {
		return $this->resultSetName;
	}
}

/* EOF */