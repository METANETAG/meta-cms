<?php


namespace ch\metanet\cms\model;

use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\common\CmsPage;
use ch\metanet\cms\module\layout\LayoutElement;
use timesplinter\tsfw\db\DB;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class ElementModel extends Model {
	/**
	 * @param int $modTypeID
	 * @param CmsElement|null $modParent The parent module
	 * @param int $pageID The pageID where the module should be
	 * @param int $creatorID The creator of the module
	 * @return CmsElement|null The CmsModule instance or null if no valid module ID passed
	 */
	public function createElement($modTypeID, $modParent, $pageID, $creatorID) {
		$stmntMod = $this->db->prepare("SELECT class FROM cms_element_available WHERE ID = ? AND active = '1'");
		$resMod = $this->db->select($stmntMod, array($modTypeID));

		if(count($resMod) <= 0)
			return null;

		$modClass = $resMod[0]->class;

		/** @var CmsElement $modInstance */
		$modInstance = new $modClass(null, $pageID);
		if($modParent !== null) {
			$modInstance->setParentElementID($modParent);
			$modInstance->setParentElement($modParent);
		}

		$modInstance->setCreatorID($creatorID);

		$modInstance->save($this->db);

		return $modInstance;
	}

	/**
	 * Returns an element tree combined of the given page IDs starting at the optional parentElement
	 * @param CmsPage $cmsPage All the page IDs in correct inheriting order
	 * @param bool $useCache
	 * @return \ArrayObject
	 */
	public function getElementTree(CmsPage $cmsPage, $useCache = false) {
		return $this->generateElementTree($cmsPage, $useCache);
	}

	/**
	 * @param CmsPage $cmsPage
	 * @param bool $useCache
	 * @return \ArrayObject The element tree for the current page
	 */
	private function generateElementTree(CmsPage $cmsPage, $useCache = false) {
		// TODO check if any page inheriting from has changed
		$pageIDs = PageModel::getPageIdsRecursive($cmsPage);

		/*if($useCache === true) {
			$stmntFetchCache = $this->db->prepare("
				SELECT pc.ID, pc.cached_elements, pc.cache_time
				FROM page_cache pc
				LEFT JOIN page p ON p.ID = pc.ID
				WHERE pc.cache_time >= p.last_modified
				AND pc.ID IN(" . DB::createInQuery($pageIDs) . ")
			");

			$resFetchCache = $this->db->select($stmntFetchCache,
				$pageIDs
			);

			if(count($resFetchCache) === count($pageIDs)) {
				foreach($resFetchCache as $fc) {
					if($fc->ID != $cmsPage->getID())
						continue;

					return unserialize($fc->cached_elements);
				}
			}
		}*/

		/*$stmntElements = $this->db->prepare("
			SELECT ei.ID, ei.mod_IDFK element_ID, ei.page_IDFK page_ID, parent_mod_IDFK parent_element_ID, ea.class, ea.name, ei.revision,
			IF(ih.element_instance_IDFK IS NULL, 0, 1) hidden
			FROM cms_element_instance ei
			LEFT JOIN cms_element_available ea ON ea.ID = ei.mod_IDFK
			LEFT JOIN cms_element_instance_hidden ih ON ih.element_instance_IDFK = ei.ID AND ih.page_IDFK = ?
			WHERE ei.page_IDFK IN (" . DB::createInQuery($pageIDs) . ")
			AND ea.active = 1
		");

		$resElements = $this->db->select($stmntElements,
			array_merge(array($cmsPage->getID()), $pageIDs)
		);*/

		$stmntElements = $this->db->prepare("
			SELECT ei.ID, ei.mod_IDFK element_ID, ei.page_IDFK page_ID, parent_mod_IDFK parent_element_ID, ea.class, ea.name, ei.revision,
			IF(ih.element_instance_IDFK IS NULL, 0, 1) hidden
			FROM cms_element_instance ei
			LEFT JOIN cms_element_available ea ON ea.ID = ei.mod_IDFK
			LEFT JOIN cms_element_instance_hidden ih ON ih.element_instance_IDFK = ei.ID AND ih.page_IDFK IN (" . DB::createInQuery($pageIDs) . ")
			WHERE ei.page_IDFK IN (" . DB::createInQuery($pageIDs) . ")
			AND ea.active = 1
		");

		$resElements = $this->db->select($stmntElements,
			array_merge($pageIDs, $pageIDs)
		);

		// Settings und co
		$elementTypes = array();
		$elementInstances = array();

		foreach($resElements as $e) {
			/** @var CmsElement $elementInstance */
			$elementInstance = new $e->class($e->ID, /*$cmsPage->getID()*/$e->page_ID, $e->name);

			if($e->revision !== null)
				$elementInstance->setRevision($e->revision);

			$elementInstance->setHidden($e->hidden == 1);

			$elementInstances[$e->ID] = $elementInstance;
			$elementTypes[$e->class][] = $e->ID;
		}

		// Load settings for modules
		foreach($elementTypes as $class => $modIDs) {
			$refClass = new \ReflectionClass($class);

			if($refClass->isSubclassOf('ch\metanet\cms\common\CmsElementSettingsLoadable') === false)
				continue;

			/** @var CmsElementSettingsLoadable $class */
			$settings = $class::getSettingsForElements($this->db, $modIDs, $pageIDs);

			foreach($settings as $key => $settingEntries) {
				$settingsEntry = $this->combineSettings($settingEntries);
				$elementInstances[$key]->setSettings($settingsEntry);
			}
		}
		
		$cachedTree = new \ArrayObject($this->createTree($resElements, $elementInstances));

		if($useCache === true) {
			$cachedTreeSerialized = serialize($cachedTree);

			try {
				$stmntCacheElements = $this->db->prepare("
					INSERT INTO page_cache SET
						ID = ?, cached_elements = ?, cache_time = NOW()
					ON DUPLICATE KEY UPDATE
						cached_elements = ?, cache_time = NOW()
				");

				$this->db->insert($stmntCacheElements, array(
					// INSERT
					$cmsPage->getID(),
					$cachedTreeSerialized,

					// UPDATE
					$cachedTreeSerialized
				));
			} catch(\Exception $e ){
				// Cache could not be saved
			}
		}

		return $cachedTree;
	}

	/**
	 * @param array $elements
	 * @param array $elementInstances
	 * @param CmsElement|null $parentElement
	 * @return array The elements for the current parent element
	 */
	private function createTree(array $elements, array $elementInstances, $parentElement = null) {
		$elementTree = array();

		foreach($elements as $e) {
			// Not interesting yet
			if(($parentElement == null && $e->parent_element_ID != null) || ($parentElement != null && $e->parent_element_ID != $parentElement->getID()))
				continue;

			/** @var CmsElement $elementInstance */
			$elementInstance = $elementInstances[$e->ID];

			if($parentElement !== null)
				$elementInstance->setParentElement($parentElement);

			$elementInstance->setParentElementID(($parentElement != null)?$parentElement->getID():null);

			if($elementInstance instanceof LayoutElement) {
				/** @var LayoutElement $elementInstance */
				$subElementTree = $this->createTree($elements, $elementInstances, $elementInstance);

				$elementInstance->setElements($subElementTree);
			}

			$elementTree[$e->ID] = $elementInstance;
		}

		return $elementTree;
	}

	/**
	 * @param \ArrayObject $elementTree All the elements available for look up
	 * @param int $elementID The ID of the element instance to return
	 * @return CmsElement|null The element instance
	 */
	public function findElementIDInTree(\ArrayObject $elementTree, $elementID) {
		foreach($elementTree as $k => $e) {
			if($k == $elementID)
				return $e;

			if($e instanceof LayoutElement) {
				$resFind = $this->findElementIDInTree(new \ArrayObject($e->getElements()), $elementID);

				if($resFind !== null) return $resFind;
			}
		}

		return null;
	}

	public function getElementsByModuleName($moduleName) {
		$stmntElements = $this->db->prepare("
			SELECT ea.ID, ea.name
			FROM cms_element_available ea
			LEFT JOIN cms_mod_available ma ON ma.ID = ea.mod_IDFK
			WHERE ma.name = ?
		");

		return $this->db->select($stmntElements, array($moduleName));
	}

	/**
	 * This functions combines settings records from multiple pages for the same module
	 * If a value is not NULL in the next settings entry, it will be overwritten. Till no settings entries are left.
	 * @param $settingEntries
	 * @return mixed
	 */
	private function combineSettings($settingEntries) {
		//var_dump($settingEntries);
		$settingsEntriesReversed = array_reverse($settingEntries);
		$oneEntry = array_shift($settingsEntriesReversed);

		foreach($settingsEntriesReversed as $entry) {
			foreach($entry as $key => $val) {
				if($val === null)
					continue;

				$oneEntry->$key = $val;
			}
		}

		return $oneEntry;
	}
}

/* EOF */