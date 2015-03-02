<?php

namespace ch\metanet\cms\module\mod_core;

use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\common\CmsView;
use ch\metanet\cms\controller\common\FrontendController;
use timesplinter\tsfw\db\DB;
use \stdClass;

/**
 * A very simple module which just prints out a given text. Nothing more nothing less than this.
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class IframeElement extends CmsElementSettingsLoadable {
	public function __construct($ID, $pageID) {
		parent::__construct($ID, $pageID, 'element_iframe');
	}

	public function render(FrontendController $frontendController, CmsView $view) {
		if($this->settingsFound === false)
			return $this->renderEditable($frontendController, 'Please specify an url for this iframe');

		//var_dump($urlAddition); exit;
		$html = '<div class="iframe-wrap">
			<iframe src="' . $this->settings->url . '" frameborder="0" id="iframe" class="iframe"></iframe>
		</div>';
		//$html = '<iframe src="' . $this->settings->url . '" frameborder="0" id="iframe"></iframe>';

		return $this->renderEditable($frontendController, $html);
	}

	/**
	 * Sets module default settings if no settings in the DB exists, class this method
	 * @return mixed
	 */
	public function setDefaultSettings() {
		$this->settings = new stdClass();
		$this->settings->url = null;
	}

	public function update(DB $db, stdClass $newSettings, $pageID) {
		$stmntUpdateSettings = $db->prepare("
			INSERT INTO " . $this->identifier . " SET
				element_instance_IDFK = ?, page_IDFK = ?, url = ?
			ON DUPLICATE KEY UPDATE
				url = ?
		");

		$db->update($stmntUpdateSettings, array(
			// INSERT
			$this->ID,
			$pageID,
			$newSettings->url,

			// UPDATE
			$newSettings->url
		));
	}

	/**
	 * @param DB $db The database object
	 * @param array $modIDs The IDs of the modules for which the settings should be loaded
	 * @param array $pageIDs The nested page IDs context
	 * @return array The settings found sorted by module IDs
	 */
	public static function getSettingsForElements(DB $db, array $modIDs, array $pageIDs) {
		$params = array_merge($modIDs, $pageIDs, $pageIDs);

		$stmntSettings = $db->prepare("
			SELECT element_instance_IDFK, page_IDFK, url
			FROM element_iframe
			WHERE element_instance_IDFK IN (" . DB::createInQuery($modIDs) . ")
			AND page_IDFK IN (" . DB::createInQuery($pageIDs) . ")
			ORDER BY FIELD(page_IDFK, " . DB::createInQuery($pageIDs) . ")
		");

		$resSettings = $db->select($stmntSettings, $params);

		$settingsArr = array();

		foreach($resSettings as $res) {
			$settingsArr[$res->element_instance_IDFK][$res->page_IDFK] = $res;
		}

		return $settingsArr;
	}
}

/* EOF */