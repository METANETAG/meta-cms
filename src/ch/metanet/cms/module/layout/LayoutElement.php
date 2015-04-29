<?php

namespace ch\metanet\cms\module\layout;

use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\common\CMSException;
use ch\metanet\cms\controller\common\FrontendController;
use timesplinter\tsfw\db\DB;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
abstract class LayoutElement extends CmsElementSettingsLoadable
{
	/** @var CmsElement[] */
	protected $elements;
	/** @var array[] */
	protected $dropZones;

	public function __construct($ID, $pageID, $identifier)
	{
		parent::__construct($ID, $pageID, $identifier);

		$this->elements = array();
	}

	public function addedChildElement(DB $db, CmsElement $cmsElement, $dropzoneID, $pageID)
	{

	}

	public function removedChildElement(DB $db, CmsElement $cmsElement, $pageID)
	{

	}

	protected function renderDropzone(FrontendController $frontendController, $dropZoneID, $blackListedElements = array(), $whiteListedElements = array())
	{
		if(!$frontendController->getAuth()->isLoggedIn())
			return null;

		$this->dropZones[$dropZoneID] = array(
			'whitelist' => $whiteListedElements,
			'blacklist' => $blackListedElements
		);

		return '<div class="dropzone" dropzone="' . $dropZoneID . '"><a href="#">click <b>or</b> drag module here</a></div>' . "\n";
	}

	public function getDropzone($dropZoneID)
	{
		if(isset($this->dropZones[$dropZoneID]) === false)
			return null;

		return $this->dropZones[$dropZoneID];
	}

	public function remove(DB $db)
	{
		foreach($this->elements as $el) {
			try {
				$el->remove($db);
			} catch(\Exception $e) {
				throw new CMSException('Could not remove sub element ' . $el->getIdentifier() . ': ' . $e->getMessage());
			}
		}

		parent::remove($db);
	}

	/**
	 * Checks if the module has sub modules
	 * @return bool
	 */
	public function hasChildElements()
	{
		return (count($this->elements) > 0);
	}

	/**
	 * Returns a list of all submodules
	 * @return \ArrayObject The list of all the modules
	 */
	public function getElements()
	{
		return $this->elements;
	}

	/**
	 * @param CmsElement[] $elements
	 */
	public function setElements(array $elements)
	{
		$this->elements = $elements;
	}
}

/* EOF */