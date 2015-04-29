<?php

namespace ch\metanet\cms\common;

use ch\metanet\cms\controller\common\FrontendController;
use ch\metanet\cms\model\PageModel;
use ch\metanet\cms\module\layout\LayoutElement;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class CmsPage
{
	const CACHE_MODE_NONE = 0;
	const CACHE_MODE_PRIVATE = 1;
	const CACHE_MODE_PUBLIC = 2;

	const ROLE_TEMPLATE = 'tpl';
	const ROLE_ERROR = 'error';
	const ROLE_STANDARD = 'page';
	const ROLE_MODULE = 'module';

	const PAGE_AREA_HEAD = 'head';
	const PAGE_AREA_BODY = 'body';

	protected $ID;

	/** @var LayoutElement $layout */
	protected $layout;
	protected $layoutID;
	protected $title;
	protected $description;
	protected $language;
	/** @var CmsPage $basePage */
	protected $parentPage;
	protected $role;
	protected $errorCode;
	protected $rights;

	protected $lastModified;
	protected $modifierID;
	protected $modifierName;
	protected $created;
	protected $creatorID;
	protected $creatorName;

	protected $inheritRights;
	protected $cacheMode;

	protected $javascript;
	protected $css;

	public function __construct()
	{
		$this->layoutID = null;
		$this->layout = null;
		$this->inheritRights = 0;
		$this->role = self::ROLE_STANDARD;
	}

	/**
	 * Renders the page with a specific view
	 *
	 * @param \ch\metanet\cms\controller\common\FrontendController $frontendController
	 * @param CmsView $view
	 * @throws CMSException
	 * @return string The rendered html
	 */
	public function render(FrontendController $frontendController, CmsView $view)
	{
		if($this->layout === null && $this->layoutID !== null)
			throw new CMSException('Modules not loaded for page #' . $this->ID);

		return ($this->layout !== null)?$this->layout->render($frontendController, $view):null;
	}

	public function getID()
	{
		return $this->ID;
	}

	public function setID($ID)
	{
		$this->ID = $ID;
	}

	public function getTitle()
	{
		return $this->title;
	}

	public function setTitle($title)
	{
		$this->title = $title;
	}

	public function getDescription()
	{
		return $this->description;
	}

	public function setDescription($description)
	{
		$this->description = $description;
	}

	public function getLanguage()
	{
		return $this->language;
	}

	public function setLanguage($language)
	{
		$this->language = $language;
	}

	/**
	 * Set the layout of the page
	 * @param LayoutElement $layout
	 */
	public function setLayout(LayoutElement $layout)
	{
		$this->layout = $layout;
	}

	public function getLayoutID()
	{
		return $this->layoutID;
	}

	public function setLayoutID($layoutID)
	{
		$this->layoutID = $layoutID;
	}

	/**
	 * @return LayoutElement
	 */
	public function getLayout()
	{
		return $this->layout;
	}

	/**
	 * Returns the CmsPage object which this page is based on
	 * @return CmsPage|null
	 */
	public function getParentPage()
	{
		return $this->parentPage;
	}

	public function hasParentPage()
	{
		return ($this->parentPage !== null);
	}

	/**
	 * @param CmsPage $basePage
	 */
	public function setParentPage($basePage)
	{
		$this->parentPage = $basePage;
	}

	public function setRights($rights)
	{
		$this->rights = $rights;
	}

	public function getRights()
	{
		return $this->rights;
	}

	public function getLastModified()
	{
		return $this->lastModified;
	}

	/**
	 * @param string $lastModified Date in ISO-Format (Y-m-d H:i:s)
	 */
	public function setLastModified($lastModified)
	{
		if($lastModified !== null && ($this->lastModified === null || $this->lastModified < $lastModified)) {
			$this->lastModified = $lastModified;
		}
	}

	public function getCreated()
	{
		return $this->created;
	}

	public function setCreated($created)
	{
		$this->created = $created;
	}
	
	public function getModifierID()
	{
		return $this->modifierID;
	}

	public function getModifierName()
	{
		return $this->modifierName;
	}
	
	public function setModifierID($modifierID)
	{
		$this->modifierID = $modifierID;
	}

	public function setModifierName($modifierName)
	{
		$this->modifierName = $modifierName;
	}

	public function getCreatorID()
	{
		return $this->creatorID;
	}

	public function getCreatorName()
	{
		return $this->creatorName;
	}
	
	public function setCreatorID($creatorID)
	{
		$this->creatorID = $creatorID;
	}

	public function setCreatorName($creatorName)
	{
		$this->creatorName = $creatorName;
	}

	public function setInheritRights($inheritRights)
	{
		$this->inheritRights = $inheritRights;
	}

	public function getInheritRights()
	{
		return $this->inheritRights;
	}

	/**
	 * @param int $cacheMode The cache mode of this page
	 */
	public function setCacheMode($cacheMode)
	{
		$this->cacheMode = $cacheMode;
	}

	/**
	 * @return int The cache mode of this page
	 */
	public function getCacheMode()
	{
		return $this->cacheMode;
	}

	/**
	 * @param string $role
	 */
	public function setRole($role)
	{
		$this->role = $role;
	}

	/**
	 * @return string
	 */
	public function getRole()
	{
		return $this->role;
	}

	/**
	 * @param mixed $errorCode
	 */
	public function setErrorCode($errorCode)
	{
		$this->errorCode = $errorCode;
	}

	/**
	 * @return mixed
	 */
	public function getErrorCode()
	{
		return $this->errorCode;
	}

	/**
	 * Adds JavaScript to a specified page area (e.x. head or body)
	 * 
	 * @param string $javaScriptStr
	 * @param string $pageArea
	 * @param string|null $key
	 * @param string $group
	 */
	public function addJs($javaScriptStr, $pageArea = self::PAGE_AREA_BODY, $key = null, $group = 'default')
	{
		if(isset($this->javascript[$pageArea]) === false)
			$this->javascript[$pageArea] = array();

		if(isset($this->javascript[$pageArea][$group]) === false)
			$this->javascript[$pageArea][$group] = array();

		if($key === null)
			$this->javascript[$pageArea][$group][] = $javaScriptStr;
		else
			$this->javascript[$pageArea][$group][$key] = $javaScriptStr;
	}

	public function removeJs($key, $pageArea = self::PAGE_AREA_BODY, $group = 'default')
	{
		if(isset($this->javascript[$pageArea][$group][$key]) === false)
			return false;

		unset($this->javascript[$pageArea][$group][$key]);

		return true;
	}

	public function getJs($pageArea = null)
	{
		if($pageArea === null)
			return $this->javascript;

		if(isset($this->javascript[$pageArea]) === false)
			return array();

		return $this->javascript[$pageArea];
	}

	public function addCss($cssStr, $group = null, $key = null)
	{
		$this->css[] = $cssStr;
	}
}

/* EOF */