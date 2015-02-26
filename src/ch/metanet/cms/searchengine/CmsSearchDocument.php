<?php


namespace ch\metanet\cms\searchengine;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class CmsSearchDocument {
	private $fields;

	public function __construct() {
		$this->fields = array();
	}

	public function addField(CmsSearchField $cmsSearchField) {
		$this->fields[] = $cmsSearchField;
	}
}

/* EOF */