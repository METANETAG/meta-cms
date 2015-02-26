<?php

/**
 * @author Pascal Muenst
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */

namespace ch\metanet\cms\model;

use ch\timesplinter\db\DB;

class TemplateModel extends Model {
	public function __construct(DB $db) {
		parent::__construct($db);
	}

	public function getModulesByLayout($layoutID) {

	}
}

/* EOF */