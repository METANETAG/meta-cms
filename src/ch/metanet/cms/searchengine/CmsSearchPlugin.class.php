<?php


namespace ch\metanet\cms\searchengine;
use ch\timesplinter\db\DB;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
abstract class CmsSearchPlugin {
	protected  $db;

	public function __construct(DB $db) {
		$this->db = $db;
	}

	/**
	 * @param $keywords The keywords to search for
	 * @return CmsSearchResultSet The result set containig the matched entries
	 */
	public abstract function doSearch($keywords);
}

/* EOF */