<?php

namespace ch\metanet\cms\model;

use ch\metanet\cms\common\CMSException;
use timesplinter\tsfw\db\DB;
use timesplinter\tsfw\db\DBException;

/**
 * @author Pascal Muenst
 * @copyright Copyright (c) 2013, METANET AG
 */
class LoginModel extends Model
{
	public function __construct(DB $db)
	{
		parent::__construct($db);
	}

	public function getAllLogins($orderby = 'email', $order = 'ASC') {
		$stmntGetLogin = $this->db->prepare("
			SELECT ID, username, email, lastlogin, wronglogins, confirmed, token, tokentime, active, registered
			FROM login
			WHERE ID != 0
			ORDER BY " . $orderby . " " . $order . "
		");

		return $this->db->select($stmntGetLogin);
	}

	/**
	 * @param $loginID
	 *
	 * @return Login|null
	 */
	public function getLoginByID($loginID)
	{
		$stmntGetLogin = $this->db->prepare("
			SELECT ID, username, email, lastlogin, wronglogins, confirmed, token, tokentime, active, registered, salt, password
			FROM login
			WHERE ID = ?
		");

		$getLogin = $this->db->select($stmntGetLogin, array($loginID));

		if(count($getLogin) <= 0)
			return null;

		return $this->createLoginFromData($getLogin[0]);
	}

	public function getRightGroupsByLogin($userID)
	{
		$stmntRgs = $this->db->prepare("
			SELECT rg.ID, rg.groupname, rg.groupkey, rg.root
			FROM login_has_rightgroup lhr
			LEFT JOIN rightgroup rg ON rg.ID = lhr.rightgroupIDFK
			WHERE lhr.loginIDFK = ?
		");

		return $this->db->select($stmntRgs, array($userID));
	}

	public function hasLoginRightGroup($loginID, $rightgroupID)
	{
		$stmntRgLogin = $this->db->prepare("
			SELECT rightgroupIDFK
			FROM login_has_rightgroup lhr
			WHERE loginIDFK = ? AND rightgroupIDFK = ?
		");

		$getLogin = $this->db->select($stmntRgLogin, array($loginID, $rightgroupID));

		return (count($getLogin) > 0);
	}

	public function removeRightgroupFromLogin($rightgroupID, $userID)
	{
		$stmntDelete = $this->db->prepare("
			DELETE FROM login_has_rightgroup WHERE rightgroupIDFK = ? AND loginIDFK = ?
		");

		$this->db->delete($stmntDelete, array($rightgroupID, $userID));
	}
	
	public function storeLogin(Login $login)
	{
		$stmntSingup = $this->db->prepare("
			INSERT INTO login SET
				username = ?,
				email = ?,
				password = ?,
				registeredby = ?,
				salt = ?,
				confirmed = NULL,
				active = ?,
				lastlogin = NULL,
				wronglogins = 0
			ON DUPLICATE KEY UPDATE
				username = ?,
				email = ?,
				active = ?,
				password = ?,
				salt = ?
		");

		$userID = $this->db->insert($stmntSingup, array(
			// Insert
			$login->getUsername(),
			$login->getEmail(),
			$login->getPassword(),
			$login->getRegisteredBy(),
			$login->getSalt(),
			(int)$login->getActive(),

			// Update
			$login->getUsername(),
			$login->getEmail(),
			(int)$login->getActive(),
			$login->getPassword(),
			$login->getSalt()
		));

		return ($login->getID() === null) ? $userID : $login->getID();
	}
	
	public function deleteLogin($accountID)
	{
		try {
			$this->db->beginTransaction();

			$stmntCheckAdmin = $this->db->prepare("
				SELECT COUNT(*) count_admins
				FROM login_has_rightgroup lhr
				JOIN rightgroup r ON r.ID = lhr.rightgroupIDFK
				JOIN login l ON l.ID = lhr.loginIDFK
				WHERE r.root = 1 AND lhr.loginIDFK != ?
			");

			$resCheckAdmin = $this->db->select($stmntCheckAdmin, array($accountID));

			if($resCheckAdmin[0]->count_admins <= 0)
				throw new CMSException('You\'re the last admin, you can\'t delete yourself!');

			$stmntDelLoginHasRg = $this->db->prepare("
				DELETE FROM login_has_rightgroup WHERE loginIDFK = ?
			");
			$this->db->delete($stmntDelLoginHasRg, array($accountID));

			$stmntDelAcc = $this->db->prepare("DELETE FROM login WHERE ID = ?");
			$rowsAffected = $this->db->delete($stmntDelAcc, array($accountID));

			$this->db->commit();
			
			return ($rowsAffected === 1);
		} catch(DBException $e) {
			$this->db->rollBack();
			
			throw $e;
		}
	}
	
	protected function createLoginFromData(\stdClass $loginData)
	{
		$login = new Login();
		
		$login->setID($loginData->ID);
		$login->setActive($loginData->active == 1);
		$login->setConfirmed($loginData->confirmed !== null ? new \DateTime($loginData->confirmed) : null);
		$login->setEmail($loginData->email);
		$login->setLastLogin($loginData->lastlogin !== null ? new \DateTime($loginData->lastlogin) : null);
		$login->setRegistered($loginData->registered !== null ? new \DateTime($loginData->registered) : null);
		$login->setToken($loginData->token);
		$login->setTokenTime($loginData->tokentime);
		$login->setUsername($loginData->username);
		$login->setWrongLogins($loginData->wronglogins);
		$login->setSalt($loginData->salt);
		$login->setPassword($loginData->password);
		
		return $login;
	}
}