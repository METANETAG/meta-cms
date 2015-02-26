<?php

namespace ch\metanet\cms\module\mod_maintenance\backend;

use ch\metanet\cms\common\CmsBackendMessage;
use ch\metanet\cms\common\CmsModuleBackendController;
use ch\metanet\cms\common\BackendNavigationInterface;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\controller\common\CmsController;
use ch\metanet\cms\tablerenderer\Column;
use ch\metanet\cms\tablerenderer\DateColumnDecorator;
use ch\metanet\cms\tablerenderer\EmptyValueColumnDecorator;
use ch\metanet\cms\tablerenderer\RewriteColumnDecorator;
use ch\metanet\cms\tablerenderer\TableRenderer;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\RequestHandler;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class ModuleBackendController extends CmsModuleBackendController implements BackendNavigationInterface
{
	public function __construct(BackendController $moduleController, $moduleName)
	{
		parent::__construct($moduleController, $moduleName);

		$this->controllerRoutes = array(
			'/' => array(
				'GET' => 'getModuleOverview'
			),

			'/http-errors' => array(
				'*' => 'viewHttpErrors'
			),

			'/http-errors/view/(\d+)' => array(
				'GET' => 'viewHttpErrorDetail'
			)
		);
	}

	public function getModuleOverview()
	{
		return $this->renderModuleContent('mod-maintenance/overview', array(
			'siteTitle' => 'Maintenance'
		));
	}
	
	public function viewHttpErrors()
	{
		if(($deleteRouteId = $this->cmsController->getHttpRequest()->getVar('delete')) !== null) {
			try {
				$stmntRemove = $this->cmsController->getDB()->prepare("DELETE FROM mod_maintenance_page_not_found WHERE ID = ?");

				$this->cmsController->getDB()->delete($stmntRemove, array($deleteRouteId));
				
				$this->setMessageForNextPage(new CmsBackendMessage(
					'Path removed successfully', CmsBackendMessage::MSG_TYPE_SUCCESS
				));
				
				RequestHandler::redirect($this->getBaseURI() . '/http-errors');
			} catch(\Exception $e) {
				$this->setMessageForNextPage(new CmsBackendMessage(
					'Could not remove path', CmsBackendMessage::MSG_TYPE_ERROR
				));
			}
		}
		
		$sqlString = "
			SELECT *
			FROM mod_maintenance_page_not_found pnf
			LEFT JOIN (
				SELECT page_not_found_IDFK,
				COUNT(*) error_count,
				COUNT(DISTINCT ip_address) ip_address_count,
				MAX(request_date) most_recent_error_date
				FROM mod_maintenance_page_not_found_request
				GROUP BY page_not_found_IDFK
			) AS entry ON entry.page_not_found_IDFK = pnf.ID
		";
		
		$trPaths = new TableRenderer('mod-maintenance-http-errors-paths', $this->cmsController->getDB(), $sqlString);
		
		$trPaths->setOptions(array(
			'delete' => '?delete={ID}'
		));
		
		$columnMostRecentErrorDate = new Column('most_recent_error_date', 'Newest error', array(new DateColumnDecorator($this->cmsController->getLocaleHandler()->getDateTimeFormat())), true, null, TableRenderer::SORT_DESC);

		$columnPath = new Column('path', 'Path', array(new RewriteColumnDecorator('<a href="' . $this->getBaseURI() . '/http-errors/view/{ID}">{path}</a>')), true);
		$columnPath->setFilter();
		
		$trPaths->setColumns(array(
			$columnPath,
			$columnMostRecentErrorDate,
			new Column('error_count', 'Errors', array(), true),
			new Column('ip_address_count', 'IP addresses', array(), true)
		));
		
		$trPaths->setDefaultOrder($columnMostRecentErrorDate);
		
		return $this->renderModuleContent('mod-maintenance/http-errors-overview', array(
			'siteTitle' => 'HTTP errors',
			'table_paths' => $trPaths->display()
		));
	}
	
	public function viewHttpErrorDetail($params)
	{
		$entryId = $params[0];

		$stmntErrorPath = $this->cmsController->getDB()->prepare("
			SELECT ID, path FROM mod_maintenance_page_not_found WHERE ID = ?
		");
		
		$resErrorPath = $this->cmsController->getDB()->select($stmntErrorPath, array($entryId));
		
		if(count($resErrorPath) === 0)
			throw new HttpException('Entry not found', 404);
		
		$sqlString = "
			SELECT http_error, ip_address, host, request_date, referrer, session_id, user_agent, query_data
			FROM mod_maintenance_page_not_found_request
			WHERE page_not_found_IDFK = ?
		";

		$trRequests = new TableRenderer('mod-maintenance-http-errors-' . $entryId . '-detail', $this->cmsController->getDB(), $sqlString);

		$columnRequestDate = new Column('request_date', 'Request date', array(new DateColumnDecorator($this->cmsController->getLocaleHandler()->getDateTimeFormat())), true, null, TableRenderer::SORT_DESC);
		
		$trRequests->setColumns(array(
			$columnRequestDate,
			new Column('http_error', 'HTTP error', array(), true),
			new Column('ip_address', 'IP address', array(new RewriteColumnDecorator('<a href="http://ipinfo.io/{ip_address}" title="ipinfo.io">{ip_address}</a>')), true),
			new Column('host', 'Host', array(new EmptyValueColumnDecorator('<em>unknown</em>')), true),
			new Column('session_id', 'Session ID', array(), true),
			new Column('user_agent', 'User agent', array(), true),
			new Column('referrer', 'Referrer', array(new EmptyValueColumnDecorator('<em>none</em>')), true),
			new Column('query_data', 'Query data', array(new EmptyValueColumnDecorator('<em>none</em>')))
		));
		
		$trRequests->setDefaultOrder($columnRequestDate);
		
		return $this->renderModuleContent('mod-maintenance/http-error-detail', array(
			'siteTitle' => 'HTTP error details',
			'path' => $resErrorPath[0]->path,
			'table_requests' => $trRequests->display(array($entryId))
		));
	}

	/**
	 * Returns an array with relative link and corresponding label as key-value
	 *
	 * @param CmsController $cmsController
	 *
	 * @return array The navigation entries for this module
	 */
	public static function getNavigationEntries(CmsController $cmsController)
	{
		$localePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR;
		$translator = $cmsController->getTranslator($localePath);
		$translator->bindTextDomain('backend', 'UTF-8');
		
		return array(
			array(
				'target' => '/http-errors',
				'label' => $translator->_d('backend', 'HTTP errors'),
				'scopes' => BackendNavigationInterface::DISPLAY_IN_ADMIN_BAR | BackendNavigationInterface::DISPLAY_IN_MOD_NAV
			)
		);
	}
}

/* EOF */