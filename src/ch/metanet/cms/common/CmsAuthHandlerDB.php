<?php

namespace ch\metanet\cms\common;

use ch\timesplinter\auth\AuthHandlerDB;
use ch\timesplinter\core\SessionHandler;
use timesplinter\tsfw\db\DB;

/**
 * Authenticates users and provides information about their rights
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class CmsAuthHandlerDB extends AuthHandlerDB
{
	protected $cmsRights;

	/**
	 * @param DB $db
	 * @param SessionHandler $sessionHandler
	 * @param array $settings
	 */
	public function __construct(DB $db, SessionHandler $sessionHandler, array $settings = array())
	{
		parent::__construct($db, $sessionHandler, $settings);

		$this->cmsRights = array();
	}

	/**
	 * @param string $username
	 * @param string $password
	 * @param null $callbackOnSuccess
	 *
	 * @return bool
	 */
	public function checkLogin($username, $password, $callbackOnSuccess = null)
	{
		if(parent::checkLogin($username, $password, $callbackOnSuccess) === false)
			return false;

		$this->cmsRights = $this->loadCmsRights();

		return true;
	}

	/**
	 * 
	 */
	protected function loadUserPopo()
	{
		parent::loadUserPopo();

		$this->cmsRights = $this->loadCmsRights();
	}

	/**
	 * @return string[]
	 */
	protected function loadCmsRights()
	{
		$rgs = array();

		foreach($this->loginPopo->rightgroups as $rg) {
			$rgs[] = $rg->rightgroupIDFK;
		}

		$cmsRights = array();

		if(count($rgs) === 0)
			return $cmsRights;

		// Lade CMS rights
		$stmntRights = $this->db->prepare("
			SELECT cms_right FROM cms_rightgroup_has_right
			WHERE date_from <= NOW() AND (date_to IS NULL OR date_to >= NOW())
			AND rightgroup_IDFK IN (" . DB::createInQuery($rgs) . ")
		");

		$resRights = $this->db->select($stmntRights, $rgs);


		foreach($resRights as $r) {
			$cmsRights[] = $r->cms_right;
		}

		return $cmsRights;
	}

	/**
	 * @param string $right
	 *
	 * @return bool
	 */
	public function hasCmsRight($right)
	{
		if($this->loggedIn === false)
			return false;

		$this->cmsRights = $this->loadCmsRights();

		return ($this->hasRootAccess() || in_array($right, $this->cmsRights));
	}

	/**
	 * @return string[]
	 */
	public function getCmsRights()
	{
		return $this->cmsRights;
	}
}

/* EOF */