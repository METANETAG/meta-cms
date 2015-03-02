<?php

namespace ch\metanet\cms\controller\common;

use ch\metanet\cms\common\CmsAuthHandlerDB;
use ch\metanet\cms\common\BackendNavigationInterface;
use ch\metanet\cms\common\CmsView;
use ch\metanet\cms\common\PluginManager;
use ch\metanet\cms\locale\PoParser;
use ch\metanet\cms\locale\PoWriter;
use ch\metanet\cms\model\ModuleModel;
use ch\timesplinter\auth\AuthHandlerDB;
use timesplinter\tsfw\common\JsonUtils;
use ch\timesplinter\controller\FrameworkController;
use ch\timesplinter\core\Core;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\HttpResponse;
use ch\timesplinter\core\Route;
use timesplinter\tsfw\db\DBConnect;
use timesplinter\tsfw\db\DBFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use timesplinter\tsfw\i18n\common\AbstractTranslator;
use timesplinter\tsfw\i18n\common\Localizer;
use timesplinter\tsfw\i18n\gettext\GetTextTranslator;

/**
 * This controller is the very basic controller for the metanet cms. It provides us a db connection and some other
 * stuff very basic for the functionality of the cms.
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
abstract class CmsController extends FrameworkController
{
	private $activeHtmlIds;
	
	protected $db;
	/** @var AuthHandlerDB */
	protected $auth;
	/** @var Localizer */
	protected $localizer;
	protected $translator;
	protected $pluginManager;
	protected $eventDispatcher;
	protected $loadedModules;
	
	protected $moduleModel;

	/** @var CmsView */
	protected $cmsView;
	protected $cmsSettings;

	/**
	 * {@inheritdoc}
	 */
	public function __construct(Core $core, HttpRequest $httpRequest, Route $route)
	{
		parent::__construct($core, $httpRequest, $route);
		
		$this->cmsSettings = $this->getSettings('cms');
		$dbSettings = $this->getSettings('db');
		
		$this->eventDispatcher = new EventDispatcher();

		if (!defined('LC_MESSAGES')) define('LC_MESSAGES', 5);

		$this->localizer = Localizer::fromAcceptedLanguages($this->httpRequest->getAcceptLanguage(), array(
			LC_MESSAGES => array('de_CH' => 'de_DE', 'de_AT' => 'de_DE', 'de_LI' => 'de_DE', 'de' => 'de_DE')
		));
		
		$this->translator = $this->getTranslator($this->core->getSiteRoot() . 'locale' . DIRECTORY_SEPARATOR);
		$this->translator->bindTextDomain('backend', 'UTF-8');
		
		$this->db = DBFactory::getNewInstance($dbSettings->type, new DBConnect(
			$dbSettings->host,
			$dbSettings->database,
			$dbSettings->user,
			$dbSettings->password
		));

		$this->auth = new CmsAuthHandlerDB($this->db, $this->core->getSessionHandler(), array(
			'login_site' => $this->core->getSettings()->logincontroller->login_page,
			'session_max_idle_time' => $this->core->getSettings()->logincontroller->session_max_idle_time
		));
		$this->pluginManager = new PluginManager($this);
		
		$this->moduleModel = new ModuleModel($this->db);
		$this->loadedModules = array();
		
		/*if($this->auth->isLoggedIn()) {
			// TODO use reparate DB connection so it doesnt affect the cms one
			$this->db->addListener(new DBRevisionListener($this->core->getSiteRoot() . 'revision/'));
		}*/

		$this->loadNeededModules();
	}

	/**
	 * Load modules needed for the current request (e.g. Modules which implements the EventSubscriberInterface)
	 * @return void
	 */
	protected abstract function loadNeededModules();

	/**
	 * Renders a page with the current routeID as templatefile name. You can provide template variables that can be
	 * used in the template file.
	 * @param array $tplVars Variables that should be accessable in the template files
	 * @param int $httpStatusCode
	 * @return HttpResponse The response
	 */
	public function generatePage($tplVars = array(), $httpStatusCode = 200)
	{
		$routeID = $this->route->id;

		return $this->generatePageFromTemplate($routeID, $tplVars, $httpStatusCode);
	}

	public function generateErrorPage(\Exception $e)
	{
		$env = $this->currentDomain->environment;
		$httpErrorCode = ($e instanceof HttpException)?$e->getCode():500;

		$message = null;
		
		if($httpErrorCode === 500) {
			$message = '<p>An internal server error has occurred.</p>';
		} elseif($httpErrorCode === 404) {
			$message = '<p>Could not find page.</p>';
		} elseif($httpErrorCode === 403) {
			$message = '<p>Access denied for this page.</p>';
		} else {
			$message = '<p>An unknown error has occurred.</p>';
		}
		
		$userData = $this->auth->getUserData();
		$username = ($this->auth->isLoggedIn())?$userData->username:null;
		$tplVars = array(
			'siteTitle' => 'Error ' . $httpErrorCode,
			'meta_description' => null,
			'error' => $e,
			'message' => $message,
            'area_head' => null,
            'area_body' => null,
			'nav_modules' => array(),
			'username' => $username,
			'js_revision' => isset($this->core->getSettings()->cms->{$env}->js_revision)?$this->core->getSettings()->cms->{$env}->js_revision:'v1',
			'css_revision' => isset($this->core->getSettings()->cms->{$env}->css_revision)?$this->core->getSettings()->cms->{$env}->css_revision:'v1',
			'debug_information' => null
		);

		if($this->core->getSettings()->core->environments->{$env}->debug === true) {
			$tplVars['debug_information'] = '<pre><b>Exception of type ' . get_class($e) . '</b>' . PHP_EOL .
			    'Message: ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')' . PHP_EOL . PHP_EOL .
			    'Thrown in file ' . $e->getFile() . ' on line ' . $e->getLine() . PHP_EOL . PHP_EOL .
			     print_r($e->getTraceAsString(), true) .
				'</pre>';
		}
		
		return $this->generatePageFromTemplate('error.html', $tplVars, $httpErrorCode);
	}

	/**
	 * @param string $tplName
	 * @param array $tplVars
	 * @param int $httpStatusCode
	 *
	 * @return HttpResponse
	 */
	public function generatePageFromTemplate($tplName, array $tplVars = array(), $httpStatusCode = 200)
	{
		$html = $this->renderTemplate($tplName, $tplVars);

		$headers = array(
			'Content-Type' => 'text/html; charset=UTF-8',
			'Content-Language' => $this->core->getLocaleHandler()->getLanguage()
		);

		return $this->generateResponse($httpStatusCode, $html, $headers);
	}

	protected function generateResponse($httpStatusCode, $content = null, array $headers = array())
	{
		return new HttpResponse($httpStatusCode, $content, $headers);
	}

	public function getLocaleHandler()
	{
		return $this->core->getLocaleHandler();
	}

	public function getErrorHandler()
	{
		return $this->core->getErrorHandler();
	}

	/**
	 * @param $hookName
	 *
	 * @return mixed
	 */
	public function invokeCmsHook($hookName)
	{
		return call_user_func_array(array($this->pluginManager,'invokeHook'), func_get_args());
	}

	/**
	 * @param string $htmlId The HTML id that should be active
	 */
	public function markHtmlIdAsActive($htmlId)
	{
		$this->activeHtmlIds[] = $htmlId;
	}

	/**
	 * @return array
	 */
	public function getActiveHtmlIds()
	{
		return $this->activeHtmlIds;
	}

	/**
	 * This method renders a template.
	 *
	 * @param string $pageHtml
	 * @param array $tplVars Variables that should be accessible in the template files
	 *
	 * @return string The rendered template
	 */
	protected function renderBasicTemplate($pageHtml, $tplVars = array())
	{
		$currentEnv = $this->core->getCurrentDomain()->environment;
		
		$defaultTplVars = array(
			'_core' =>  $this->core,
			'_auth' => $this->auth,
			'page_html' => $pageHtml,
			'logged_in' => $this->auth->isLoggedIn(),
			'username' => null,
			'siteTitle' => null,
			'scripts_footer' => null,
			'admin_bar' => null,
			'meta_description' => null,
			'js_revision' => isset($this->core->getSettings()->cms->{$currentEnv}->js_revision)?$this->core->getSettings()->cms->{$currentEnv}->js_revision:'v1',
			'css_revision' => isset($this->core->getSettings()->cms->{$currentEnv}->css_revision)?$this->core->getSettings()->cms->{$currentEnv}->css_revision:'v1',
			'area_head' => null,
			'area_body' => null,
			'cms_page' => null
		);
		
		$lang = $this->core->getLocaleHandler()->getLanguage();
		
		$navModules = array();

		foreach($this->moduleModel->getAllModules() as $module) {
			if($module->backendcontroller === null)
				continue;

			$module->display_name = isset($module->manifest_content->name->$lang)?$module->manifest_content->name->$lang:$module->name;
			$navModules[] = $module;
		}

		usort($navModules, function($a, $b) {
			return ($a->display_name > $b->display_name);
		});
		
		if($this->auth->isLoggedIn()) {
			$adminBarHtml = '<div class="mfadminbar">
				<a href="/backend"><img class="adminbar-logo" src="/images/adminbar-logo.png" alt=""></a>
				<ul class="adminbar-nav">
					<li class="more"><a href="#" id="nav-general">General</a>
						<ul class="adminbar-nav-sub">
							<li><a href="/backend/general/phpinfo" id="nav-phpinfo">PHP Info</a></li>
						</ul>
					</li>
					<li class="more"><a href="/backend/modules" id="nav-modules">' . $this->translator->_d('backend', 'Modules') . '</a>
						<ul class="adminbar-nav-sub">';
						
						foreach($navModules as $module) {
							$baseModuleLink = '/backend/module/' . $module->name;
							$adminBarHtml .= '<li><a href="' . $baseModuleLink . '" id="nav-' . $module->name . '">' . $module->display_name . '</a>' . $this->renderModuleNavigation($module->backendcontroller, $baseModuleLink, BackendNavigationInterface::DISPLAY_IN_ADMIN_BAR). '</li>';
						}
			
						$adminBarHtml .= '</ul>
					</li>
					<li><a href="/backend/elements" id="nav-elements">' . $this->translator->_d('backend', 'Elements') . '</a></li>
					<li><a href="/">' . $this->translator->_d('backend', 'Inline editing') . '</a></li>
				</ul>
				<ul class="adminbar-user">
					<li><a href="/backend/myaccount">' . $this->auth->getUserData()->username . '</a></li>
					<li class="user-logout"><a href="/backend/logout"><span></span></a></li>
				</ul>
			</div>';
			
			$tplVars['username'] = $this->auth->getUserData()->username;
			$tplVars['admin_bar'] = $adminBarHtml;
		}
		
		return $this->cmsView->render('template.html' , $tplVars + $defaultTplVars);
	}

	/**
	 * Checks if a user has one of the given cms right (XOR) and throw exception if not
	 * @param array|string $rights Single right or array of rights
	 * @throws \ch\timesplinter\core\HttpException
	 */
	public function abortIfUserHasNotRights($rights)
	{
		foreach((array)$rights as $r) {
			if($this->auth->hasCmsRight($r) === true) {
				return;
			}
		}

		if(count($this->auth->getCmsRights()) !== 0)
			$currentRights = implode(', ', $this->auth->getCmsRights());
		else
			$currentRights = 'no rights yet';

		throw new HttpException('You have not the required rights for this action. Required right is one of: ' . implode(', ', (array)$rights) . '. You\'re current rights: ' . $currentRights, 401);
	}

	/**
	 * @param string $moduleBackendController
	 * @param string $baseModuleLink
	 *
	 * @param $scope
	 *
	 * @return null|string
	 */
	protected function renderModuleNavigation($moduleBackendController, $baseModuleLink, $scope)
	{
		if(class_exists($moduleBackendController) === false || in_array('ch\metanet\cms\common\BackendNavigationInterface', class_implements($moduleBackendController)) === false)
			return null;
		
		/** @var BackendNavigationInterface $moduleBackendController */
		if(count($entries = $moduleBackendController::getNavigationEntries($this)) === 0)
			return null;

		$renderedEntries = array();

		foreach($entries as $entry) {
			if(isset($entry['scopes']) === false || $scope & $entry['scopes'])
				$renderedEntries[] = '<li><a href="' . $baseModuleLink . $entry['target'] . '">' . $entry['label'] . '</a></li>';
		}
		
		return count($renderedEntries) > 0 ? '<ul class="nav-module">' . implode(PHP_EOL, $renderedEntries) . '</ul>' : null;
	}

	public function getSettings($settingsName, $componentType = null)
	{
		$moduleConfigPath = $this->core->getSiteRoot() . 'settings' . DIRECTORY_SEPARATOR;
		
		if($componentType !== null)
			$moduleConfigPath .= $componentType . DIRECTORY_SEPARATOR;
		
		$moduleConfigFileName = $settingsName . '.json';
		
		if(file_exists($moduleConfigPath . $moduleConfigFileName) === false)
			return new \stdClass();

		$currentEnv = $this->core->getCurrentDomain()->environment;
		
		$jsonSettings = JsonUtils::decodeFile($moduleConfigPath . $moduleConfigFileName);
		
		if(isset($jsonSettings->$currentEnv) === true)
			return $jsonSettings->$currentEnv;
		elseif(isset($jsonSettings->default) === true)
			return $jsonSettings->default;
		
		return new \stdClass();
	}
	
	public function getModuleSettings($moduleName)
	{
		return $this->getSettings($moduleName, 'modules');
	}

	/**
	 * @param string $directory
	 *
	 * @return AbstractTranslator
	 */
	public function getTranslator($directory)
	{
		$translator = new GetTextTranslator($directory);
		$translator->setPoParser(new PoParser());
		
		$env = $this->currentDomain->environment;
		
		if($this->core->getSettings()->core->environments->{$env}->debug === true) {
			$translator->setPoWriter(new PoWriter());
		}
		
		return $translator;
	}

	/**
	 * @param string $tplFile
	 * @param array $tplVars
	 *
	 * @return string
	 */
	protected abstract function renderTemplate($tplFile, array $tplVars = array());

	/**
	 * @return \ch\timesplinter\db\DB
	 */
	public function getDB()
	{
		return $this->db;
	}

	/**
	 * @return CmsAuthHandlerDB|\ch\timesplinter\auth\AuthHandlerDB
	 */
	public function getAuth()
	{
		return $this->auth;
	}

	public function getCore()
	{
		return $this->core;
	}
	
	public function getCmsSettings()
	{
		return $this->cmsSettings;
	}
	
	public function getCmsView()
	{
		return $this->cmsView;
	}

	public function getHttpRequest()
	{
		return $this->httpRequest;
	}

	public function getRoute()
	{
		return $this->route;
	}

	/**
	 * @return EventDispatcher
	 */
	public function getEventDispatcher()
	{
		return $this->eventDispatcher;
	}
}

/* EOF */