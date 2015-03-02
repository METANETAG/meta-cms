<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Pascal
 * Date: 22.03.13
 * Time: 14:02
 * To change this template use File | Settings | File Templates.
 */

namespace ch\metanet\cms\model;

use timesplinter\tsfw\db\DB;

class Model {
	protected $db;

	public function __construct(DB $db) {
		$this->db = $db;
	}
}