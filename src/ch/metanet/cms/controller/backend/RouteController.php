<?php

namespace ch\metanet\cms\controller\backend;

use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\model\ModuleModel;
use ch\metanet\cms\model\PageModel;
use ch\metanet\cms\model\RouteModel;
use ch\metanet\cms\tablerenderer\BooleanColumnDecorator;
use ch\metanet\cms\tablerenderer\CallbackColumnDecorator;
use ch\metanet\cms\tablerenderer\Column;
use ch\metanet\cms\tablerenderer\LinkColumnDecorator;
use ch\metanet\cms\tablerenderer\TableRenderer;
use timesplinter\tsfw\common\JsonUtils;
use ch\timesplinter\core\Core;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\HttpResponse;
use ch\timesplinter\core\RequestHandler;
use ch\timesplinter\core\Route;
use ch\timesplinter\formhelper\FormHelper;
use ch\metanet\cms\common\CmsUtils;
use timesplinter\tsfw\common\StringUtils;

/**
 * The route controller handles installed modules
 *
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class RouteController extends BackendController {
	/** @var $formHelper FormHelper */
	protected $formHelper;

	private $pageModel;
	private $routeModel;

	public function __construct(Core $core, HttpRequest $httpRequest, Route $route) {
		parent::__construct($core, $httpRequest, $route);

		$this->pageModel = new PageModel($this->db);
		$this->routeModel = new RouteModel($this->db);

		$this->markHtmlIdAsActive('routes');
	}

	/**
	 * Shows all the pages and their dependencies
	 * @return HttpResponse
	 */
	public function getRoutesOverview() {
		$lang = $this->getLocaleHandler()->getLanguage();

		$sqlRoutes = "
			SELECT
				r.ID, r.regex, r.pattern, r.robots, r.external_source, r.page_IDFK, r.redirect_route_IDFK,
				rr.ID rrID, rr.pattern rr_pattern,
				r.mod_IDFK, m.ID mod_ID, m.manifest_content, m.name mod_name,
				p.ID page_ID, p.language_codeFK page_lang, p.title page_title
			FROM route r
			LEFT JOIN route rr ON rr.ID = r.redirect_route_IDFK
			LEFT JOIN page p ON p.ID = r.page_IDFK
			LEFT JOIN cms_mod_available m ON m.ID = r.mod_IDFK
			ORDER BY pattern
		";
		$stmntRoutes = $this->db->prepare($sqlRoutes);

		$trRoutes = new TableRenderer('routes', $this->db, $sqlRoutes);
		$trRoutes->setOptions(array(
			'edit' => '/backend/route/{ID}/edit',
			'delete' => '/backend/route/{ID}/delete'
		));
		$columnPattern = new Column('r.pattern', 'Pattern', array(new LinkColumnDecorator()), true);
		$columnPattern->setFilter();
		$columnDestination = new Column(null, 'Destination', array(new CallbackColumnDecorator(array($this, 'renderDestinationColumn'))));

		$trRoutes->setColumns(array(
			new Column('ID', 'ID', array(), true, 'r.ID'),
			$columnPattern,
			new Column('r.regex', 'Regex active', array(new BooleanColumnDecorator()), true),
			$columnDestination,
			new Column('r.robots', 'Robots', array(), true)
		));

		$resRoutes = $this->db->select($stmntRoutes);
		$pageModel = new PageModel($this->db);

		/*foreach($resRoutes as $r) {
			if($r->external_source !== null) {
				$r->destination = 'External: <a href="' . $r->external_source . '">' . $r->external_source . '</a>';
			} elseif($r->redirect_route_IDFK !== null) {
				$r->destination = 'Route redirect: <a href="#">' . $r->rr_pattern . ' (#' . $r->rrID . ')</a>';
			} elseif($r->page_ID !== null) {
				$pagePath = $pageModel->getPagePath($r->page_ID);

				$r->destination = 'Page: ' . ((count($pagePath) > 0)?implode(' > ', $pagePath):null). '<a href="#">' . $r->page_title . ' (#' . $r->page_ID . ', ' . $r->page_lang . ')</a>';
			} else {
				$r->destination = null;
			}

			if($r->mod_IDFK !== null) {
				$jsonMod = JsonUtils::decode($r->manifest_content);
				$r->destination .= ', Module: <a href="/backend/module/' . $r->mod_name . '">' . (isset($jsonMod->name->{$lang})?$jsonMod->name->{$lang}:$r->mod_name) . '</a>';
			}
		}*/

		$tplVars = array(
			'routes' => $resRoutes,
			'routes_table' => $trRoutes->display(),
			'siteTitle' => 'Routes'
		);

		return $this->generatePageFromTemplate('backend-routes-overview', $tplVars);
	}

	public function renderDestinationColumn($value, $record, $selector, $tableRenderer) {
		$lang = $this->getLocaleHandler()->getLanguage();
		$destination = null;

		if($record->external_source !== null) {
			$destination = 'External: <a href="' . $record->external_source . '">' . $record->external_source . '</a>';
		} elseif($record->redirect_route_IDFK !== null) {
			$destination = 'Route redirect: <a href="#">' . $record->rr_pattern . ' (#' . $record->rrID . ')</a>';
		} elseif($record->page_ID !== null) {
			$pagePath = $this->pageModel->getPagePath($record->page_ID);

			$pagePathHtml = array();

			foreach($pagePath as $id => $title) {
				$pagePathHtml[] = '<a href="/backend/page/' . $id . '">' . $title . '</a>';
			}

			$pagePathHtml[] = ' <a href="#">' . $record->page_title . ' (#' . $record->page_ID . ', ' . $record->page_lang . ')</a>';

			$destination = 'Page: ' . implode(' > ', $pagePathHtml);
		} else {
			$destination = null;
		}

		if($record->mod_IDFK !== null) {
			$jsonMod = JsonUtils::decode($record->manifest_content);
			$destination .= ', Module: <a href="/backend/module/' . $record->mod_name . '">' . (isset($jsonMod->name->{$lang})?$jsonMod->name->{$lang}:$record->mod_name) . '</a>';
		}

		return $destination;
	}

	public function getRouteEdit() {
		$this->abortIfUserHasNotRights('CMS_ROUTES_EDIT');

		$lang = $this->getLocaleHandler()->getLanguage();

		$routeID = $this->route->getParam(0);

		$routeModel = new RouteModel($this->db);
		$routeData = $routeModel->getRouteByID($routeID);

		$pageModel = new PageModel($this->db);

		$pageOptions = array(0 => ' - please choose -');

		/*foreach($pageModel->getAllPages() as $p) {
			$pagePath = $pageModel->getPagePath($p->ID);

			$pageOptions[$p->ID] = $p->title . ' (' . $p->language_codeFK . ((count($pagePath) > 0)?', ' . implode(' > ', $pagePath):null) . ')';
		}*/
		$pageOptions += $pageModel->generatePageTreeOpts();

		$routeOptions = array(0 => '- please choose -');

		foreach($routeModel->getAllRoutes() as $r) {
			if($r->ID == $this->route->getParam(0))
				continue;

			$routeOptions[$r->ID] = $r->pattern;
		}

		$routeTyp = null;

		if($routeData !== null) {
			if($routeData->page_IDFK !== null) {
				$routeTyp = 1;
			} elseif($routeData->redirect_route_IDFK !== null) {
				$routeTyp = 2;
			}
		}

		$moduleModel = new ModuleModel($this->db);
		$moduleOptions = array(0 => '- please choose -');

		foreach($moduleModel->getModulesWithFrontendController() as $mod) {
			$moduleOptions[$mod->ID] = $mod->manifest_content->name->{$lang};
		}

		$tplVars = array(
			'siteTitle' => ($routeData !== null)?'Edit route #' . $routeID:'Create new route',
			'form_status' => ($this->formHelper !== null && $this->formHelper->hasErrors())?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()):null,

			/*'form_title' => ($pageData !== null)?$pageData->getTitle():null,
			'form_language' => ($pageData !== null)?$pageData->getLanguage():null,
			'form_base_page' => ($pageData !== null && $pageData->getBasePage() !== null)?$pageData->getBasePage()->getID():null,
			'form_description' => ($pageData !== null)?$pageData->getDescription():null*/
			'form_pattern' => ($routeData !== null)?substr($routeData->pattern, 1):null,
			'form_robots' => ($routeData !== null)?$routeData->robots:null,
			'form_regexp' => ($routeData !== null)?$routeData->regex:null,
			'form_page' => ($routeData !== null)?$routeData->page_IDFK:null,
			'form_redirect' => ($routeData !== null)?$routeData->redirect_route_IDFK:null,
			'form_route_typ' => $routeTyp,
			'form_module' => ($routeData !== null)?$routeData->mod_IDFK:null,
			'opts_page' => $pageOptions,
			'opts_routes' => $routeOptions,
			'opts_modules' => $moduleOptions,
			'domain' => $this->httpRequest->getHost() . '/'
		);

		if($this->formHelper !== null && $this->formHelper->sent()) {
			$tplVars['form_pattern'] = $this->formHelper->getFieldValue('pattern');
			$tplVars['form_robots'] = $this->formHelper->getFieldValue('robots');
			$tplVars['form_regexp'] = $this->formHelper->getFieldValue('regexp');
			$tplVars['form_page'] = $this->formHelper->getFieldValue('page');
			$tplVars['form_redirect'] = $this->formHelper->getFieldValue('redirect');
			$tplVars['form_route_typ'] = $this->formHelper->getFieldValue('route_typ');
		}


		return $this->generatePageFromTemplate('backend-route-edit', $tplVars);
	}

	public function processRouteEdit() {
		$this->abortIfUserHasNotRights('CMS_ROUTES_EDIT');

		//$coreModel = new CoreModel($this->db);
		$pageModel = new PageModel($this->db);
		$routeModel = new RouteModel($this->db);
		$moduleModel = new ModuleModel($this->db);

		$pageOptions = array();

		foreach($pageModel->getAllPages() as $p) {
			$pageOptions[$p->ID] = $p->language_codeFK . ', ' . $p->title;
		}

		$routeOptions = array();

		foreach($routeModel->getAllRoutes() as $r) {
			if($r->ID == $this->route->getParam(0))
				continue;

			$routeOptions[$r->ID] = $r->pattern;
		}

		$moduleOptions = array();

		foreach($moduleModel->getModulesWithFrontendController() as $m) {
			$routeOptions[$m->ID] = $m->ID;
		}

		$this->formHelper = new FormHelper(FormHelper::METHOD_POST);
		$this->formHelper->addField('pattern', null, FormHelper::TYPE_STRING, true, array(
			'missingError' => 'Please insert a pattern for this route'
		));
		$this->formHelper->addField('page', null, FormHelper::TYPE_OPTION, false, array(
			'invalidError' => 'Please select a valid page',
			'options' => $pageOptions
		));
		$this->formHelper->addField('robots', null, FormHelper::TYPE_STRING, false);
		$this->formHelper->addField('regexp', null, FormHelper::TYPE_CHECKBOX);
		$this->formHelper->addField('route_typ', null, FormHelper::TYPE_OPTION);
		$this->formHelper->addField('redirect', null, FormHelper::TYPE_OPTION, false, array(
			'invalidError' => 'Please select a valid page',
			'options' => $pageOptions
		));

		$this->formHelper->addField('module', null, FormHelper::TYPE_OPTION, false, array(
			'invalidError' => 'Please select a valid module',
			'options' => $moduleOptions
		));

		if(!$this->formHelper->sent() || !$this->formHelper->validate())
			return $this->getRouteEdit();

		$patternStr = $this->formHelper->getFieldValue('pattern');

		if(StringUtils::startsWith($patternStr, '/')) {
			$this->formHelper->addError(null, 'The route can not start with a slash (/)');
			return $this->getRouteEdit();
		}

		if(preg_match('@^[A-Za-z0-9\-\._/?#\@&+=]+$@', $patternStr) === 0) {
			$this->formHelper->addError(null, 'The route should only have alphanumeric characters and -._/?#@&+= in it');
			return $this->getRouteEdit();
		}

		if($patternStr === 'backend' || StringUtils::startsWith($patternStr, 'backend/') === true) {
			$this->formHelper->addError(null, 'The route should not start with "backend/". This URI node is reserved by the CMS');
			return $this->getRouteEdit();
		}

		// save settings
		$routeTyp = $this->formHelper->getFieldValue('route_typ');

		$stmntUpdate = $this->db->prepare("
			INSERT INTO route
				SET ID = ?, pattern = ?, regex = ?, page_IDFK = ?, mod_IDFK = ?, robots = ?, redirect_route_IDFK = ?
			ON DUPLICATE KEY UPDATE
				pattern = ?, regex = ?, page_IDFK = ?, mod_IDFK = ?, robots = ?, redirect_route_IDFK = ?

		");

		$resUpdate = $this->db->update($stmntUpdate, array(
			$this->route->getParam(0),
			'/' . $patternStr,
			$this->formHelper->getFieldValue('regexp'),
			($routeTyp == 1)?$this->formHelper->getFieldValue('page'):null,
			($this->formHelper->getFieldValue('module') == 0)?null:$this->formHelper->getFieldValue('module'),
			$this->formHelper->getFieldValue('robots'),
			($routeTyp == 2)?$this->formHelper->getFieldValue('redirect'):null,

			// UPDATE
			'/' . $patternStr,
			$this->formHelper->getFieldValue('regexp'),
			($routeTyp == 1)?$this->formHelper->getFieldValue('page'):null,
			($this->formHelper->getFieldValue('module') == 0)?null:$this->formHelper->getFieldValue('module'),
			$this->formHelper->getFieldValue('robots'),
			($routeTyp == 2)?$this->formHelper->getFieldValue('redirect'):null
		));

		RequestHandler::redirect('/backend/routes');
	}

}

/* EOF */