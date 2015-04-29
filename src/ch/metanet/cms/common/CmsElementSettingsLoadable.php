<?php

namespace ch\metanet\cms\common;

use ch\metanet\cms\controller\common\BackendController;
use timesplinter\tsfw\common\JsonUtils;
use timesplinter\tsfw\common\StringUtils;
use timesplinter\tsfw\db\DB;

/**
 * Enables a CMS element to have element specific settings and load and store them on a per page basis.
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
abstract class CmsElementSettingsLoadable extends CmsElement
{
	protected $settings;
	protected $settingsSelf;
	protected $settingsFound;

	public function __construct($ID, $pageID, $identifier = null)
	{
		parent::__construct($ID, $pageID, $identifier);

		$this->settingsFound = false;
		$this->settingsSelf = false;

		$this->setDefaultSettings();
		
		$this->tplVars->offsetSet('settings', $this->settings);
	}

	/**
	 * @param DB $db The database object
	 * @param array $modIDs The IDs of the modules for which the settings should be loaded
	 * @param array $pageIDs The nested page IDs context
	 * @return array The settings found sorted by module IDs
	 */
	public static function getSettingsForElements(DB $db, array $modIDs, array $pageIDs)
	{
		return array();
	}

	/**
	 * @param DB $db
	 */
	public function remove(DB $db)
	{
		$stmntRemoveElementSettings = $db->prepare("DELETE FROM " . $this->identifier . " WHERE element_instance_IDFK = ?");
		$db->delete($stmntRemoveElementSettings, array($this->ID));

		parent::remove($db);
	}

	/**
	 * Sets module default settings if no settings in the DB exists
	 */
	public function setDefaultSettings()
	{
		$this->settings = new \stdClass();
	}

	/**
	 * Stores the new settings for this element
	 * 
	 * @param DB $db
	 * @param \stdClass $newSettings
	 * @param int $pageID
	 */
	public abstract function update(DB $db, \stdClass $newSettings, $pageID);

	/**
	 * Deletes the setting of itself for the current page
	 * 
	 * @param DB $db
	 * @param int $pageID
	 */
	public function deleteSettingsSelf(DB $db, $pageID)
	{
		$stmntDelete = $db->prepare("DELETE FROM " . $this->identifier . " WHERE element_instance_IDFK = ? AND page_IDFK = ?");

		$db->delete($stmntDelete, array($this->ID, $pageID));
	}

	/**
	 * Generates the HTML config form for the element
	 * 
	 * @param BackendController $backendController
	 * @param int $pageID The current pages ID
	 *
	 * @return string The config box as HTML
	 * @throws CMSException
	 * @throws \Exception
	 */
	public function generateConfigBox(BackendController $backendController, $pageID)
	{
		$lang = $backendController->getLocaleHandler()->getLanguage();

		$configFilePath = $backendController->getCore()->getSiteRoot() . 'settings' . DIRECTORY_SEPARATOR . 'elements' . DIRECTORY_SEPARATOR;
		$configFile = $configFilePath . $this->identifier . '.config.json';

		if(file_exists($configFile) === false)
			throw new CMSException('No settings found for this module: ' . $this->identifier);

		try {
			$jsonConfig = JsonUtils::decode(file_get_contents($configFile));

			if(!isset($jsonConfig->settings))
				return 500;
		} catch(\Exception $e) {
			throw $e;
		}

		$modIDStr = 'mod-' . $this->ID . '-' . $pageID;

		$boxHtml = '<form method="post" class="mod-config-form" element="' . $modIDStr . '"><fieldset><legend>Element specific</legend>';

		foreach($jsonConfig->settings as $key => $entry) {
			$fld = null;
			$hintHtml = isset($entry->hint)?'<abbr title="' . $entry->hint->$lang . '">?</abbr>':null;
			$settingValue = isset($this->settings->$key)?$this->settings->$key:null;

			$idStr = 'mod-' . $this->ID . '-' . $pageID . '-' . $key;

			$requiredHtml = ($entry->required)?'<em title="required">*</em>':null;
			$requiredAttr = ($entry->required)?' required':null;

			if(in_array($entry->type, array('select', 'option', 'select-multi', 'multi-option'))) {
				$multiple = null;
				$multiBrackets = null;

				if(in_array($entry->type, array('select-multi', 'multi-option'))){
					$multiple = ' multiple';
					$multiBrackets = '[]';
				}

				$fld .= '<dl><dt><label for="' . $idStr . '">' . $entry->label->$lang . $requiredHtml . '</label></dt>
				<dd><select name="' . $key . $multiBrackets . '" id="' . $idStr . '"' . $requiredAttr . $multiple . '>';

				$values = $this->getValues($entry->options, $backendController);

				foreach($values as $optKey => $optVal) {
					if(in_array($entry->type, array('select-multi', 'multi-option')))
						$selected = in_array($optKey, $this->settings->$key)?' selected':null;
					else
						$selected = ($this->settings->$key == $optKey)?' selected':null;

					$fld .= '<option value="' . $optKey . '"' . $selected . '>' . $optVal . '</option>';
				}

				$fld .= '</select>' . $hintHtml . '</dd></dl>';
			} elseif(in_array($entry->type, array('input', 'email', 'number', 'url', 'regex', 'string', 'text', 'file'))) {
				$dataAttrs = null;

				if(in_array($entry->type, array('input', 'text', 'string', 'number', 'regex'))) {
					$typeStr = 'text';
					$dataAttrs = ' data-type="string"';
				} elseif($entry->type === 'file') {
					$typeStr = 'text';
					$dataAttrs = ' class="filechooser"';
				} else {
					$typeStr = $entry->type;
					$dataAttrs = ' data-type="' . $entry->type . '"';
				}

				if($entry->type === 'regex' && isset($entry->regex)) {
					$dataAttrs = ' data-type="regex" regex="' . $entry->regex . '"';
				}

				if($entry->type === 'number') {
					$minStr = (isset($entry->min))?' min="' . $entry->min . '"':null;
					$maxStr = (isset($entry->max))?' max="' . $entry->max . '"':null;

					if($minStr !== null || $maxStr !== null)
						$dataAttrs = $minStr . $maxStr;
				}

				if(in_array($entry->type, array('input', 'text', 'string'))) {
					$minStr = (isset($entry->minLength))?' minlength="' . $entry->minLength . '"':null;
					$maxStr = (isset($entry->maxLength))?' maxlength="' . $entry->maxLength . '"':null;

					if($minStr !== null || $maxStr !== null)
						$dataAttrs = $minStr . $maxStr;
				}

				$sizeClass = StringUtils::afterFirst($entry->type, '-');
				$sizeClassStr = ($sizeClass !== '')?' class="' . $sizeClass . '"':null;
				$fld .= '<dl><dt><label for="' . $idStr . '">' . $entry->label->$lang . $requiredHtml . '</label></dt>
				<dd><input type="' . $typeStr . '" name="' . $key . '" id="' . $idStr . '" value="' . $settingValue . '"' . $sizeClassStr . '' . $requiredAttr . '' . $dataAttrs . '>' . $hintHtml . '</dd></dl>';
			} elseif(in_array($entry->type, array('radio', 'multi-checkbox'))) {
				$values = $this->getValues($entry->options, $backendController);

				$type = null;
				$multiBrackets = in_array($entry->type, array('multi-checkbox'))?'[]':null;
				$selectedArr = is_array($this->settings->$key)?$this->settings->$key:array($this->settings->$key);

				if($entry->type === 'radio')
					$type = 'radio';
				elseif($entry->type === 'multi-checkbox')
					$type = 'checkbox';

				$fld .= '<dl><dt>' . $entry->label->$lang . '</dt>
				<dd><ul>';

				foreach($values as $optKey => $optVal) {
					$checked = in_array($optKey, $selectedArr)?' checked':null;
					$fld .= '<li><label><input type="' . $type . '" name="' . $key . $multiBrackets . '" value="' . $optKey . '"' . $checked . '>' . $optVal . '</label></li>';
				}

				$fld .= '</ul></dd></dl>';
			} elseif($entry->type == 'toggle') {
				$checked = ($this->settings->$key == 1)?' checked':null;
				$fld .= '<dl><dt><label for="' . $idStr . '">' . $entry->label->$lang . $requiredHtml . '</label></dt>
				<dd><input type="checkbox" name="' . $key . '" id="' . $idStr . '" value="1"' . $requiredAttr . $checked . '>' . $hintHtml . '</dd></dl>';
			} elseif($entry->type === 'wysiwyg') {
				$fld .= '<dl><dt><label for="' . $idStr . '">' . $entry->label->$lang . $requiredHtml . '</label></dt>
				<dd><textarea class="ckeditor" name="' . $key . '" id="' . $idStr . '"' . $requiredAttr . '>' . $settingValue . '</textarea>' . $hintHtml . '</dd></dl>';
			} else {
				throw new CMSException('Unknow settings data-type: ' . $entry->type);
			}

			$boxHtml .= $fld;
		}

		$boxHtml .= '</fieldset><fieldset><legend>General</legend>
			<dl><dt>Pass on</dt>
				<dd><ul>
					<li><label><input type="checkbox" value="1">Override children\'s settings</label></li>
				</ul></dd>
			</dl>';

		if($this->settingsSelf) {
			$boxHtml .= '<dl>
				<dt>Delete</dt>
				<dd><ul>
					<li><label><input name="delete_settings" type="checkbox" value="1">Delete specific element settings on this page</label></li>
				</ul></dd>
			</dl>';
		}

		$boxHtml .= '</fieldset></form>
		<script src="/js/ckeditor/ckeditor.js"></script>
		<script src="/js/ckeditor/config.js"></script>
		<script src="/js/jquery.filechooser.js"></script>
		<script src="/js/cms.settingsbox.js"></script>';

		return $boxHtml;
	}

	/**
	 * Returns the settings for this element
	 * 
	 * @return \stdClass The settings
	 */
	public function getSettings()
	{
		return $this->settings;
	}

	/**
	 * Set settings for this element
	 * 
	 * @param \stdClass $settings The settings to set
	 */
	public function setSettings(\stdClass $settings)
	{
		$this->settings = $settings;
		$this->tplVars->offsetSet('settings', $this->settings);
		$this->settingsFound = true;
	}

	/**
	 * Delete the current settings
	 */
	public function resetSettingsFound()
	{
		$this->settingsFound = false;
	}

	/**
	 * Checks if the element has settings already defined
	 * 
	 * @return bool
	 */
	public function hasSettings()
	{
		return $this->settingsFound;
	}

	/**
	 * @param $settingsSelf
	 */
	public function setSettingsSelf($settingsSelf)
	{
		$this->settingsSelf = $settingsSelf;
	}

	/**
	 * @param string $selector
	 * @param BackendController $backendController
	 *
	 * @return mixed
	 */
	protected function getValues($selector, BackendController $backendController)
	{
		if(is_string($selector) === true) {
			return StringUtils::endsWith($selector, '()')
				?call_user_func(array($this,StringUtils::beforeLast($selector, '()')), $backendController)
				:$this->$selector;
		}

		return $selector;
	}
}