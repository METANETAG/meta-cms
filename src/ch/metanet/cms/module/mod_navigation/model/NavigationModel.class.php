<?php

namespace ch\metanet\cms\module\mod_navigation\model;

use ch\metanet\cms\model\Model;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class NavigationModel extends Model
{
	public function getNavigationByID($navigationID)
	{
		$stmntNav = $this->db->prepare("SELECT ID, name FROM navigation WHERE ID = ?");
		$resNav = $this->db->select($stmntNav, array($navigationID));

		if(count($resNav) <= 0)
			return null;

		return $resNav[0];
	}

	public function getNavEntryByID($navEntryID)
	{
		$stmntNav = $this->db->prepare("SELECT ID, title, route_IDFK, language_codeFK, external_link FROM navigation_entry WHERE ID = ?");
		$resNav = $this->db->select($stmntNav, array($navEntryID));

		if(count($resNav) <= 0)
			return null;

		return $resNav[0];
	}

	public function getEntriesByNavID($navID, $lang = null, $parentEntryID = null)
	{
		$cond = ' AND parent_navigation_entry_IDFK IS NULL';
		$params = array($navID);

		if($parentEntryID !== null) {
			$cond = ' AND parent_navigation_entry_IDFK = ?';
			$params[] = $parentEntryID;
		}

		if($lang !== null) {
			$cond .= ' AND ne.language_codeFK = ?';
			$params[] = $lang;
		}

		$stmntEntries = $this->db->prepare("
			SELECT navigation_entry_IDFK, parent_navigation_entry_IDFK, default_params, sort, ne.ID, ne.title, ne.route_IDFK,
			r.pattern, nhe.hidden, ne.external_link
			FROM navigation_has_entry nhe
			LEFT JOIN navigation_entry ne ON ne.ID = nhe.navigation_entry_IDFK
			LEFT JOIN route r ON r.ID = ne.route_IDFK
			WHERE nhe.navigation_IDFK = ? " . $cond . "
			ORDER BY nhe.sort
		");

		return $this->db->select($stmntEntries, $params);
	}

	public function getAllEntriesByNavID($navID, $lang = null)
	{
		$cond = '';
		$params = array($navID);

		if($lang !== null) {
			$cond .= ' AND ne.language_codeFK = ?';
			$params[] = $lang;
		}
		
		$stmntEntries = $this->db->prepare("
			SELECT
				navigation_entry_IDFK,
				parent_navigation_entry_IDFK,
				default_params,
				sort,
				ne.ID,
				ne.title,
				ne.route_IDFK,
				ne.external_link,
				nhe.hidden,
				nhe.navigation_IDFK,
				r.pattern
			FROM navigation_has_entry nhe
			LEFT JOIN navigation_entry ne ON ne.ID = nhe.navigation_entry_IDFK
			LEFT JOIN route r ON r.ID = ne.route_IDFK
			WHERE nhe.navigation_IDFK = ?" . $cond . "
			ORDER BY nhe.sort
		");

		return $this->db->select($stmntEntries, $params);
	}

	public function getAllNavigationEntries()
	{
		$stmntEntries = $this->db->prepare("
			SELECT ne.title, ne.ID, r.pattern
			FROM navigation_entry ne
			LEFT JOIN route r ON r.ID = ne.route_IDFK
			ORDER BY ne.title
		");

		return $this->db->select($stmntEntries);
	}

	public function getNavigationEntry($navEntryId)
	{
		$stmntEntries = $this->db->prepare("
			SELECT ne.title, ne.ID, r.pattern
			FROM navigation_entry ne
			LEFT JOIN route r ON r.ID = ne.route_IDFK
			WHERE ne.ID = ?
		");

		$res = $this->db->select($stmntEntries, array($navEntryId));
		
		if(count($res) !== 1)
			return null;

		return $res[0];
	}
	
	public function deleteNavigation($navId)
	{
		$stmntDelEntries = $this->db->prepare("
			DELETE FROM navigation_has_entry WHERE navigation_IDFK = ?
		");
		
		$stmntDelNav = $this->db->prepare("
			DELETE FROM navigation WHERE ID = ?
		");
		
		try {
			$this->db->beginTransaction();
			
			$this->db->delete($stmntDelEntries, array($navId));
			$this->db->delete($stmntDelNav, array($navId));
			
			$this->db->commit();
		} catch(\Exception $e) {
			$this->db->rollBack();
			
			throw $e;
		}
	}
	
	public function deleteNavigationEntry($navEntryId)
	{
		try {
			$this->db->beginTransaction();
			
			$stmntDelAssigns = $this->db->prepare("
				DELETE FROM navigation_has_entry WHERE navigation_entry_IDFK = ?
			");

			$this->db->delete($stmntDelAssigns, array($navEntryId));
			
			$stmntDelEntry = $this->db->prepare("
				DELETE FROM navigation_entry WHERE ID = ?
			");
			
			$this->db->delete($stmntDelEntry, array($navEntryId));
			
			$this->db->commit();
		} catch(\Exception $e) {
			$this->db->rollBack();
			
			throw $e;
		}
	}
}

/* EOF */