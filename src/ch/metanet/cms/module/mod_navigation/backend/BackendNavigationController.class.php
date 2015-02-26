<?php

namespace ch\metanet\cms\module\mod_navigation\backend;

use ch\metanet\cms\common\CmsModuleBackendController;
use ch\metanet\cms\common\CmsUtils;
use ch\metanet\cms\controller\backend\ModuleController;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\model\CoreModel;
use ch\metanet\cms\model\RouteModel;
use ch\metanet\cms\module\mod_navigation\model\NavigationModel;
use ch\metanet\cms\module\mod_news\model\FAQModel;
use ch\metanet\cms\tablerenderer\Column;
use ch\metanet\cms\tablerenderer\DateColumnDecorator;
use ch\metanet\cms\tablerenderer\RewriteColumnDecorator;
use ch\metanet\cms\tablerenderer\SortColumnDecorator;
use ch\metanet\cms\tablerenderer\TableRenderer;
use ch\metanet\formHandler\rule\RequiredRule;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpResponse;
use ch\timesplinter\core\RequestHandler;
use ch\timesplinter\formhelper\FormHelper;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class BackendNavigationController extends CmsModuleBackendController
{
	/** @var  FormHelper */
	private $formHelper;
	private $navigationModel;

	public function __construct(BackendController $moduleController, $moduleName)
	{
		parent::__construct($moduleController, $moduleName);

		$this->controllerRoutes = array(
			'/' => array(
				'*' => 'getModuleOverview'
			),
			'/entry/(\d+)/edit' => array(
				'GET' => 'getEditNavEntry',
				'POST' => 'postEditNavEntry'
			),
			'/entry/add' => array(
				'GET' => 'getEditNavEntry',
				'POST' => 'postEditNavEntry'
			),
			'/entry/(\d+)/delete' => array(
				'GET' => 'deleteNavEntry'
			),

			'/nav/(\d+)/edit' => array(
				'GET' => 'getEditNav',
				'POST' => 'postEditNav'
			),
			'/nav/add' => array(
				'GET' => 'getEditNav',
				'POST' => 'postEditNav'
			),
			'/nav/(\d+)/delete' => array(
				'GET' => 'deleteNav'
			),

			'/nav/(\d+)/entry/(\d+)/edit' => array(
				'GET' => 'getEditNavHasEntry',
				'POST' => 'postEditNavHasEntry'
			),

			'/nav/(\d+)/edit/update-order' => array(
				'POST' => 'postUpdateNavEntries'
			)
		);

		$this->navigationModel = new NavigationModel($this->cmsController->getDB());
	}

	public function getModuleOverview() {
		$sqlNavigations = "
			SELECT ID, name, (SELECT COUNT(*) FROM navigation_has_entry WHERE navigation_IDFK = n.ID) entries
			FROM navigation n
			ORDER BY name
		";

		$sqlNewsEntriesParams = array(

		);

		$tableNavigations = new TableRenderer('navs', $this->cmsController->getDB(), $sqlNavigations);
		$tableNavigations->setOptions(array(
			TableRenderer::OPT_EDIT => $this->baseLink . '/nav/{ID}/edit',
			TableRenderer::OPT_DELETE => $this->baseLink . '/nav/{ID}/delete'
		));
		$tableNavigations->setColumns(array(
			new Column('ID', '#'),
			new Column('name', 'Name'),
			new Column('entries', 'Entries')
		));

		$sqlNavigationEntries = "
			SELECT ne.ID, ne.language_codeFK, ne.title, ne.route_IDFK, r.pattern route, ne.external_link, (SELECT COUNT(*) FROM navigation_has_entry WHERE navigation_entry_IDFK = ne.ID) occurrences
			FROM navigation_entry ne
			LEFT JOIN route r ON r.ID = ne.route_IDFK
			ORDER BY ne.title
		";

		$sqlParams = array($this->cmsController->getCore()->getLocaleHandler()->getLanguage());

		$columnTitle = new Column('ne.title', 'Title');
		$columnTitle->setFilter();
		
		$tableNavigationEntries = new TableRenderer('naventries', $this->cmsController->getDB(), $sqlNavigationEntries);
		$tableNavigationEntries->setOptions(array(
			TableRenderer::OPT_EDIT => $this->baseLink . '/entry/{ID}/edit',
			TableRenderer::OPT_DELETE => $this->baseLink . '/entry/{ID}/delete'
		));
		$tableNavigationEntries->setColumns(array(
			new Column('ID', '#'),
			$columnTitle,
			new Column('ne.language_codeFK', 'Language'),
			new Column('route', 'Route', array(new RewriteColumnDecorator('<a href="{route}">{route}</a>'))),
			new Column('external_link', 'External source'),
			new Column('occurrences', 'Occurrences')
		));

		$tplVars = array(
			'siteTitle' => 'Module: Navigation',
			'news_entries_table' => $tableNavigations->display($sqlNewsEntriesParams),
			'news_cats_table' => $tableNavigationEntries->display(/*$sqlParams*/)
		);

		return $this->renderModuleContent('mod-navigation-overview', $tplVars);
	}

	public function getEditNavEntry($params) {
		$navEntry = isset($params[0])?$this->navigationModel->getNavEntryByID($params[0]):null;

		$routeModel = new RouteModel($this->cmsController->getDB());
		$coreModel = new CoreModel($this->cmsController->getDB());
		$categoriesOpts = array();

		foreach($routeModel->getAllRoutes() as $nc) {
			$categoriesOpts[$nc->ID] = $nc->pattern;
		}

		if($this->formHelper !== null && $this->formHelper->sent()) {
			$formValues = array(
				'form_language' => $this->formHelper->getFieldValue('language'),
				'form_title' => $this->formHelper->getFieldValue('title'),
				'form_route' => $this->formHelper->getFieldValue('route'),
				'form_external_link' => $this->formHelper->getFieldValue('external_link')
			);
		} else {
			$formValues = array(
				'form_language' => isset($navEntry->language_codeFK)?$navEntry->language_codeFK:null,
				'form_title' => isset($navEntry->title)?$navEntry->title:null,
				'form_route' => isset($navEntry->route_IDFK)?$navEntry->route_IDFK:null,
				'form_external_link' => isset($navEntry->external_link)?$navEntry->external_link:null
			);
		}

		$tplVars = array_merge(array(
			'siteTitle' => isset($params[0])?'Edit navigation entry #' . $params[0]:'Create navigation entry',
			'opt_routes' => $categoriesOpts,
			'opt_languages' => array_merge(array(0 => '- please choose - '), $coreModel->getLanguages()),
			'form_message' => ($this->formHelper !== null && $this->formHelper->sent() && $this->formHelper->hasErrors())?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()):null
		), $formValues);

		return $this->renderModuleContent('mod-nav-edit-entry', $tplVars);
	}

	public function postEditNavEntry($params) {
		$this->formHelper = $this->generateFormEditNavEntry();

		if(!$this->formHelper->sent() || $this->formHelper->validate() === false) {
			return $this->getEditNavEntry($params);
		}

		try {
			$stmntUpdate = $this->cmsController->getDB()->prepare("
				INSERT INTO navigation_entry SET
					ID = ?, title = ?, language_codeFK = ?, route_IDFK = ?, external_link = ?
				ON DUPLICATE KEY UPDATE
					title = ?, language_codeFK = ?, route_IDFK = ?, external_link = ?
			");

			$this->cmsController->getDB()->update($stmntUpdate, array(
				isset($params[0])?$params[0]:null,
				$this->formHelper->getFieldValue('title'),
				$this->formHelper->getFieldValue('language'),
				$this->formHelper->getFieldValue('route'),
				$this->formHelper->getFieldValue('external_link'),

				// UPDATE
				$this->formHelper->getFieldValue('title'),
				$this->formHelper->getFieldValue('language'),
				$this->formHelper->getFieldValue('route'),
				$this->formHelper->getFieldValue('external_link')
			));

			RequestHandler::redirect($this->baseLink);
		} catch(\Exception $e) {
			$this->formHelper->addError(null, 'Could not save navigation entry: ' . $e->getMessage());

			return $this->getEditNavEntry($params);
		}


	}

	private function generateFormEditNavEntry() {
		$formHelper = new FormHelper(FormHelper::METHOD_POST);

		$routeModel = new RouteModel($this->cmsController->getDB());
		$coreModel = new CoreModel($this->cmsController->getDB());

		$optsRoute = array();

		foreach($routeModel->getAllRoutes() as $r) {
			$optsRoute[$r->ID] = $r->pattern;
		}

		$formHelper->addField('title', null, $formHelper::TYPE_STRING, true, array(
			'missingError' => 'Please fill in an entry title'
		));
		$formHelper->addField('language', null, FormHelper::TYPE_OPTION, true, array(
			'missingError' => 'Please choose a language',
			'invalidError' => 'Please choose a valid language',
			'options' => $coreModel->getLanguages()
		));
		$formHelper->addField('route', null, FormHelper::TYPE_OPTION, false, array(
			'invalidError' => 'Please choose a valid route',
			'options' => $optsRoute
		));
		$formHelper->addField('external_link', null, $formHelper::TYPE_URL, false, array(
			'invalidError' => 'Please fill in a valid URL'
		));

		return $formHelper;
	}

	/*
	 *  CATEGORIES
	 */
	public function getEditNav($params)
	{
		$lang = $this->getEditLanguage();

		$navigation = isset($params[0])?$this->navigationModel->getNavigationByID($params[0]):null;

		if($this->formHelper !== null && $this->formHelper->sent()) {
			$formValues = array(
				'form_name' => $this->formHelper->getFieldValue('name')
			);
		} else {
			$formValues = array(
				'form_name' => ($navigation !== null)?$navigation->name:null
			);
		}

		$stmntEntries = $this->cmsController->getDB()->prepare("
			SELECT ne.ID, ne.title, ne.route_IDFK, r.pattern route_destination, ne.external_link, nhe.sort, nhe.parent_navigation_entry_IDFK, ne2.title parent_title, ne.language_codeFK
			FROM navigation_has_entry nhe
			LEFT JOIN navigation_entry ne ON ne.ID = nhe.navigation_entry_IDFK
			LEFT JOIN navigation_entry ne2 ON ne2.ID = nhe.parent_navigation_entry_IDFK
			LEFT JOIN route r ON r.ID = ne.route_IDFK
			WHERE ne.language_codeFK = ? AND nhe.navigation_IDFK = ?
			ORDER BY nhe.parent_navigation_entry_IDFK, nhe.sort
		");

		$langEntriesHtml = null;

		if(isset($params[0]) === true) {
			$resNav = $this->cmsController->getDB()->select($stmntEntries, array($this->cmsController->getLocaleHandler()->getLanguage(), $params[0]));
			$langEntriesHtml = $this->generateNavList($resNav, $navigation->ID);
		}
		
		// Get nav entries
		$stmntNewEntries = $this->cmsController->getDB()->prepare("
			SELECT ne.ID, ne.title, ne.route_IDFK, r.pattern route_destination, ne.external_link, ne.language_codeFK
			FROM navigation_entry ne
			LEFT JOIN route r ON r.ID = ne.route_IDFK
			WHERE ne.language_codeFK = ?
			ORDER BY ne.title
		");

		$optionEntries = array(0 => 'please choose');

		$resNewEntries = $this->cmsController->getDB()->select($stmntNewEntries, array($lang));

		foreach($resNewEntries as $e) {
			$optionEntries[$e->ID] = $e->title . ' (' . $e->route_destination . ')';
		}

		$entriesChosen = array();
		$entriesPool = array();

		if(isset($params[0])) {
			$navigationEntries = $this->navigationModel->getEntriesByNavID($params[0]);

			foreach($navigationEntries as $e) {
				$entriesChosen[$e->navigation_entry_IDFK] = $e->title . '<span>' . $e->pattern . '</span>';
			}
		}

		$allEntries = $this->navigationModel->getAllNavigationEntries();
		$entriesChosenKeys = array_keys($entriesChosen);

		foreach($allEntries as $e) {
			if(in_array($e->ID, $entriesChosenKeys))
				continue;

			$entriesPool[$e->ID] = $e->title . '<span>' . $e->pattern . '</span>';
		}

		$tplVars = array_merge(array(
			'siteTitle' => isset($params[0])?'Edit navigation #' . $params[0]:'Create navigation',
			'form_message' => ($this->formHelper !== null && $this->formHelper->sent() && $this->formHelper->hasErrors())?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()):null,
			'lang_entries' => $langEntriesHtml,
			'option_entries' => $optionEntries,
			'entries_chosen' => $entriesChosen,
			'entries_pool' => $entriesPool
		), $formValues);

		return $this->renderModuleContent('mod-navigation-edit-nav', $tplVars);
	}



	public function postEditNav($params) {
		$this->formHelper = $this->generateFormEditNav();

		if(!$this->formHelper->sent() || $this->formHelper->validate() === false)
			return $this->getEditNav($params);

		try {
			$this->cmsController->getDB()->beginTransaction();

			$stmntUpdate = $this->cmsController->getDB()->prepare("
				INSERT INTO navigation SET
					ID = ?,
					name = ?
				ON DUPLICATE KEY UPDATE
					name = ?
			");

			$insertID = $this->cmsController->getDB()->insert($stmntUpdate, array(
				isset($params[0])?$params[0]:null,
				$this->formHelper->getFieldValue('name'),

				// UPDATE
				$this->formHelper->getFieldValue('name')
			));

			$navID = isset($params[0])?$params[0]:$insertID;

			//var_dump($this->formHelper->getFieldValue('entries'));

			if(isset($params[0])) {
				$stmntDelete = $this->cmsController->getDB()->prepare("
					DELETE FROM navigation_has_entry WHERE navigation_IDFK = ? AND parent_navigation_entry_IDFK IS NULL
				");

				$this->cmsController->getDB()->delete($stmntDelete, array($params[0]));
			}

			$stmntInsert = $this->cmsController->getDB()->prepare("
				INSERT INTO navigation_has_entry SET navigation_IDFK = ?, navigation_entry_IDFK = ?, sort = ?
			");

			foreach($this->formHelper->getFieldValue('entries') as $i => $e) {
				$this->cmsController->getDB()->insert($stmntInsert, array(
					$navID,
					$e,
					($i+1)
				));
			}

			$this->cmsController->getDB()->commit();

			RequestHandler::redirect($this->baseLink);
		} catch(\Exception $e) {
			$this->formHelper->addError(null, 'Could not save navigation: ' . $e->getMessage());
			$this->cmsController->getDB()->rollBack();
		}

		return $this->getEditNav($params);
	}
	
	public function deleteNav($params) {
		if(isset($params[0]) === false)
			RequestHandler::redirect($this->baseLink);

		$message = null;
		
		if($this->cmsController->getHttpRequest()->getVar('confirm') !== null) {
			try {
				$this->navigationModel->deleteNavigation($params[0]);
				
				RequestHandler::redirect($this->baseLink);
			} catch(\Exception $e) {
				$message = '<div class="form-error">Could not delete navigation: ' . $e->getMessage() . '</div>';
			}
		}
		
		$navigation = $this->navigationModel->getNavigationByID($params[0]);
		
		$tplVars = array(
			'siteTitle' => 'Delete navigation "' . $navigation->name . '"',
			'nav' => $navigation,
			'message' => $message
		);

		return $this->renderModuleContent('mod-navigation-delete-nav', $tplVars);
	}

	public function deleteNavEntry($params) {
		if(isset($params[0]) === false)
			RequestHandler::redirect($this->baseLink);

		if(($navigationEntry = $this->navigationModel->getNavigationEntry($params[0])) === null)
			throw new HttpException('Navigation entry not found', 404);
		
		$message = null;

		if($this->cmsController->getHttpRequest()->getVar('confirm') !== null) {
			try {
				$this->navigationModel->deleteNavigationEntry($params[0]);

				RequestHandler::redirect($this->baseLink);
			} catch(\Exception $e) {
				$message = '<div class="form-error">Could not delete navigation: ' . $e->getMessage() . '</div>';
			}
		}

		$tplVars = array(
			'siteTitle' => 'Delete navigation entry "' . $navigationEntry->title . '"',
			'nav' => $navigationEntry,
			'message' => $message
		);

		return $this->renderModuleContent('mod-navigation-delete-nav-entry', $tplVars);
	}

	private function generateFormEditNav() {
		$navEntries = $this->navigationModel->getAllNavigationEntries();
		$entryOptions = array();

		foreach($navEntries as $ne) {
			$entryOptions[] = $ne->ID;
		}

		$formHelper = new FormHelper(FormHelper::METHOD_POST);

		$formHelper->addField('name', null, $formHelper::TYPE_STRING, true, array(
			'missingError' => 'Please fill in a category name'
		));
		$formHelper->addField('entries', null, FormHelper::TYPE_MULTIOPTIONS, false, array(
			'invalidError' => 'Please choose valid navigation entries',
			'options' => $entryOptions
		));

		return $formHelper;
	}

	public function getEditNavHasEntry($params) {
		$entriesChosen = array();
		$entriesPool = array();

		$form_hidden = 0;

		if(isset($params[0])) {
			$formHiddenStmnt = $this->cmsController->getDB()->prepare("
				SELECT hidden FROM navigation_has_entry WHERE navigation_IDFK = ? AND navigation_entry_IDFK = ?
			");

			$resFormHidden = $this->cmsController->getDB()->select($formHiddenStmnt, array(
				$params[0],
				$params[1]
			));

			if(count($resFormHidden) > 0)
				$form_hidden = $resFormHidden[0]->hidden;

			$navigationEntries = $this->navigationModel->getEntriesByNavID($params[0], null, $params[1]);

			foreach($navigationEntries as $e) {
				$entriesChosen[$e->navigation_entry_IDFK] = $e->title . '<span>' . $e->pattern . '</span>';
			}
		}

		$allEntries = $this->navigationModel->getAllNavigationEntries();
		$entriesChosenKeys = array_keys($entriesChosen);

		foreach($allEntries as $e) {
			if(in_array($e->ID, $entriesChosenKeys))
				continue;

			$entriesPool[$e->ID] = $e->title . '<span>' . $e->pattern . '</span>';
		}

		$tplVars = array(
			'siteTitle' => 'Edit entry #' . $params[1] . ' in navigation  #' . $params[0],
			'form_message' => ($this->formHelper !== null && $this->formHelper->sent() && $this->formHelper->hasErrors())?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()):null,
			'form_hidden' => ($this->formHelper !== null && $this->formHelper->sent() && $this->formHelper->hasErrors())?$this->formHelper->getFieldValue('hidden'):$form_hidden,
			'entries_chosen' => $entriesChosen,
			'entries_pool' => $entriesPool
		);

		return $this->renderModuleContent('mod-navigation-edit-nav-has-entry', $tplVars);
	}



	public function postEditNavHasEntry($params) {
		$this->formHelper = $this->generateFormEditNavHasEntry();

		if(!$this->formHelper->sent() || $this->formHelper->validate() === false)
			return $this->getEditNav($params);

		try {
			$this->cmsController->getDB()->beginTransaction();

			$navID = $params[0];
			$entryID = $params[1];

			// Get old hidden states
			$stmntHidden = $this->cmsController->getDB()->prepare("
				SELECT navigation_entry_IDFK, hidden FROM navigation_has_entry WHERE navigation_IDFK = ? AND parent_navigation_entry_IDFK = ?
			");

			$resHidden = $this->cmsController->getDB()->select($stmntHidden, array(
				$navID, $entryID
			));

			$hiddenStates = array();

			foreach($resHidden as $h) {
				$hiddenStates[$h->navigation_entry_IDFK] = $h->hidden;
			}

			$stmntDelete = $this->cmsController->getDB()->prepare("
				DELETE FROM navigation_has_entry WHERE navigation_IDFK = ? AND parent_navigation_entry_IDFK = ?
			");

			$this->cmsController->getDB()->delete($stmntDelete, array($navID, $entryID));

			$stmntInsert = $this->cmsController->getDB()->prepare("
				INSERT INTO navigation_has_entry SET navigation_IDFK = ?, navigation_entry_IDFK = ?, parent_navigation_entry_IDFK = ?, sort = ?, hidden = ?
			");

			foreach($this->formHelper->getFieldValue('entries') as $i => $e) {
				$this->cmsController->getDB()->insert($stmntInsert, array(
					$navID,
					$e,
					$entryID,
					($i+1),
					(isset($hiddenStates[$e]) && $hiddenStates[$e] == 1)?1:0 // TODO
				));
			}

			$stmntUpdateThisEntry = $this->cmsController->getDB()->prepare("
				UPDATE navigation_has_entry SET hidden = ? WHERE navigation_IDFK = ? AND navigation_entry_IDFK = ?
			");

			$this->cmsController->getDB()->update($stmntUpdateThisEntry, array(
				$this->formHelper->getFieldValue('hidden'), $navID, $entryID
			));

			$this->cmsController->getDB()->commit();
		} catch(\Exception $e) {

			if($e->getCode() === 23000) {
				$errorMsg = 'The navigation entry <b>#' . $entryID . '</b> is already used in this navigation. Each navigation entry can only be used once per navigation.';
			} else {
				$errorMsg = 'Could not save navigation: ' . $e->getMessage();
			}

			$this->formHelper->addError(null, $errorMsg);
			$this->cmsController->getDB()->rollBack();

			return $this->getEditNavHasEntry($params);
		}

		RequestHandler::redirect($this->baseLink . '/nav/' . $navID . '/edit');
	}

	private function generateFormEditNavHasEntry() {
		$navEntries = $this->navigationModel->getAllNavigationEntries();
		$entryOptions = array();

		foreach($navEntries as $ne) {
			$entryOptions[] = $ne->ID;
		}

		$formHelper = new FormHelper(FormHelper::METHOD_POST);

		$formHelper->addField('hidden', null, FormHelper::TYPE_CHECKBOX, false);

		$formHelper->addField('entries', null, FormHelper::TYPE_MULTIOPTIONS, false, array(
			'invalidError' => 'Please choose valid navigation entries',
			'options' => $entryOptions
		));

		return $formHelper;
	}

	/**
	 * @param array $result
	 * @param $navID
	 * @param null $parentEntry
	 * @return null|string
	 */
	private function generateNavList(array $result, $navID, $parentEntry = null) {
		$hasEntries = false;
		$htmlList = '';

		foreach($result as $r) {
			if($r->parent_navigation_entry_IDFK != $parentEntry)
				continue;

			$hasEntries = true;
			$htmlList .= '<li id="entry-' . $r->ID . '"><a href="' . $this->baseLink . '/nav/' . $navID . '/entry/' . $r->ID . '/edit">' . $r->title. ' <em>' . $r->route_destination . '</em></a>' . $this->generateNavList($result, $navID, $r->ID) . '</li>';
		}

		return ($hasEntries || $parentEntry === null)?'<ul>' . $htmlList . '</ul>':null;
	}
}

/* EOF */