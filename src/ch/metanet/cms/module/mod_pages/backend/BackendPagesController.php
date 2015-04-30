<?php

namespace ch\metanet\cms\module\mod_pages\backend;

use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsElementSettingsLoadable;
use ch\metanet\cms\common\CmsModuleBackendController;
use ch\metanet\cms\common\CmsPage;
use ch\metanet\cms\common\CmsUtils;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\model\CoreModel;
use ch\metanet\cms\model\ElementModel;
use ch\metanet\cms\model\ModuleModel;
use ch\metanet\cms\model\PageModel;
use ch\metanet\cms\model\RightGroupModel;
use ch\metanet\cms\model\RouteModel;
use ch\metanet\cms\module\layout\LayoutElement;
use ch\metanet\cms\tablerenderer\BooleanColumnDecorator;
use ch\metanet\cms\tablerenderer\CallbackColumnDecorator;
use ch\metanet\cms\tablerenderer\Column;
use ch\metanet\cms\tablerenderer\DateColumnDecorator;
use ch\metanet\cms\tablerenderer\LinkColumnDecorator;
use ch\metanet\cms\tablerenderer\RewriteColumnDecorator;
use ch\metanet\cms\tablerenderer\TableRenderer;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\RequestHandler;
use ch\timesplinter\formhelper\FormHelper;
use timesplinter\tsfw\common\StringUtils;
use ch\metanet\cms\tablerenderer\EmptyValueColumnDecorator;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class BackendPagesController extends CmsModuleBackendController
{
	/** @var  FormHelper */
	private $formHelper;
	private $pageModel;
	private $routeModel;

	public function __construct(BackendController $moduleController, $moduleName)
	{
		parent::__construct($moduleController, $moduleName);

		$this->controllerRoutes = array(
			'/' => array(
				'*' => 'getModuleOverview'
			),
			'/page/(\d+)' => array(
				'GET' => 'getPageDetails',
			),

			'/page/(\d+)/edit' => array(
				'GET' => 'getPageEdit',
				'POST' => 'postPageEdit'
			),
			'/page/create' => array(
				'GET' => 'getPageEdit',
				'POST' => 'postPageEdit'
			),
			'/page/(\d+)/delete' => array(
				'GET' => 'deleteNewsEntry'
			),

			/* rights */
			'/page/(\d+)/right-add' => array(
				'GET' => 'getPageRightEdit',
				'POST' => 'processPageRightEdit'
			),
		);

		$this->pageModel = new PageModel($this->cmsController->getDB());
		$this->routeModel = new RouteModel($this->cmsController->getDB());
	}

	public function getModuleOverview()
	{
		if($this->cmsController->getHttpRequest()->getVar('delete') !== null)
			$this->deletePage();

		$pageQueryStr = "
			SELECT
				p.ID, p.title, p.description, bp.ID base_page_ID, bp.title base_page_title, l.name lang_name, lc.ID creator_ID, lc.username creator_name, p.created,
				lm.ID last_modifier_ID, lm.username last_modifier, p.last_modified, p.uniqid,p.inhert_rights, r.pattern
			FROM page p
			LEFT JOIN login lc ON lc.ID = p.creator_IDFK
			LEFT JOIN login lm ON lm.ID = p.modifier_IDFK
			LEFT JOIN page bp ON bp.ID = p.base_page_IDFK
			LEFT JOIN route r ON r.page_IDFK = p.ID
			LEFT JOIN language l ON l.code = p.language_codeFK
			ORDER BY p.title
		";

		$columnTitle = new Column('p.title', 'Title', array(new RewriteColumnDecorator('<a href="' . $this->baseLink . '/page/{ID}">{title}</a>')), true);
		$columnTitle->setFilter();

		$columnRoute = new Column('pattern', 'Route', array(), true, 'r.pattern');
		$columnRoute->setHidden(true);
		$columnRoute->setFilter();
		
		$tableRenderer = new TableRenderer('pages', $this->cmsController->getDB(), $pageQueryStr);
		$tableRenderer->setColumns(array(
			new Column('ID', '#', array(), true, 'p.ID'),
			$columnTitle,
			new Column('lang_name', 'Language', array(), true, 'l.name'),
			$columnRoute,
			/*new Column('base_page_ID', 'Base page', array(new RewriteColumnDecorator('<a href="#">{base_page_title} (#{base_page_ID})</a>')), true),*/
			new Column(null, 'Page inheritance', array(new CallbackColumnDecorator(array($this,'getPagePathTableRenderer')))),
			new Column('inhert_rights', 'Inherit rights', array(new BooleanColumnDecorator()), true, 'p.inhert_rights'),
			new Column('created', 'Created', array(new DateColumnDecorator($this->cmsController->getLocaleHandler()->getDateTimeFormat())), true, 'p.created'),
			new Column('creator_name', 'Creator', array(new RewriteColumnDecorator('<a href="/backend/account/{creator_ID}">{creator_name}</a>')), true, 'lc.username'),
			new Column('last_modified', 'Last modified', array(new EmptyValueColumnDecorator('<span class="no-value">never</span>'), new DateColumnDecorator($this->cmsController->getLocaleHandler()->getDateTimeFormat())), true, 'p.last_modified'),
			new Column('last_modifier', 'Last modifier', array(new EmptyValueColumnDecorator('<span class="no-value">no one</span>'), new RewriteColumnDecorator('<a href="/backend/account/{last_modifier_ID}">{last_modifier}</a>')), true, 'lm.username')
		));
		
		$tableRenderer->setOptions(array('preview' => '/preview/page/{uniqid}', 'delete' => '?delete={ID}'));

		return $this->renderModuleContent('mod-pages-overview', array(
			'siteTitle' => 'Pages',
			'base_link' => $this->baseLink,
			'pages' => $tableRenderer->display(),
			'pages_tree' => $this->getPagesHierarchically()
		));
	}

	public function getPageDetails($params)
	{
		$pageID = (int)$params[0];

		$cmsPage = $this->pageModel->getPageByID($pageID);

		if($cmsPage === null)
			throw new HttpException('Could not find page', 404);
		
		$moduleModel = new ElementModel($this->cmsController->getDB());
		$coreModel = new CoreModel($this->cmsController->getDB());
		//$modules = $moduleModel->getModulesByPage($pageData);
		$elements = $moduleModel->getElementTree($cmsPage);

		$rights = array();

		foreach($cmsPage->getRights() as $r) {
			$rightEntry = new \stdClass();

			$rightEntry->ID = $r->ID;
			$rightEntry->group_name = $r->groupname;
			$rightEntry->rights = CmsUtils::getRightsAsString($r->rights);

			$rightEntry->inherited_page = ($r->inherted_page == $pageID)?null:'<a href="/backend/page/' . $r->inherted_page . '">Page #' . $r->inherted_page . '</a>';

			$dtStart = new \DateTime($r->start_date);
			$dtFormat = $this->cmsController->getLocaleHandler()->getDateTimeFormat();

			$rightEntry->duration = ($r->end_date === null)?'since ' . $dtStart->format($dtFormat):$dtStart->format($dtFormat) . ' - ' . $r->end_date;
			$rights[] = $rightEntry;
		}

		$sqlQueryStr = "SELECT r.ID, r.pattern, m.ID mod_ID, m.name mod_name, r.robots, r.ssl_forbidden, r.ssl_required
			FROM route r
			LEFT JOIN cms_mod_available m ON m.ID = r.mod_IDFK
			WHERE page_IDFK = ?
		";

		$trRoutes = new TableRenderer('route', $this->cmsController->getDB(), $sqlQueryStr);
		$trRoutes->setColumns(array(
			new Column('ID', 'ID'),
			new Column('pattern', 'Path', array(new LinkColumnDecorator())),
			new Column('mod_name', 'Module', array(new EmptyValueColumnDecorator('<em>none</em>'), new RewriteColumnDecorator('<a href="/backend/module/{mod_name}">{mod_name}</a>'))),
			new Column('robots', 'Robots', array(new EmptyValueColumnDecorator('<em>default</em>'))),
			new Column(null, 'SSL', array(new CallbackColumnDecorator(function($value, $record, $selector, $tableRenderer) {
				$sslAttrs = array();
				
				if($record->ssl_forbidden + $record->ssl_required == 0)
					return 'user\'s choice';
				
				if($record->ssl_forbidden == 1)
					$sslAttrs[] = 'forbidden';
				
				if($record->ssl_required == 1)
					$sslAttrs[] = 'required';
				
				return implode(' / ', $sslAttrs);
			})))
		));
		$trRoutes->setOptions(array(
			'edit' => '/backend/route/{ID}/edit',
			'delete' => '/backend/route/{ID}/delete'
		));
		
		$pageInfo = array();
		
		$pageInfo['ID'] = $pageID;
		$pageInfo['Title'] = $cmsPage->getTitle();
		
		if($cmsPage->getDescription() !== null)
			$pageInfo['Description'] = $cmsPage->getDescription();
		
		if(($pagePathAsHtmlStr = $this->getPagePathAsHtmlStr($this->pageModel->getPagePath($pageID))) !== null)
			$pageInfo['Page inheritance'] = $pagePathAsHtmlStr;
		
		$roles = $this->getRoleOptions();
		
		$pageInfo['Role'] = isset($roles[$cmsPage->getRole()]) ? $roles[$cmsPage->getRole()] : $cmsPage->getRole();
		
		$languages = $coreModel->getLanguages();
		
		$pageInfo['Language'] = isset($languages[$cmsPage->getLanguage()]) ? $languages[$cmsPage->getLanguage()] : $cmsPage->getLanguage();
		$pageInfo['Inherit rights'] = $cmsPage->getInheritRights() == 1 ? 'yes' : 'no';
		
		$dtCreated = new \DateTime($cmsPage->getCreated());
		$pageInfo['Created'] = $dtCreated->format($this->cmsController->getLocaleHandler()->getDateTimeFormat()) . ' by <a href="/backend/account/' . $cmsPage->getCreatorID() . '">' . $cmsPage->getCreatorName() . '</a>';
		
		if($cmsPage->getLastModified() !== null) {
			$dtLastModified = new \DateTime($cmsPage->getCreated());
			$pageInfo['Last modified'] = $dtLastModified->format($this->cmsController->getLocaleHandler()->getDateTimeFormat()) . ' by <a href="/backend/account/' . $cmsPage->getModifierID() . '">' . $cmsPage->getModifierName() . '</a>';
		}
		
		$tplVars = array(
			'siteTitle' => 'Page "' . $cmsPage->getTitle() . '"',
			'base_link' => $this->baseLink,
			'page' => $cmsPage,
			'page_info' => $pageInfo,
			'rights' => $rights,
			'elements_list' => $this->generateElementList($elements, $cmsPage->getID()),
			'routes' => $trRoutes->display(array($pageID))
		);

		return $this->renderModuleContent('mod-pages-page-details', $tplVars);
	}

	public function getPageEdit($params)
	{
		$this->cmsController->abortIfUserHasNotRights('MOD_PAGES_EDIT');

		$pageID = null;
		$pageData = null;
		$routeData = null;

		if(isset($params[0]) === true) {
			$pageID = (int)$params[0];
			$pageData = $this->pageModel->getPageByID($pageID);
			$routes = $this->routeModel->getRoutesByPageID($pageID);

			if(count($routes) > 0)
				$routeData = array_shift($routes);
		}

		$coreModel = new CoreModel($this->cmsController->getDB());

		$basePagesOpts = array(0 => '- no base page -');

		foreach($this->pageModel->getBasePagesForPage($pageID) as $p) {
			$basePagesOpts[$p->ID] = $p->language_codeFK . ', ' . $p->title;
		}

		$basePagesOpts = $this->pageModel->generatePageTreeOpts($this->pageModel->getBasePagesForPage($pageID), CmsPage::ROLE_TEMPLATE);

		$tplVars = array(
			'siteTitle' => ($pageData !== null)?'Edit general page settings "' . $pageData->getTitle() . '"':'Create new page',
			'scripts_footer' => '<script src="/js/backend/mod-pages-page-edit.js"></script>',
			'base_link' => $this->baseLink,
			'page' => $pageData,
			'submit_label' => ($pageID === null)?'Create':'Update',

			'form_status' => ($this->formHelper !== null && $this->formHelper->hasErrors())?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()):null,
			'form_title' => ($pageData !== null)?$pageData->getTitle():null,
			'form_language' => ($pageData !== null)?$pageData->getLanguage():null,
			'form_base_page' => ($pageData !== null && $pageData->getParentPage() !== null)?$pageData->getParentPage()->getID():null,
			'form_description' => ($pageData !== null)?$pageData->getDescription():null,
			'form_inherit_rights' => ($pageData !== null)?$pageData->getInheritRights():1,
			'form_role' => ($pageData !== null)?$pageData->getRole():null,
			'form_error_code' => ($pageData !== null)?$pageData->getErrorCode():null,

			// Route stuff
			'form_ssl' => null,
			'form_pattern' => null,
			'form_module' => null,

			// Select field options
			'opts_language' => $coreModel->getLanguages(),
			'opts_base_page' => $basePagesOpts,
			'opts_error_code' => $this->getErrorCodeOptions(),
			'opts_module' => $this->getModuleOptions(),
			'opts_role' => $this->getRoleOptions(),
			'opts_ssl' => $this->getSSLOptions(),

			//'page_rights' => ($this->httpRequest->getParam(0) !== null)?$tableRenderer->display(array($this->httpRequest->getParam(0))):null
		);

		if($routeData !== null) {
			$trimmedRoute = $routeData->pattern;

			if(StringUtils::startsWith($trimmedRoute, '/'))
				$trimmedRoute = substr($trimmedRoute, 1);

			$tplVars['form_ssl'] = $this->getSSLMode($routeData);
			$tplVars['form_pattern'] = $trimmedRoute;
			$tplVars['form_module'] = $routeData->mod_IDFK;
		}

		if($this->formHelper !== null && $this->formHelper->sent()) {
			$tplVars['form_title'] = $this->formHelper->getFieldValue('title');
			$tplVars['form_language'] = $this->formHelper->getFieldValue('language');
			$tplVars['form_description'] = $this->formHelper->getFieldValue('description');
			$tplVars['form_inherit_rights'] = $this->formHelper->getFieldValue('inherit_rights');
			$tplVars['form_role'] = $this->formHelper->getFieldValue('role');
			$tplVars['form_error_code'] = $this->formHelper->getFieldValue('error_code');
		}

		return $this->renderModuleContent('mod-pages-page-edit', $tplVars);
	}

	public function postPageEdit($params)
	{
		$this->cmsController->abortIfUserHasNotRights('MOD_PAGES_EDIT');

		$pageID = null;
		$routeID = null;

		if(isset($params[0]) === true) {
			$pageID = (int)$params[0];

			$routes = $this->routeModel->getRoutesByPageID($pageID);

			if(count($routes) > 0) {
				$routeData = array_shift($routes);
				$routeID = $routeData->ID;
			}
		}

		$coreModel = new CoreModel($this->cmsController->getDB());

		$basePagesOpts = array(0 => '- no base page -');

		foreach($this->pageModel->getBasePagesForPage($pageID) as $p) {
			$basePagesOpts[$p->ID] = $p->language_codeFK . ', ' . $p->title;
		}

		$this->formHelper = new FormHelper(FormHelper::METHOD_POST);
		$this->formHelper->addField('title', null, FormHelper::TYPE_STRING, true, array(
			'missingError' => 'Please insert a title for this page'
		));
		$this->formHelper->addField('language', null, FormHelper::TYPE_OPTION, true, array(
			'missingError' => 'Please select a language this page',
			'invalidError' => 'Please select a valid language for this page',
			'options' => $coreModel->getLanguages()
		));
		$this->formHelper->addField('role', null, FormHelper::TYPE_OPTION, true, array(
			'missingError' => 'Please select a role for this page',
			'invalidError' => 'Please select a valid role for this page',
			'options' => $this->getRoleOptions()
		));
		$this->formHelper->addField('base_page', null, FormHelper::TYPE_OPTION, false, array(
			'invalidError' => 'Please select a valid base page for this page',
			'options' => $basePagesOpts
		));
		$this->formHelper->addField('description', null, FormHelper::TYPE_STRING, false);
		$this->formHelper->addField('inherit_rights', null, FormHelper::TYPE_CHECKBOX);

		if(!$this->formHelper->sent() || !$this->formHelper->validate())
			return $this->getPageEdit($params);

		$pageRole = $this->formHelper->getFieldValue('role');

		// Role specific form checks
		if($pageRole === 'error') {
			$this->formHelper->addField('error_code', null, FormHelper::TYPE_OPTION, true, array(
				'missingError' => 'Please select an error code for this page',
				'invalidError' => 'Please select a valid error code for this page',
				'options' => $this->getErrorCodeOptions()
			));
		}

		if(in_array($pageRole, array('page', 'module')) === true) {
			// Route
			$this->formHelper->addField('route', null, FormHelper::TYPE_STRING, true, array(
				'missingError' => 'Please insert a title for this page'
			));

			$this->formHelper->addField('ssl', null, FormHelper::TYPE_OPTION, true, array(
				'missingError' => 'Please choose an SSL option',
				'invalidError' => 'Please choose a valid SSL option',
				'options' => $this->getSSLOptions()
			));
		}

		if($pageRole === 'module') {
			$this->formHelper->addField('module', null, FormHelper::TYPE_OPTION, true, array(
				'missingError' => 'Please choose a module',
				'invalidError' => 'Please choose a valid module',
				'options' => $this->getModuleOptions()
			));
		}

		if(!$this->formHelper->validate())
			return $this->getPageEdit($params);


		$stmntCheckErrorCode = $this->cmsController->getDB()->prepare("
			SELECT ID FROM page WHERE error_code = ?
		");

		$errorCode = $this->formHelper->getFieldValue('error_code');

		$resCheckErrorCode = $this->cmsController->getDB()->select($stmntCheckErrorCode, array($errorCode));

		if(count($resCheckErrorCode) > 0 && $resCheckErrorCode[0]->ID != $pageID)
			$this->formHelper->addError('error_code', 'There is already an error page (#' . $resCheckErrorCode[0]->ID . ') for error case ' . $errorCode);

		if($this->formHelper->hasErrors())
			return $this->getPageEdit($params);

		// save settings
		$layoutIDFK = null;

		if($this->formHelper->getFieldValue('base_page') !== null) {
			$stmntLayout = $this->cmsController->getDB()->prepare("SELECT layout_IDFK FROM page WHERE ID = ?");
			$resLayout = $this->cmsController->getDB()->select($stmntLayout, array(
				$this->formHelper->getFieldValue('base_page')
			));

			if(count($resLayout) > 0)
				$layoutIDFK = $resLayout[0]->layout_IDFK;
		}

		try {
			$stmntUpdate = $this->cmsController->getDB()->prepare("
				INSERT INTO page SET
					ID = ?, title = ?, language_codeFK = ?, description = ?, base_page_IDFK = ?, layout_IDFK = ?, inhert_rights = ?, creator_IDFK = ?, created = NOW(), role = ?, error_code = ?, uniqid = ?
				ON DUPLICATE KEY UPDATE
					title = ?, language_codeFK = ?, description = ?, base_page_IDFK = ?, layout_IDFK = ?, inhert_rights = ?, modifier_IDFK = ?, role = ?, error_code = ?, last_modified = NOW()
			");

			$basePageParam = ($this->formHelper->getFieldValue('base_page') != 0)?$this->formHelper->getFieldValue('base_page'):null;

			$msgKey = 'updated';

			$this->cmsController->getDB()->update($stmntUpdate, array(
				// INSERT
				$pageID,
				$this->formHelper->getFieldValue('title'),
				$this->formHelper->getFieldValue('language'),
				$this->formHelper->getFieldValue('description'),
				$basePageParam,
				$layoutIDFK,
				$this->formHelper->getFieldValue('inherit_rights'),
				$this->cmsController->getAuth()->getUserID(),
				$this->formHelper->getFieldValue('role'),
				$this->formHelper->getFieldValue('error_code'),
				uniqid(),

				// UPDATE
				$this->formHelper->getFieldValue('title'),
				$this->formHelper->getFieldValue('language'),
				$this->formHelper->getFieldValue('description'),
				$basePageParam,
				$layoutIDFK,
				$this->formHelper->getFieldValue('inherit_rights'),
				$this->cmsController->getAuth()->getUserID(),
				$this->formHelper->getFieldValue('role'),
				$this->formHelper->getFieldValue('error_code')
			));

			if($pageID === null) {
				$pageID = $this->cmsController->getDB()->lastInsertId();
				$msgKey = 'created';
			}


			// Route things
			$ssl = $this->formHelper->getFieldValue('ssl');

			$sslRequired = 0;
			$sslForbidden = 0;

			if($ssl === 'required')
				$sslRequired = 1;
			elseif($ssl === 'forbidden')
				$sslForbidden = 1;

			$modID = null;

			if($pageRole === CmsPage::ROLE_MODULE)
				$modID = $this->formHelper->getFieldValue('module');

			if(in_array($pageRole, array(CmsPage::ROLE_STANDARD, CmsPage::ROLE_MODULE)) === true) {
				$formPattern = $this->formHelper->getFieldValue('route');

				if(StringUtils::startsWith($formPattern, '/') === false)
					$formPattern = '/' . $formPattern;

				$stmntRouteUpdate = $this->cmsController->getDB()->prepare("
					INSERT INTO route SET
						ID = ?, pattern = ?, page_IDFK = ?, ssl_required = ?, ssl_forbidden = ?, mod_IDFK = ?, regex = 0
					ON DUPLICATE KEY UPDATE
						pattern = ?, page_IDFK = ?, ssl_required = ?, ssl_forbidden = ?, mod_IDFK = ?
				");


				$this->cmsController->getDB()->update($stmntRouteUpdate, array(
					// INSERT
					$routeID,
					$formPattern,
					$pageID,
					$sslRequired,
					$sslForbidden,
					$modID,

					// UPDATE
					$formPattern,
					$pageID,
					$sslRequired,
					$sslForbidden,
					$modID
				));
			}
		} catch(\Exception $e) {
			$this->formHelper->addError(null, $e->getMessage());

			return $this->getPageEdit($params);
		}

		$this->setMessageKeyForNextPage($msgKey);

		RequestHandler::redirect($this->baseLink . '/page/' . $pageID);
	}

	public function deletePage()
	{
		$this->cmsController->abortIfUserHasNotRights('MOD_PAGES_DELETE');

		$this->pageModel->deletePageByID($this->cmsController->getHttpRequest()->getVar('delete'));
	}

	public function getPageRightEdit($params)
	{
		$rightGroupModel = new RightGroupModel($this->cmsController->getDB());
		$rightGroupID = isset($params[1])?$params[1]:null;
		$pageID = isset($params[0])?$params[0]:null;

		$formData = array();

		if($rightGroupID !== null) {
			$rg = $this->pageModel->getRightEntryByPageID($rightGroupID, $pageID);

			$formData['form_rightgroup'] = array($rightGroupID);
			$formData['form_date_from'] = $rg->start_date;
			$formData['form_date_to'] = $rg->end_date;
			$formData['form_rights'] = CmsUtils::getRightsFromDec($rg->rights);
		} else {
			$formData['form_rightgroup'] = array();
			$formData['form_date_from'] = null;
			$formData['form_date_to'] = null;
			$formData['form_rights'] = array();
		}

		$optsRightGroups = array(-1 => '- please choose -');

		foreach($rightGroupModel->getRightGroups() as $g) {
			if($g->isRoot() === true)
				continue;

			$optsRightGroups[$g->getID()] = $g->getGroupName();
		}

		$tplVars = array(
			'siteTitle' => ($rightGroupID === null)?'Add rightgroup access for page #' . $pageID:'Edit access of rightgroup #' . $rightGroupID . ' for page #' . $pageID,
			'opts_rightgroups' => $optsRightGroups,
			'form_status' => ($this->formHelper !== null && $this->formHelper->hasErrors())?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()):null,
			'opt_rights' => array('read' => 'read', 'write' => 'write')
		);

		if($this->formHelper !== null && $this->formHelper->sent()) {
			$formData['form_date_from'] = $this->formHelper->getFieldValue('date_from');
			$formData['form_date_to'] = $this->formHelper->getFieldValue('date_to');
			$formData['form_rightgroup'] = $this->formHelper->getFieldValue('rightgroup');
			$formData['form_rights'] = $this->formHelper->getFieldValue('rights');
		}

		return $this->renderModuleContent('backend-page-right-edit', array_merge($tplVars, $formData));
	}

	public function getPageRightRemove()
	{
		$pageID = $this->cmsController->getHttpRequest()->getParam(0);
		$rightID = $this->cmsController->getHttpRequest()->getParam(1);

		$stmntRemoveRight = $this->cmsController->getDB()->prepare("DELETE FROM page_has_rightgroup WHERE rightgroup_IDFK = ? AND page_IDFK = ?");
		$this->db->delete($stmntRemoveRight, array(
			$rightID, $pageID
		));

		RequestHandler::redirect($this->baseLink . '/page/' . $pageID);
	}

	public function processPageRightEdit($params)
	{
		$this->formHelper = new FormHelper(FormHelper::METHOD_POST);

		$pageID = isset($params[0])?$params[0]:null;

		$rightGroupModel = new RightGroupModel($this->cmsController->getDB());

		$optsRightGroups = array();

		foreach($rightGroupModel->getRightGroups() as $g) {
			if($g->isRoot() === true)
				continue;

			$optsRightGroups[$g->getID()] = $g->getGroupName();
		}

		$this->formHelper->addField('rightgroup', null, FormHelper::TYPE_OPTION, true, array(
			'missingError' => 'Please choose a group',
			'invalidError' => 'Please choose a valid group',
			'options' => $optsRightGroups
		));
		$this->formHelper->addField('rights', null, FormHelper::TYPE_MULTIOPTIONS, false, array(
			'missingError' => 'Please choose one or more rights',
			'invalidError' => 'Please choose one or more valid rights',
			'options' => array('read' => 'read', 'write' => 'write')
		));
		$this->formHelper->addField('date_from', null, FormHelper::TYPE_DATE, true, array(
			'missingError' => 'Please enter a date from where the group should have access',
			'invalidError' => 'Please enter a valid date from where the group should habe acess'
		));
		$this->formHelper->addField('date_to', null, FormHelper::TYPE_DATE, false, array(
			'invalidError' => 'Please enter a valid date till when the group should habe acess'
		));

		if(!$this->formHelper->sent() || !$this->formHelper->validate())
			return $this->getPageRightEdit($params);

		$dateFrom = $this->formHelper->getFieldValue('date_from');
		$dateTo = $this->formHelper->getFieldValue('date_to');

		if($dateFrom !== null)
			$dtFrom = new \DateTime($this->formHelper->getFieldValue('date_from'));

		if($dateTo !== null)
			$dtTo = new \DateTime($this->formHelper->getFieldValue('date_to'));

		$rights = $this->formHelper->getFieldValue('rights');


		try {
			$stmntSaveRightGroup = $this->cmsController->getDB()->prepare("
				INSERT INTO page_has_rightgroup SET page_IDFK = ?, rightgroup_IDFK = ?, start_date = ?, end_date = ?, rights = ?
				ON DUPLICATE KEY UPDATE start_date = ?, end_date = ?, rights = ?
			");

			$this->cmsController->getDB()->insert($stmntSaveRightGroup, array(
				// INSERT
				$pageID,
				$this->formHelper->getFieldValue('rightgroup'),
				($dateFrom !== null)?$dtFrom->format('Y-m-d H:i:s'):null,
				($dateTo !== null)?$dtTo->format('Y-m-d H:i:s'):null,
				CmsUtils::getRightsAsDec(
					in_array('read', $rights)?'1':'0',
					in_array('write', $rights)?'1':'0'
				),

				// UPDATE
				($dateFrom !== null)?$dtFrom->format('Y-m-d H:i:s'):null,
				($dateTo !== null)?$dtTo->format('Y-m-d H:i:s'):null,
				CmsUtils::getRightsAsDec(
					in_array('read', $rights)?'1':'0',
					in_array('write', $rights)?'1':'0'
				)
			));
		} catch(\Exception $e) {
			$this->formHelper->addError(null, 'Could not save right information');
		}

		if($this->formHelper->hasErrors())
			return $this->getPageRightEdit($params);

		RequestHandler::redirect($this->baseLink . '/page/' . $pageID);
	}

	public function getPagePathTableRenderer($value, $record, $selector, $tableRenderer)
	{
		return $this->getPagePathAsHtmlStr($this->pageModel->getPagePath($record->ID));
	}

	protected function getPagesHierarchically($pageID = null)
	{
		$htmlList = '<ul>';

		$pagesStmnt = $this->cmsController->getDB()->prepare("
			SELECT ID, title, language_codeFK lang FROM page WHERE " . (($pageID === null)?"base_page_IDFK IS NULL":"base_page_IDFK = ?") . " ORDER BY title
		");

		$params = ($pageID === null)?array():array($pageID);

		$pages = $this->cmsController->getDB()->select($pagesStmnt, $params);

		foreach($pages as $p) {
			/** @var CmsElement $m */
			$htmlList .= '<li><a href="' . $this->baseLink . '/page/' . $p->ID . '">' . $p->title . ' <em>(#' . $p->ID . ', ' . $p->lang . ')</em></a>';

			$htmlList .= $this->getPagesHierarchically($p->ID);

			$htmlList .= '</li>';
		}

		return $htmlList . '</ul>';
	}

	protected function generateElementList($modules, $pageID)
	{
		$htmlList = '<ul>';

		foreach($modules as $m) {
			$inheritedFrom = ($m->getPageID() !== $pageID)?', inherited from page #' . $m->getPageID():null;

			/** @var CmsElement $m */
			$htmlList .= '<li data="icon: \'/images/icon-' . $m->getIdentifier() . '.png\'"><a href="/backend/module-instance/' . $m->getID() . '" title="' . $this->getSettingsAsStr($m) . '">' . $m->getIdentifier() . ' <em>(#' . $m->getID() . $inheritedFrom . ')</em> <a href="" class="delete">delete</a></a> ';

			if($m instanceof LayoutElement && $m->hasChildElements())
				$htmlList .= $this->generateElementList($m->getElements(), $pageID);

			$htmlList .= '</li>';
		}

		return $htmlList . '</ul>';
	}

	protected static function getSettingsAsStr(CmsElement $mod)
	{
		if($mod instanceof CmsElementSettingsLoadable === false)
			return '(no settings)';

		/** @var CmsElementSettingsLoadable $mod */
		
		if($mod->hasSettings() === false)
			return '(no settings found)';

		$settingsStr = '';
		
		foreach($mod->getSettings() as $k => $v) {
			if(in_array($k, array('mod_instance_IDFK'/*, 'page_IDFK'*/)))
				continue;

			$value = htmlentities(strip_tags(print_r($v, true)));
			$value = strlen($value) > 254 ? substr($value, 0, 254) . ' [...]' : $value;
			
			$settingsStr .= $k . ': ' . $value . "\n";
		}

		return $settingsStr;
	}

	protected function getRoleOptions()
	{
		return array(
			CmsPage::ROLE_STANDARD => 'Standard',
			CmsPage::ROLE_MODULE => 'Module',
			CmsPage::ROLE_TEMPLATE => 'Template',
			CmsPage::ROLE_ERROR => 'Error'
		);
	}

	protected function getSSLOptions()
	{
		return array(
			'optional' => 'users decision',
			'required' => 'required',
			'forbidden' => 'forbidden'
		);
	}

	protected function getModuleOptions()
	{
		$moduleModel = new ModuleModel($this->cmsController->getDB());
		$moduleOptions = array(0 => '- please choose -');
		$lang = $this->cmsController->getLocaleHandler()->getLanguage();

		foreach($moduleModel->getModulesWithFrontendController() as $mod) {
			$moduleOptions[$mod->ID] = $mod->manifest_content->name->{$lang};
		};

		return $moduleOptions;
	}

	protected function getErrorCodeOptions()
	{
		return array(
			404 => 'HTTP Error 404 - Page not found',
			403 => 'HTTP Error 403 - Access forbidden',
			500 => 'HTTP Error 500 - Server Error'
		);
	}

	protected function getSSLMode($routeData)
	{
		if($routeData->ssl_required == 1)
			return 'required';
		elseif($routeData->ssl_forbidden == 1)
			return 'forbidden';
		elseif($routeData->ssl_forbidden == 0 && $routeData->ssl_required == 0)
			return 'optional';
	}
	
	protected function getPagePathAsHtmlStr(array $pagePathEntries) 
	{
		$htmlRes = array();
		
		foreach($pagePathEntries as $id => $title)
			$htmlRes[] = '<a href="' . $this->baseLink . '/page/' . $id . '">' . $title . '</a>';
		
		return count($htmlRes) > 0 ? implode(' > ', $htmlRes) : null;
	}
}

/* EOF */