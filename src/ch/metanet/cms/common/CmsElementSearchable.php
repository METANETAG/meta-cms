<?php


namespace ch\metanet\cms\common;

use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\controller\common\CmsController;
use timesplinter\tsfw\db\DB;
use stdClass;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
interface CmsElementSearchable {
	/**
	 *
	 * @param DB $db
	 * @param string $language
	 *
	 * @return string
	 */
	public function renderSearchIndexContent(DB $db, $language);
}

/* EOF */