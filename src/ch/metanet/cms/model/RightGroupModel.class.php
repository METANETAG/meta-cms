<?php

namespace ch\metanet\cms\model;

use ch\timesplinter\db\DB;
use \DateTime;

/**
 * Class RightGroupModel
 * @package ch\metanet\cms\model
 */
class RightGroupModel extends Model
{
	public function __construct(DB $db)
	{
		parent::__construct($db);
	}

	/**
	 * @return RightGroup[]
	 */
	public function getRightGroups()
	{
		$rightGroups = array();
		
		$stmntGroups = $this->db->prepare("
			SELECT ID, groupkey, groupname, root
			FROM rightgroup
			ORDER BY groupname
		");

		$stmntCmsRights = $this->db->prepare("
			SELECT cms_right
			FROM cms_rightgroup_has_right
			WHERE rightgroup_IDFK = ?
			AND date_from <= NOW()
			AND (date_to IS NULL OR date_to >= NOW())
		");
		
		$resRG =  $this->db->select($stmntGroups);
		
		foreach($resRG as $rgData) {
			$resCmsRights = $this->db->select($stmntCmsRights, array($rgData->ID));

			$cmsRights = array();

			foreach($resCmsRights as $r) {
				$cmsRights[] = $r->cms_right;
			}

			$rgData->rights = $cmsRights;

			$rightGroups[] = $this->createRightGroupFromData($rgData);
		}
		
		return $rightGroups;
	}

	public function addRightGroupToUser($rightGroupID, $userID, DateTime $fromDate, DateTime $toDate = null)
	{
		$stmntRgUser = $this->db->prepare("
			INSERT INTO login_has_rightgroup SET rightgroupIDFK = ?, loginIDFK = ?, datefrom = ?, dateto = ?
		");

		$this->db->insert($stmntRgUser, array(
			$rightGroupID,
			$userID,
			$fromDate->format('Y-m-d H:i:s'),
			($toDate !== null)?$toDate->format('Y-m-d H:i:s'):null
		));
	}

	/**
	 * @param $rightGroupID
	 *
	 * @return RightGroup|null
	 */
	public function getRightGroupByID($rightGroupID)
	{
		$stmntGroups = $this->db->prepare("
			SELECT ID, groupkey, groupname, root
			FROM rightgroup
			WHERE ID = ?
		");

		$resRG =  $this->db->select($stmntGroups, array($rightGroupID));

		if(count($resRG) <= 0)
			return null;

		$rightGroup = $resRG[0];

		$stmntCmsRights = $this->db->prepare("
			SELECT cms_right
			FROM cms_rightgroup_has_right
			WHERE rightgroup_IDFK = ?
			AND date_from <= NOW()
			AND (date_to IS NULL OR date_to >= NOW())
		");

		$resCmsRights = $this->db->select($stmntCmsRights, array($rightGroup->ID));

		$cmsRights = array();

		foreach($resCmsRights as $r) {
			$cmsRights[] = $r->cms_right;
		}

		$rightGroup->rights = $cmsRights;
		
		return $this->createRightGroupFromData($rightGroup);
	}

	public function getRightGroupByUser($userID)
	{
		$stmntGroups = $this->db->prepare("
			SELECT ID, groupkey, groupname, root
			FROM login_has_rightgroup lhr
			LEFT JOIN rightgroup r ON r.ID = lhr.rightgroupIDFK
			WHERE lhr.loginIDFK = ?
			ORDER BY groupname
		");

		return  $this->db->select($stmntGroups, array($userID));
	}
	
	public function storeRightGroup(RightGroup $rightGroup)
	{
		$stmntMutate = $this->db->prepare("
			INSERT INTO rightgroup SET
				ID = ?, groupname = ?, groupkey = ?, root = ?
			ON DUPLICATE KEY UPDATE
				groupname = ?, groupkey = ?, root = ?
		");

		$newRgID = $this->db->insert($stmntMutate, array(
			$rightGroup->getID(),
			$rightGroup->getGroupName(),
			$rightGroup->getGroupKey(),
			(int)$rightGroup->isRoot(),

			$rightGroup->getGroupName(),
			$rightGroup->getGroupKey(),
			(int)$rightGroup->isRoot()
		));

		$rgID = ($rightGroup->getID() !== null) ? $rightGroup->getID() : $newRgID;

		$removeRights = $this->db->prepare("DELETE FROM cms_rightgroup_has_right WHERE rightgroup_IDFK = ?");
		$this->db->delete($removeRights, array($rgID));

		$stmntInsertRight = $this->db->prepare("
			INSERT INTO cms_rightgroup_has_right
			SET rightgroup_IDFK = ?, cms_right = ?, date_from = NOW()
		");

		foreach($rightGroup->getRights() as $r) {
			$this->db->insert($stmntInsertRight, array(
				$rgID, 
				$r
			));
		}
	}

	public function deleteRightGroup($rightGroupID)
	{
		try {
			$this->db->beginTransaction();

			$stmntDelLoginHasRg = $this->db->prepare("
				DELETE FROM login_has_rightgroup WHERE rightgroupIDFK = ?
			");
			$this->db->delete($stmntDelLoginHasRg, array($rightGroupID));

			$stmntDelRg = $this->db->prepare("DELETE FROM rightgroup WHERE ID = ?");
			$rowsAffected = $this->db->delete($stmntDelRg, array($rightGroupID));

			$this->db->commit();
			
			return ($rowsAffected === 1);
		} catch(\Exception $e) {
			$this->db->rollBack();

			throw $e;
		}
	}
	
	protected function createRightGroupFromData(\stdClass $rightGroupData)
	{
		$rightGroupObj = new RightGroup();
		
		$rightGroupObj->setID($rightGroupData->ID);
		$rightGroupObj->setGroupKey($rightGroupData->groupkey);
		$rightGroupObj->setGroupName($rightGroupData->groupname);
		$rightGroupObj->setRoot((bool)$rightGroupData->root);
		$rightGroupObj->setRights($rightGroupData->rights);
		
		return $rightGroupObj;
	}
}