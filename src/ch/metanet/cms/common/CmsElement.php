<?php

namespace ch\metanet\cms\common;

use \ArrayObject;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\controller\common\FrontendController;
use ch\metanet\cms\model\PageModel;
use ch\metanet\cms\module\layout\LayoutElement;
use ch\metanet\cms\module\mod_core\TextElement;
use timesplinter\tsfw\common\JsonUtils;
use timesplinter\tsfw\db\DBException;
use timesplinter\tsfw\db\DB;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
abstract class CmsElement
{
	protected $ID;
	protected $pageID;
	protected $parentModuleID;
	protected $identifier;

	/** @var CmsElement $parentModule */
	protected $parentModule;
	protected $tplVars;
	protected $creatorID;
	protected $revision;
	protected $domLayer;

	protected $hidden;

	public function __construct($ID, $pageID, $identifier = null)
	{
		$this->ID = $ID;
		$this->pageID = $pageID;
		$this->identifier = $identifier;
		$this->domLayer = null;
		$this->tplVars = new ArrayObject();

		$this->hidden = false;

		$this->tplVars->offsetSet('_modid', $this->ID);
	}

	/**
	 * Renders the module with the given template file and TemplateEngine instance
	 * @param \ch\metanet\cms\controller\common\FrontendController $frontendController
	 * @param CmsView $view
	 * @return string Returns the rendered html of the module
	 */
	public function render(FrontendController $frontendController, CmsView $view)
	{
		return $view->render($this->identifier . '.html', (array)$this->tplVars);
	}

	public function remove(DB $db)
	{
		$stmntRemoveMod = $db->prepare("DELETE FROM cms_element_instance WHERE ID = ?");
		$db->delete($stmntRemoveMod, array($this->ID));
	}

	public function save(DB $db)
	{
		return $this->create($db);
	}

