<?php


namespace ch\metanet\cms\module\mod_pages\backend;

use ch\metanet\cms\backend\DashboardWidget;
use ch\metanet\cms\controller\common\BackendController;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 * @version 1.0.0
 */
class LastEditedPagesDashboardWidget extends DashboardWidget {
	public function __construct(BackendController $backendController) {
		parent::__construct($backendController, 'widget_pages_last_edits', 'Last page edits');
	}

	protected function renderContent() {
		$html = '<ul class="data">';

		$stmntLastEditedPages = $this->backendController->getDB()->prepare("
			SELECT p.ID, p.title, p.last_modified, l.ID login_ID, l.username
			FROM page p
			LEFT JOIN login l ON l.ID = p.modifier_IDFK
			ORDER BY p.last_modified DESC LIMIT 0,10
		");

		$resLastEditedPages = $this->backendController->getDB()->select($stmntLastEditedPages);

		foreach($resLastEditedPages as $p) {
			$html .= '<li><a href="/backend/module/mod_pages/page/' . $p->ID . '">' . $p->title . '</a><span class="note">at <em>' . $p->last_modified . '</em> by <a href="/backend/users/' . $p->login_ID . '/details">' . $p->username . '</a></span></li>';
		}

		$html .= '</ul>
		<p><a class="btn" href="/backend/module/mod_pages">view pages module</a></p>';

		return $html;
	}
}

/* EOF */ 