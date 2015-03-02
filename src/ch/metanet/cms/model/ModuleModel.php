<?php

namespace ch\metanet\cms\model;

use ch\metanet\cms\common\CmsPage;
use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CMSException;
use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\module\layout\LayoutElement;
use ch\timesplinter\common\JsonUtils;
use ch\timesplinter\core\FrameworkLoggerFactory;
use timesplinter\tsfw\db\DB;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class ModuleModel extends Model
{
	protected  $stmntMod;
	protected $logger;

	public function __construct(DB $db)
	{
		parent::__construct($db);

		$this->logger = FrameworkLoggerFactory::getLogger($this);

		$this->stmntMod = $this->db->prepare("
			SELECT mi.ID, mi.page_IDFK, ma.class, ma.name, ma.ID modID, parent_mod_IDFK, IF(ih.element_instance_IDFK IS NULL, 0, 1) hidden
			FROM cms_element_instance mi
			LEFT JOIN cms_element_available ma ON ma.ID = mi.mod_IDFK
			LEFT JOIN cms_element_instance_hidden ih ON ih.element_instance_IDFK = mi.ID AND ih.page_IDFK = ?
			WHERE mi.ID = ?
		");
	}

	/**
	 * Returns a CMSModule instance by it's ID
	 * 
	 * @param int $moduleID
	 * @param \ch\metanet\cms\common\CmsPage $cmsPage
	 * @param bool $combineSettings
	 * @param bool $loadSubElements
	 * 
	 * @throws \ch\metanet\cms\common\CMSException
	 * 
	 * @return CmsElement
	 */
	public function getElementInstanceByID($moduleID, CmsPage $cmsPage, $combineSettings = true, $loadSubElements = true)
	{
		$resMod = $this->db->select($this->stmntMod, array($cmsPage->getID(), $moduleID));

		if(count($resMod) <= 0)
			throw new CMSException('Module with ID ' . $moduleID . ' not found');

		$modInfo = $resMod[0];

		/** @var $elementInstance CmsElement */
		$elementInstance = new $modInfo->class($moduleID, $modInfo->page_IDFK/*$cmsPage->getID()*/);
		$elementInstance->setParentElementID($modInfo->parent_mod_IDFK);
		$elementInstance->setHidden($modInfo->hidden == 1);

		if($elementInstance instanceof CmsElementSettingsLoadable) {
			/** @var CmsElementSettingsLoadable $elementInstance */
			$settings = $elementInstance->getSettingsForElements($this->db, array($moduleID), PageModel::getPageIdsRecursive($cmsPage));

			// Combine the settings
			if(isset($settings[$moduleID])) {
				$settingsEntry = null;

				if($combineSettings === true) {
					$settingsEntry = $this->combineSettings($settings[$moduleID]);
				} elseif(isset($settings[$moduleID][$cmsPage->getID()]) === true) {
					$settingsEntry = $settings[$moduleID][$cmsPage->getID()];
				}

				if($settingsEntry !== null)
					$elementInstance->setSettings($settingsEntry);
			}

			$elementInstance->setSettingsSelf(isset($settings[$moduleID][$cmsPage->getID()]));
		}

		if($elementInstance instanceof LayoutElement && $loadSubElements === true)
			$elementInstance->setElements($this->getChildElementInstances($elementInstance->getID(), $cmsPage/*, $modInstance*/));

		return $elementInstance;
	}

	public function getChildElementInstances($parentElementID, CmsPage $cmsPage/*, CmsElement $parentElement*/)
	{
		$elementInstances = array();

		$pageIds = PageModel::getPageIdsRecursive($cmsPage);

		$stmntChildElements = $this->db->prepare("
			SELECT mi.ID, ma.class, ma.name, ma.ID modID
			FROM cms_element_instance mi
			LEFT JOIN cms_element_available ma ON ma.ID = mi.mod_IDFK
			WHERE mi.parent_mod_IDFK = ?
			AND (page_IDFK IN (" . implode(',', array_fill(0, count($pageIds), '?')) . ") OR page_IDFK IS NULL)
			AND ma.active = 1
		");

		$resChildElements = $this->db->select($stmntChildElements, array_merge(array($parentElementID), $pageIds));

		foreach($resChildElements as $elementInfo) {
			/** @var CmsElement $modInstance */
			$modInstance = $this->getElementInstanceByID($elementInfo->ID, $cmsPage/*, $parentElement*/);

			$elementInstances[$elementInfo->ID] = $modInstance;
		}

		return $elementInstances;
	}

	public function getModulesFromCacheByPage(CmsPage $cmsPage) {
		/*$stmntCache = $this->db->prepare("SELECT cached_elements, cache_time FROM page_cache WHERE ID = ?");

		$resCache = $this->db->select($stmntCache, array($cmsPage->getID()));

		if(count($resCache) <= 0)
			return null;

		if($resCache[0]->cache_time < $cmsPage->getLastModified())
			return null;

		if(($elements = unserialize($resCache[0]->cached_elements)) === false)
			return null;

		return $elements;*/
		return null;
	}

	/**
	 * Reloads settings for a given element
	 * @param CmsElement $cmsElement The element which settings should be reloaded
	 * @param CmsPage $cmsPage The CmsPage in which context the settings for the element should be loaded
	 */
	public function reloadSettings(CmsElement $cmsElement, CmsPage $cmsPage)
	{
		if(($cmsElement instanceof CmsElementSettingsLoadable) === false)
			return;
		
		/** @var CmsElementSettingsLoadable $cmsElement */

		$cmsElement->resetSettingsFound();

		/** @var CmsElementSettingsLoadable $cmsElement */
		$settings = $cmsElement->getSettingsForElements($this->db, array($cmsElement->getID()), PageModel::getPageIdsRecursive($cmsPage));

		foreach($settings as $settingEntries) {
			/** @var CmsElement */
			$settingsEntry = $this->combineSettings($settingEntries);
			$cmsElement->setSettings($settingsEntry);
		}
	}

	/**
	 * @param bool $activeOnly
	 *
	 * @return \stdClass[]
	 * @throws \ch\timesplinter\core\JSONException
	 */
	public function getAllModules($activeOnly = true) {
		$stmntMods = $this->db->prepare("
			SELECT *
			FROM cms_mod_available
			WHERE active = ?
			ORDER BY name
		");

		$resMods = $this->db->select($stmntMods, array(
			(int)$activeOnly)
		);

		foreach($resMods as $mod) {
			$mod->manifest_content = JsonUtils::decode($mod->manifest_content);
		}

		return $resMods;
	}

	/**
	 * @param $name
	 * @param bool $activeOnly
	 *
	 * @return \stdClass|null
	 */
	public function getModuleByName($name, $activeOnly = true)
	{
		return $this->getModuleByColumn('name', $name, $activeOnly);
	}
	
	public function getModuleById($id, $activeOnly = true)
	{
		return $this->getModuleByColumn('ID', $id, $activeOnly);
	}

	/**
	 * @param $column
	 * @param $value
	 * @param $activeOnly
	 *
	 * @return \stdClass|null
	 */
	protected function getModuleByColumn($column, $value, $activeOnly)
	{
		$stmntMod = $this->db->prepare("
			SELECT ID, name, active, path, frontendcontroller, backendcontroller, manifest_content
			FROM cms_mod_available
			WHERE active = ?
			AND " . $column . " = ?
		");

		$resMods = $this->db->select($stmntMod, array(
			(int)$activeOnly,
			$value
		));

		if(count($resMods) <= 0)
			return null;

		try {
			$resMods[0]->manifest_content = JsonUtils::decode($resMods[0]->manifest_content);
		} catch(\Exception $e) {
			$resMods[0]->manifest_content = null;
		}

		return $resMods[0];
	}

	public function getModulesWithFrontendController()
	{
		$stmntMods = $this->db->prepare("
			SELECT *
			FROM cms_mod_available
			WHERE active = 1
			AND frontendcontroller IS NOT NULL
			ORDER BY name
		");

		$resMods = $this->db->select($stmntMods);

		foreach($resMods as $mod) {
			$mod->manifest_content = JsonUtils::decode($mod->manifest_content);
		}

		return $resMods;
	}

	/**
	 * This functions combines settings records from multiple pages for the same module
	 * If a value is not NULL in the next settings entry, it will be overwritten. Till no settings entries are left.
	 * @param $settingEntries
	 * @return mixed
	 */
	protected function combineSettings($settingEntries)
	{
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

	protected function getChildElements(CmsElement $element, \ArrayObject $elementInstances)
	{
		$childElements = array();

		foreach($elementInstances as $m) {
			/** @var CmsElement $m */
			if($m->getParentElementID() != $element->getID())
				continue;

			if($m instanceof LayoutElement)
				$m->setElements($this->getChildElements($m, $elementInstances));

			$childElements[$m->getID()] =  $m;
		}

		return $childElements;
	}
}

/* EOF */