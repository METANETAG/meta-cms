<?php


namespace ch\metanet\cms\model;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class PluginModel extends Model
{
	public function getAllActivePlugins()
	{
		$pluginsStmnt = $this->db->prepare("
			SELECT ID, name, class FROM cms_plugin WHERE active = 1 ORDER BY name
		");

		return $this->db->select($pluginsStmnt);
	}
}

/* EOF */