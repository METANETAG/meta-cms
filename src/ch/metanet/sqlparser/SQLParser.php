<?php


namespace ch\metanet\sqlparser;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
abstract class SQLParser {
	public function __construct() {

	}

	public abstract function parseQueryString($queryStr);
}

/* EOF */