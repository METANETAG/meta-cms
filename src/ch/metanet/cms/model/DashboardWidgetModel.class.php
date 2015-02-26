<?php


namespace ch\metanet\cms\model;
use ch\metanet\cms\backend\DashboardWidget;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 * @version 1.0.0
 */
class DashboardWidgetModel extends Model
{
	/**
	 * @param $loginID
	 *
	 * @return \stdClass[]
	 */
	public function getWidgetsByLoginID($loginID)
	{
		$stmntLoginWidgets = $this->db->prepare("
			SELECT ID, name, class
			FROM cms_dashboard_widget_to_login wtl
			LEFT JOIN cms_dashboard_widget_available wa ON wa.ID = wtl.widget_IDFK
			WHERE wtl.login_IDFK = ?
		");

		return $this->db->select($stmntLoginWidgets, array(
			$loginID
		));
	}
}

/* EOF */ 