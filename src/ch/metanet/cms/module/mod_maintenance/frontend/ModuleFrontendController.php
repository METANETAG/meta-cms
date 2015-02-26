<?php

namespace ch\metanet\cms\module\mod_maintenance\frontend;

use ch\metanet\cms\common\CmsModuleFrontendController;
use ch\metanet\cms\event\PageNotFoundEvent;
use ch\timesplinter\db\DBException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class ModuleFrontendController extends CmsModuleFrontendController implements EventSubscriberInterface
{
	public function logPageNotFound(PageNotFoundEvent $event)
	{
		$httpRequest = $event->getHttpRequest();

		$stmntGetPageNotFound = $this->cmsController->getDB()->prepare("
			SELECT ID FROM mod_maintenance_page_not_found WHERE path = ?
		");

		$stmntInsertPageNotFound = $this->cmsController->getDB()->prepare("
			INSERT IGNORE INTO mod_maintenance_page_not_found
				SET path = ?
		");

		$stmntInsertPageNotFoundRequest = $this->cmsController->getDB()->prepare("
			INSERT INTO mod_maintenance_page_not_found_request
				SET page_not_found_IDFK = ?, http_error = 404, ip_address = ?, host = ?, query_data = ?, user_agent = ?, referrer = ?, session_id = ?, request_date = NOW()
		");

		try {
			$this->cmsController->getDB()->beginTransaction();

			$pageNotFoundEntryId = (int)$this->cmsController->getDB()->insert($stmntInsertPageNotFound, array(
				$httpRequest->getPath()
			));

			if($pageNotFoundEntryId === 0) {
				$resPageNotFound = $this->cmsController->getDB()->select($stmntGetPageNotFound, array($httpRequest->getPath()));

				if(count($resPageNotFound) === 1)
					$pageNotFoundEntryId = (int)$resPageNotFound[0]->ID;
			}

			$ipAddress = $httpRequest->getRemoteAddress();

			if(($hostName = gethostbyaddr($ipAddress)) === $ipAddress) {
				$hostName = null;
			}

			$this->cmsController->getDB()->insert($stmntInsertPageNotFoundRequest, array(
				$pageNotFoundEntryId,
				$ipAddress,
				$hostName,
				strlen($httpRequest->getQuery()) > 0?$httpRequest->getQuery():null,
				$httpRequest->getUserAgent(),
				$httpRequest->getReferrer(),
				session_id()
			));

			$this->cmsController->getDB()->commit();
		} catch(DBException $e) {
			$this->cmsController->getDB()->rollBack();

			throw $e;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents()
	{
		return array(
			'cms.page_not_found' => array('logPageNotFound')
		);
	}
}

/* EOF */