	public function create(DB $db)
	{
		try {
			// For default we create the instance in db... has to be overwritten if the module has any settings

			$stmntModID = $db->prepare("SELECT ID FROM cms_element_available WHERE name = ? AND active = 1");
			$resModID = $db->select($stmntModID, array($this->identifier));

			if(count($resModID) <= 0)
				throw new CMSException('Could not create module with identifier: "' . $this->identifier . '"');

			$stmntCreateInstance = $db->prepare("
				INSERT INTO cms_element_instance SET mod_IDFK = ?, parent_mod_IDFK = ?, creator_IDFK = ?, created = NOW(), page_IDFK = ?
			");

			$this->ID = $db->insert($stmntCreateInstance, array(
				$resModID[0]->ID,
				($this->parentModule !== null)?$this->parentModule->getID():null,
				$this->creatorID,
				$this->pageID
			));
		} catch(DBException $e) {
			throw new CMSException('Could not create module cause of DB error: ' . $e->getMessage() . ', Query: ' . $e->getQueryString());
		} catch(\Exception $e) {
			throw $e;
		}

		return true;
	}

	protected function hasConfig(FrontendController $fec)
	{
		$configFilePath = $fec->getCore()->getSiteRoot() . 'settings' . DIRECTORY_SEPARATOR . 'elements' . DIRECTORY_SEPARATOR;
		$configFile = $configFilePath . $this->identifier . '.config.json';

		if(file_exists($configFile) === false)
			return false;

		try {
			$json = JsonUtils::decodeFile($configFile);

			if(!isset($json->settings))
				return false;
		} catch(\Exception $e) {
			var_dump($e, JsonUtils::minify(file_get_contents($configFile)));
			return false;
		}

		return true;
	}

	public function generateRevisionBox(BackendController $backendController, $pageID)
	{
		$revisionPath = $backendController->getCore()->getSiteRoot() . 'revision' . DIRECTORY_SEPARATOR . $this->identifier . DIRECTORY_SEPARATOR;
		$modIDStr = 'mod-' . $this->ID . '-' . $pageID;

		$i = 0;
		$htmlOpts = '';
		$currentRevision = null;

		if(is_dir($revisionPath) === true) {
			$files = scandir($revisionPath, 1);
			$fileNamePattern = $this->identifier . '.' . $this->ID . '-' . $pageID . '.';
			$fileNamePatternLength = strlen($fileNamePattern);
			$dateTimeFormat = $backendController->getLocaleHandler()->getDateTimeFormat();

			foreach($files as $f) {
				if(($offset = strpos($f, $fileNamePattern)) === false)
					continue;

				$fileNameCropped = substr($f, $offset + $fileNamePatternLength);
				$revInfo = explode('.',$fileNameCropped);

				$selected = null;
				$currentStr = null;
				$currentRevision = null;

				if($revInfo[0] == $this->revision || ($this->revision === null && $i === 0)) {
					$selected = ' disabled';
					$currentStr = ' - current';

					$currentRevision = $revInfo[0];
				}

				$df = new \DateTime($revInfo[0]);

				$htmlOpts .= '<option value="' . $this->identifier . DIRECTORY_SEPARATOR . $f . '"' . $selected . '>' .  $revInfo[1] . ' (' .  $df->format($dateTimeFormat) . ')' . $currentStr . '</option>';

				++$i;
			}
		}

		$html = '<form method="post" class="mod-config-form" element="' . $modIDStr . '">
			<p>There are ' . $i . ' revisions for this element.</p>
			<p>Current revision: ' . $currentRevision . '</p>
			<dl>
				<dt><label for="revision">Revision</label></dt>
				<dd><select id="revision" name="revision">
					<option>- please chose -</option>' . $htmlOpts . '
				</select></dd>
			</dl>
		</form>';

		return $html;
	}

	protected function renderEditable(FrontendController $frontendController, $html)
	{
		$pageModel = new PageModel($frontendController->getDB());
		// @TODO dont know if it works 2014-02-03 pam else comment in and delete line after again
		//$cmsPage = $pageModel->getPageByID($frontendController->getCmsPage()->getID());
		$cmsPage = $frontendController->getCmsPage();

		// @TODO Move the $this->hidden compare earlier in the code, before the rendering begins or is over to save resources
		if($pageModel->hasUserWriteAccess($cmsPage, $frontendController->getAuth()) === false)
			return ($this->hidden === false)?$html:null;

		$modIDStr = 'mod-' . $this->ID . '-' . $frontendController->getCmsPage()->getID();

		$layerClass = 'element';
		$sortableClass = null;
		$hiddenClass = null;

		if($this instanceof LayoutElement)
			$layerClass = 'element-layout';

		if($this instanceof CmsElementSortable)
			$sortableClass = 'element-sortable ';

		if($this->hidden === true)
			$hiddenClass = 'element-hidden ';

		$editableHtml = '<div id="' . $modIDStr . '" class="element-editable ' . $hiddenClass . $sortableClass . str_replace('_','-', $this->identifier). ' clearfix">
			<div id="' . $modIDStr . '-content" class="' . $layerClass . ' edit-area clearfix">
				' . $html . '
				<div class="edit-area-btn-group">
					<!--<a class="edit-area-btn edit-area-btn-info ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" href="javascript:alert(\'Element type: ' . $this->identifier . '\');"><span class="ui-button-icon-primary ui-icon ui-icon-info"></span><span class="ui-button-text">Element Info</span></a>-->
					' . (($this->parentModule instanceof CmsElementSortable)?'<a class="edit-area-btn edit-area-btn-history ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only ui-move" title="move" role="button"><span class="ui-button-icon-primary ui-icon ui-icon-arrow-4"></span><span class="ui-button-text">move</span></a>':null) . '
					' . (($this instanceof TextElement)?'<a class="edit-area-btn edit-area-btn-edit ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" title="edit content" role="button"><span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text">edit</span></a> ':null) . '
					' . (($this->hasConfig($frontendController))?'<a class="edit-area-btn edit-area-btn-settings ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" href="/backend/element/' . $this->ID . '-' . $frontendController->getCmsPage()->getID() . '/ajax-settingsbox" role="button" title="Module settings: ' . $modIDStr . ' (' . $this->identifier . ')"><span class="ui-button-icon-primary ui-icon ui-icon-gear"></span><span class="ui-button-text">Settings</span></a> ':null) . '
					<a class="edit-area-btn edit-area-btn-history ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" href="/backend/element/' . $this->ID . '-' . $frontendController->getCmsPage()->getID() . '/ajax-revision-control" role="button" title="Revision control: ' . $modIDStr . ' (' . $this->identifier . ')"><span class="ui-button-icon-primary ui-icon ui-icon-clock"></span><span class="ui-button-text">Revision control</span></a>';

					if($this->pageID == $frontendController->getCmsPage()->getID()) {
						$editableHtml .= '<a class="edit-area-btn edit-area-btn-delete ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" title="delete" role="button"><span class="ui-button-icon-primary ui-icon ui-icon-trash"></span><span class="ui-button-text">delete</span></a>';
					} else {
						$editableHtml .= ($this->hidden === false)?
							'<a class="edit-area-btn edit-area-btn-hide ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" title="hide" role="button"><span class="ui-button-icon-primary ui-icon ui-icon-closethick"></span><span class="ui-button-text">hide</span></a>':
							'<a class="edit-area-btn edit-area-btn-reveal ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" title="show" role="button"><span class="ui-button-icon-primary ui-icon ui-icon-closethick"></span><span class="ui-button-text">show</span></a>';
					}

					$editableHtml .= '</div>
			</div>
		</div>';

		return $editableHtml;
	}

	/**
	 * Returns the parent module of this one
	 * 
	 * @return CmsElement
	 */
	public function getParentModule()
	{
		return $this->parentModule;
	}

	public function setParentModule(CmsElement $parentModule)
	{
		$this->parentModule = $parentModule;
	}

	public function getPageID()
	{
		return $this->pageID;
	}

	public function setPageID($pageID)
	{
		$this->pageID = $pageID;
	}

	/**
	 * Returns the unique module identifier
	 * @return string The Module identifier
	 */
	public function getIdentifier()
	{
		return $this->identifier;
	}

	public function getID()
	{
		return $this->ID;
	}

	public function setCreatorID($creatorID)
	{
		$this->creatorID = $creatorID;
	}

	public function getParentElementID()
	{
		return $this->parentModuleID;
	}

	public function setParentElementID($parentModuleID)
	{
		$this->parentModuleID = $parentModuleID;
	}

	public function setDomLayer($domLayer)
	{
		$this->domLayer = $domLayer;
	}

	/**
	 * @param string $revision
	 */
	public function setRevision($revision)
	{
		$this->revision = $revision;
	}

	/**
	 * @return string
	 */
	public function getRevision()
	{
		return $this->revision;
	}

	/**
	 * Returns true if the element is hidden or false if its not
	 * 
	 * @return boolean The hidden state of the element
	 */
	public function isHidden()
	{
		return $this->hidden;
	}

	/**
	 * Sets the hidden state of the element
	 * 
	 * @param boolean $hidden Hidden state
	 */
	public function setHidden($hidden)
	{
		$this->hidden = $hidden;
	}
}

/* EOF */