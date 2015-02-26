<?php


namespace ch\metanet\cms\controller\backend;
use ch\metanet\cms\backend\DashboardWidget;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\model\DashboardWidgetModel;
use ch\timesplinter\common\StringUtils;
use ch\timesplinter\core\HttpResponse;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class GeneralController extends BackendController {
	public function getDashboard() {
		$tplVars = array(
			'siteTitle' => 'Dashboard',
			'widgets_html' => $this->generateWidgetsHTML()
		);

		return new HttpResponse(200, $this->renderTemplate('backend-welcome', $tplVars));
	}

	private function generateWidgetsHTML() {
		$html = '';

		$dashboardWidgetModel = new DashboardWidgetModel($this->db);
		
		foreach($dashboardWidgetModel->getWidgetsByLoginID($this->auth->getUserID()) as $w) {
			/** @var DashboardWidget $wInstance */
			$wInstance = new $w->class($this);

			$html .= $wInstance->render();
		}

		return $html;
	}

	public function getPHPInfo()
	{
		ob_start();
		phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES | INFO_ENVIRONMENT | INFO_VARIABLES);
		$phpInfoOutput = ob_get_clean();
		$phpInfoStr = '';
		
		foreach($this->phpInfoToArray($phpInfoOutput) as $catTitle => $catData) {
			$phpInfoStr .= '<h3>' . $catTitle . '</h3>
			<table>
			<tbody>';

			foreach($catData as $key => $val) {
				$phpInfoStr .= '<tr>
				<th>' . $key . '</th>
				<td>'; 
				
				if(is_array($val) === true) {
					$phpInfoStr .= '<ul>';
					
					foreach($val as $subKey => $subVal) {
						$phpInfoStr .= '<li>' . $subKey . ': ' . $subVal . '</li>';
					}
					
					$phpInfoStr .= '</ul>';
				} else {
					$phpInfoStr .= $val;
				}
				         
		         $phpInfoStr .= '</td>
				</tr>';
			}

			$phpInfoStr .= '</tbody></table>';
		}
		
		return $this->generatePage(array(
			'siteTitle' => 'PHP info',
			'phpinfo' => $phpInfoStr
		), 200);
	}

	protected function phpInfoToArray($phpInfoOutput)
	{
		$info_arr = array();
		$info_lines = explode("\n", strip_tags($phpInfoOutput, "<tr><td><h2>"));
		$cat = "General";
		
		foreach($info_lines as $line) {
			// new cat?
			preg_match("~<h2>(.*)</h2>~", $line, $title) ? $cat = $title[1] : null;
			
			if(preg_match("~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~", $line, $val)) {
				$info_arr[$cat][$val[1]] = $val[2];
			} elseif(preg_match("~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~", $line, $val)) {
				$info_arr[$cat][$val[1]] = array("local" => $val[2], "master" => $val[3]);
			}
		}
		
		return $info_arr;
	}
}

/* EOF */