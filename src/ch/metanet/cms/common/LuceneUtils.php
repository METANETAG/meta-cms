<?php


namespace ch\metanet\cms\common;
use ZendSearch\Lucene\Lucene;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
 class LuceneUtils {
	public static function openOrCreate($indexPath) {
		try {
			return Lucene::open($indexPath);
		} catch(\Exception $e) {
			return Lucene::create($indexPath);
		}
	}
}

/* EOF */