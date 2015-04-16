<?php

namespace ch\metanet\cms\model;

use timesplinter\tsfw\db\DB;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013 by METANET AG, www.metanet.ch
 */
abstract class Model
{
	protected $db;

	public function __construct(DB $db)
	{
		$this->db = $db;
	}
}