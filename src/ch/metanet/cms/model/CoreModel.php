<?php

namespace ch\metanet\cms\model;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class CoreModel extends Model
{
	public function getLanguages()
	{
		$stmntLangs = $this->db->prepare("
			SELECT code, name FROM language ORDER BY name
		");

		$resLangs = $this->db->select($stmntLangs);

		$langArr = array();

		foreach($resLangs as $l)
			$langArr[$l->code] = $l->name;

		return $langArr;
	}
}

/* EOF */