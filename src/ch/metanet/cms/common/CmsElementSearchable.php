<?php


namespace ch\metanet\cms\common;

use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\controller\common\CmsController;
use timesplinter\tsfw\db\DB;
use stdClass;

/**
 * Makes a CMS element searchable through mod_search
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
interface CmsElementSearchable {
	/**
	 * Returns a string with all relevant data which should be searchable in this element
	 *
	 * @param DB $db The CMS database instance
	 * @param string $language The current language code (e.x. de, en, ...)
	 *
	 * @return string The string containing the searchable information
	 */
	public function renderSearchIndexContent(DB $db, $language);
}

/* EOF */