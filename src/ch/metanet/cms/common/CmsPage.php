<?php

namespace ch\metanet\cms\common;

use ch\metanet\cms\controller\common\FrontendController;
use ch\metanet\cms\model\PageModel;
use ch\metanet\cms\module\layout\LayoutElement;

/**
 * This represents a CMS frontend page
 * 
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

	/** @var int The ID of the page */
	protected $ID;
	/** @var LayoutElement $layout */
	protected $layout;
	/** @var int|null The ID of the layout element */
	protected $layoutID;
	/** @var string Title of the page */
	protected $title;
	/** @var string Description of the page (a.k.a. meta description) */
	protected $description;
	/** @var string Language code (e.x. de, en, ...) */
	protected $language;
	/** @var CmsPage|null $basePage The parent page or null if there is any */
	protected $parentPage;
	/** @var string Role of this page (one of the ROLE_ constants in this class) */
	protected $role;
	/** @var int|null The error code of this page (if it's of type error -> ROLE_ERROR)  */
	protected $errorCode;
	/** @var string[] The groups and their rights to access this page */
	protected $rights;
	/** @var string|null The last modified date as ISO date string */
	protected $lastModified;
	/** @var int|null The last modifiers user ID or null if page never has been modified till now */
	protected $modifierID;
	/** @var  string|null The last modifiers user ID or null if page never has been modified till now */
	protected $modifierName;
	/** @var string The create date as ISO date string */
	protected $created;
	/** @var int The user ID of the page creator */
	protected $creatorID;
	/** @var string The user name of the page creator */
	protected $creatorName;
	/** @var int Should the page inherit rights from its parent page (1) or not (0) */
	protected $inheritRights;
	/** @var null Cache mode (not in use atm) */
	protected $cacheMode = self::CACHE_MODE_NONE;
	/** @var string[] JS code to include in this page (inline or as <script src="..." /> */
	protected $javascript;
	/** @var string[] CSS code to include in this page (inline or as <link href="...">) */
	protected $css;

	public function __construct()
	{
		$this->layoutID = null;
		$this->layout = null;
		$this->inheritRights = 0;
		$this->role = self::ROLE_STANDARD;
		
		$this->css = array();
		$this->javascript = array();
	}

	/**
	 * Renders the page with a specific view
	 *
	 * @param FrontendController $frontendController
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

	/**
	 * @return int|null
	 */
	public function getID()
	{
		return $this->ID;
	}

	/**
	 * @param int|null $ID
	 */
	public function setID($ID)
	{
		$this->ID = $ID;
	}

	/**
	 * @return string|null
	 */
	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * @param string|null $title
	 */
	public function setTitle($title)
	{
		$this->title = $title;
	}

	/**
	 * @return string|null
	 */
	public function getDescription()
	{
		return $this->description;
	}

	/**
	 * @param string|null $description
	 */
	public function setDescription($description)
	{
		$this->description = $description;
	}

	/**
	 * @return string|null
	 */
	public function getLanguage()
	{
		return $this->language;
	}

	/**
	 * @param string|null $language
	 */
	public function setLanguage($language)
	{
		$this->language = $language;
	}

	/**
	 * Set the layout of the page
	 * 
	 * @param LayoutElement $layout
	 */
	public function setLayout(LayoutElement $layout)
	{
		$this->layout = $layout;
	}

	/**
	 * @return int
	 */
	public function getLayoutID()
	{
		return $this->layoutID;
	}

	/**
	 * @param int $layoutID
	 */
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

	/**
	 * @return bool
	 */
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
	 * @param int|null $errorCode
	 */
	public function setErrorCode($errorCode)
	{
		$this->errorCode = $errorCode;
	}

	/**
	 * @return int|null
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

	/**
	 * @param string $key
	 * @param string $pageArea
	 * @param string $group
	 *
	 * @return bool
	 */
	public function removeJs($key, $pageArea = self::PAGE_AREA_BODY, $group = 'default')
	{
		if(isset($this->javascript[$pageArea][$group][$key]) === false)
			return false;

		unset($this->javascript[$pageArea][$group][$key]);

		return true;
	}

	/**
	 * @param string|null $pageArea
	 *
	 * @return string[]
	 */
	public function getJs($pageArea = null)
	{
		if($pageArea === null)
			return $this->javascript;

		if(isset($this->javascript[$pageArea]) === false)
			return array();

		return $this->javascript[$pageArea];
	}

	/**
	 * @param string $cssStr
	 * @param null $group
	 * @param null $key
	 */
	public function addCss($cssStr, $group = null, $key = null)
	{
		$this->css[] = $cssStr;
	}
}

/* EOF */