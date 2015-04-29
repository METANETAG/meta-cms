<?php

namespace ch\metanet\cms\common;

use ZendSearch\Lucene\Lucene;
use ZendSearch\Lucene\SearchIndexInterface;

/**
 * Some nice functions for using the zend lucene search implementation more effective.
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
 class LuceneUtils
 {
	 /**
	  * Opens a new zend search index. If it does not exist it will be created.
	  * 
	  * @param string $indexPath Path to the index
	  *
	  * @return SearchIndexInterface
	  */
	public static function openOrCreate($indexPath)
	{
		try {
			return Lucene::open($indexPath);
		} catch(\Exception $e) {
			return Lucene::create($indexPath);
		}
	}
}

/* EOF */