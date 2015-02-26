<?php

namespace ch\metanet\cms\model;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class RouteModel extends Model
{
	public function getRouteByID($routeID)
	{
		$stmntRoute = $this->db->prepare("
			SELECT ID, pattern, regex, page_IDFK, redirect_route_IDFK, mod_IDFK, robots, external_source, ssl_required, ssl_forbidden
			FROM route
			WHERE ID = ?
		");

		$resRoute = $this->db->select($stmntRoute, array(
			$routeID
		));

		if(count($resRoute) <= 0)
			return null;

		return $resRoute[0];
	}

	public function getAllRoutes()
	{
		$stmntRoutes = $this->db->prepare("
			SELECT ID, pattern, page_IDFK, mod_IDFK, redirect_route_IDFK, regex, robots, external_source, ssl_required, ssl_forbidden
			FROM route
			ORDER BY pattern
		");

		return $this->db->select($stmntRoutes);
	}

	public function getRoutesByPageID($pageID)
	{
		$stmntRoutes = $this->db->prepare("
			SELECT ID, pattern, page_IDFK, mod_IDFK, redirect_route_IDFK, regex, robots, external_source, ssl_required, ssl_forbidden
			FROM route
			WHERE page_IDFK = ?
			ORDER BY pattern
		");

		return $this->db->select($stmntRoutes, array($pageID));
	}
}

/* EOF */