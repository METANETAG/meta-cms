<?php

namespace ch\metanet\cms\controller\backend;

use ch\metanet\cms\common\CMSException;
use ch\metanet\cms\common\CmsUtils;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\model\LoginModel;
use ch\metanet\cms\model\ModuleModel;
use ch\metanet\cms\model\RightGroupModel;
use ch\metanet\cms\tablerenderer\BooleanColumnDecorator;
use ch\metanet\cms\tablerenderer\Column;
use ch\metanet\cms\tablerenderer\TableRenderer;
use ch\timesplinter\auth\AuthHandlerFactory;
use ch\timesplinter\common\JsonUtils;
use ch\timesplinter\core\Core;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\HttpResponse;
use ch\timesplinter\core\RequestHandler;
use ch\timesplinter\core\Route;
use ch\timesplinter\db\DBException;
use ch\timesplinter\formhelper\FormHelper;
use ch\timesplinter\mailer\MailFactory;
use \DateTime;

/**
 * The login controller handles login, password restore, signup
 *
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class AccountController extends BackendController
{
	/** @var $formHelper FormHelper */
	protected $formHelper;
	private $userID;

	public function __construct(Core $core, HttpRequest $httpRequest, Route $route)
	{
		parent::__construct($core, $httpRequest, $route);

		$this->markHtmlIdAsActive('users');
	}
	
	public function getAccountOverview()
	{
		$paramUserID = $this->route->getParam(0);

		/*if($paramUserID == $this->auth->getUserData()->ID)
			RequestHandler::redirect('/backend/myaccount');*/

		$this->userID = ($paramUserID === null)?$this->auth->getUserID():$paramUserID;

		$rightgroupModel = new RightGroupModel($this->db);
		$loginModel = new LoginModel($this->db);

		if(($rightgroupID = $this->httpRequest->getVar('removerightgroup')) !== null) {
			$loginModel->removeRightgroupFromLogin($rightgroupID, $this->userID);
		}

		$rightgroupsAddArr = array(0 => '- please choose -');

		foreach($rightgroupModel->getRightGroups() as $rg) {
			if($loginModel->hasLoginRightGroup($this->userID, $rg->ID) || $rg->ID == 0)
				continue;

			$rightgroupsAddArr[$rg->ID] = $rg->groupname;
		}

		$userData = $loginModel->getLoginByID($this->userID);
		$lastlogin = $userData->lastlogin;
		$confirmed = $userData->confirmed;

		$tplVars = array(
			'siteTitle' => ($this->userID == $this->auth->getUserID())?'My account overview':'Account ' . $userData->email,
			'user_username' => $userData->username,
			'user_email' => $userData->email,
			'lastlogin' => ($lastlogin !== null)?DateTime::createFromFormat('Y-m-d H:i:s', $lastlogin)->format('d.m.Y H:i:s'):'never',
			'confirmed' => ($confirmed !== null)?DateTime::createFromFormat('Y-m-d H:i:s', $confirmed)->format('d.m.Y H:i:s'):'not yet',
			'registered' => DateTime::createFromFormat('Y-m-d H:i:s', $userData->registered)->format('d.m.Y H:i:s'),
			'active' => ($userData->active == 1)?'yes':'no',
			'wronglogins' => $userData->wronglogins,
			'rightgroups' => $loginModel->getRightGroupsByLogin($this->userID),
			'rightgroupsAdd' => $rightgroupsAddArr,
			'rightgroupsAddSelected' => array(0)
		);

		return $this->generatePageFromTemplate('backend-account-overview', $tplVars);
	}

	public function processAccountOverview() {
		$rightgroupModel = new RightGroupModel($this->db);
		$loginModel = new LoginModel($this->db);
		$rightgroupsAddArr = array();

		foreach($rightgroupModel->getRightGroups() as $rg) {
			if($loginModel->hasLoginRightGroup($this->userID, $rg->ID) || $rg->ID == 0)
				continue;

			$rightgroupsAddArr[$rg->ID] = $rg->groupname;
		}

		$this->formHelper = new FormHelper(FormHelper::METHOD_POST, array(
			FormHelper::METHOD_GET, 'addrightgroup'
		));

		$this->formHelper->addField('rightgroup', 0, FormHelper::TYPE_INTEGER, true, array(
			'missingError' => 'Please set a group which should be added',
			'invalidError' => 'Please choose a valid group to be added',
			'options' => $rightgroupsAddArr
		));

		if(!$this->formHelper->sent() || !$this->formHelper->validate())
			return $this->getAccountOverview();

		$rightgroupModel = new RightGroupModel($this->db);

		$rightgroupModel->addRightGroupToUser(
			$this->formHelper->getFieldValue('rightgroup'),
			($this->route->getParam(0) === null)?$this->auth->getUserData():$this->route->getParam(0),
			new DateTime()
		);

		return $this->getAccountOverview();
	}

	public function getAccountsOverview() {
		$status = null;

		if($this->httpRequest->getVar('rgdelete') !== null) {
			$this->deleteRightgroup($this->httpRequest->getVar('rgdelete'));
		}

		if($this->httpRequest->getVar('accdelete') !== null) {
			try {
				$this->deleteAccount($this->httpRequest->getVar('accdelete'));
			} catch(\Exception $e) {
				$status = 'Could not delete account: ' . $e->getMessage();
			}
		}

		$loginModel = new LoginModel($this->db);
		$rightgroupModel = new RightGroupModel($this->db);

		$users = $loginModel->getAllLogins();


		foreach($users as $u) {
			$rgs = $rightgroupModel->getRightGroupByUser($u->ID);

			$rgArr = array();

			foreach($rgs as $r) {
				$rgArr[] = '<a href="/backend/users/rightgroup/' . $r->ID . '/edit">' . $r->groupname;
			}

			$u->confirmed = ($u->confirmed !== null)?$u->confirmed:'not yet';
			$u->lastlogin = ($u->lastlogin !== null)?$u->lastlogin:'never';
			$u->rightgroups = implode(', ', $rgArr);
		}

		$stmntRightGroups = "
			SELECT ID, groupkey, groupname, root
			FROM rightgroup
			WHERE ID != 0
			ORDER BY groupname
		";

		$tableRendererRightgroups = new TableRenderer('rightgroups', $this->db, $stmntRightGroups);
		$tableRendererRightgroups->setColumns(array(
			new Column('ID', '#', array(), true),
			new Column('groupname', 'Name', array(), true),
			new Column('root', 'Root access', array(new BooleanColumnDecorator()), true)
		));
		$tableRendererRightgroups->setOptions(array(
			'edit' => '/backend/users/rightgroup/{ID}/edit',
			'delete' => '/backend/users?rgdelete={ID}'
		));

		$tplVars = array(
			'siteTitle' => 'Accounts',
			'logins' => $users,
			'right_groups' => $tableRendererRightgroups->display(),
			'status' => $status
		);

		return $this->generatePageFromTemplate('backend-accounts', $tplVars);
	}

	public function getEditRightgroup() {
		$this->abortIfUserHasNotRights('BACKEND_RIGHTGROUPS_EDIT');

		$formVars = array(
			'form_name' => null,
			'form_key' => null,
			'form_rights' => array(),
			'form_root' => 0,
			'form_status' => ($this->formHelper !== null && $this->formHelper->hasErrors())
				?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()):null
		);

		if($this->formHelper !== null && $this->formHelper->sent()) {
			$formVars['form_name'] = $this->formHelper->getFieldValue('name');
			$formVars['form_key'] = $this->formHelper->getFieldValue('key');
			$formVars['form_root'] = $this->formHelper->getFieldValue('root');
			$formVars['form_rights'] = $this->formHelper->getFieldValue('rights');
		} elseif($this->route->getParam(0) !== null) {
			$rightgroupModel = new RightGroupModel($this->db);
			$rg = $rightgroupModel->getRightGroupByID($this->route->getParam(0));

			$formVars['form_name'] = $rg->groupname;
			$formVars['form_key'] = $rg->groupkey;
			$formVars['form_root'] = $rg->root;
			$formVars['form_rights'] = $rg->cms_rights;
		}

		$moduleModel = new ModuleModel($this->db);

		$rights = array();

		$lang = $this->getLocaleHandler()->getLanguage();

		foreach($moduleModel->getAllModules() as $mod) {
			$rightCat = new \stdClass();
			$rightCat->title = isset($mod->manifest_content->name->$lang)?$mod->manifest_content->name->$lang:$mod->name;
			$rightCat->rights = array();

			if(isset($mod->manifest_content->rights) === false)
				continue;

			foreach($mod->manifest_content->rights as $rKey => $rLang) {
				$label = isset($rLang->$lang)?$rLang->$lang:null;

				$rightCat->rights[$rKey] = ($label !== null)?$label:$rKey;
			}

			$rights[] = $rightCat;
		}

		$tplVars = array(
			'siteTitle' => ($this->route->getParam(0) !== null)?'Edit rightgroup':'Create rightgroup',
			'submit_label' => ($this->route->getParam(0) !== null)?'Save changes':'Create',
			'rights' => $rights

		);

		return $this->generatePageFromTemplate('backend-account-rightgroup-edit', array_merge($tplVars, $formVars));
	}

	public function processEditRightgroup() {
		$this->abortIfUserHasNotRights('BACKEND_RIGHTGROUPS_EDIT');

		$this->formHelper = new FormHelper(FormHelper::METHOD_POST);

		$this->formHelper->addField('name', null, FormHelper::TYPE_STRING, true, array(
			'missingError' => 'Please insert a groupname'
		));
		$this->formHelper->addField('key', null, FormHelper::TYPE_STRING, false);
		$this->formHelper->addField('root', null, FormHelper::TYPE_CHECKBOX, false);
		$this->formHelper->addField('rights', null, FormHelper::TYPE_MULTIOPTIONS, false, array(
			'options' => $this->getAllRights()
		));

		if($this->formHelper->getFieldValue('root') == 1 && !$this->auth->hasCmsRight('BACKEND_RIGHTGROUPS_ROOTING'))
			$this->formHelper->addError('root', 'You have not the right to root a rightgroup.');

		if(!$this->formHelper->sent() || !$this->formHelper->validate())
			return $this->getEditRightgroup();

		$stmntMutate = $this->db->prepare("
			INSERT INTO rightgroup
				SET ID = ?, groupname = ?, groupkey = ?, root = ?
			ON DUPLICATE KEY UPDATE
				groupname = ?, groupkey = ?, root = ?
		");

		$newRgID = $this->db->insert($stmntMutate, array(
			// INSERT
			$this->route->getParam(0),
			$this->formHelper->getFieldValue('name'),
			$this->formHelper->getFieldValue('key'),
			$this->formHelper->getFieldValue('root'),

			// UPDATE
			$this->formHelper->getFieldValue('name'),
			$this->formHelper->getFieldValue('key'),
			$this->formHelper->getFieldValue('root')
		));

		$rgID = ($this->route->getParam(0) !== null)?$this->route->getParam(0):$newRgID;

		$removeRghts = $this->db->prepare("DELETE FROM cms_rightgroup_has_right WHERE rightgroup_IDFK = ?");
		$this->db->delete($removeRghts, array($rgID));

		$stmntInsertRight = $this->db->prepare("
			INSERT INTO cms_rightgroup_has_right
			SET rightgroup_IDFK = ?, cms_right = ?, date_from = NOW()
		");

		foreach($this->formHelper->getFieldValue('rights') as $r) {
			$this->db->insert($stmntInsertRight, array($rgID, $r));
		}

		RequestHandler::redirect('/backend/users');
	}

	public function deleteRightgroup($rightgroupID) {
		try {
			$this->db->beginTransaction();

			$stmntDelLoginHasRg = $this->db->prepare("
				DELETE FROM login_has_rightgroup WHERE rightgroupIDFK = ?
			");
			$this->db->delete($stmntDelLoginHasRg, array($rightgroupID));

			$stmntDelRg = $this->db->prepare("DELETE FROM rightgroup WHERE ID = ?");
			$this->db->delete($stmntDelRg, array($rightgroupID));

			$this->db->commit();
		} catch(\Exception $e) {
			$this->db->rollBack();

			echo 'sorry could not delete rightgroup. Reason: ' . $e->getMessage();
		}
	}


	public function getEditUser() {
		$this->abortIfUserHasNotRights('BACKEND_USERS_EDIT');

		$rightgroupModel = new RightGroupModel($this->db);

		$formVars = array(
			'form_name' => null,
			'form_email' => null,
			'form_rightgroups' => array(),
			'form_active' => 0
		);

		if($this->formHelper !== null && $this->formHelper->sent()) {
			$formVars['form_name'] = $this->formHelper->getFieldValue('name');
			$formVars['form_email'] = $this->formHelper->getFieldValue('email');
			$formVars['form_active'] = $this->formHelper->getFieldValue('active');
			$formVars['form_rightgroups'] = $this->formHelper->getFieldValue('rightgroups');
		} elseif($this->route->getParam(0)) {


			$formVars['form_name'] = null;
			$formVars['form_email'] = null;
			$formVars['form_active'] = null;
			$formVars['form_rightgroups'] = null;
		}

		$rightgroups = array();

		foreach($rightgroupModel->getRightGroups() as $rg) {
			$rightgroups[$rg->ID] = $rg->groupname;
		}

		$tplVars = array(
			'siteTitle' => ($this->route->getParam(0) === null)?'Create new user':'Edit user',
			'opts_rightgroups' => $rightgroups,
			'submit_label' => ($this->route->getParam(0) === null)?'Create':'Save changes',
			'form_status' => ($this->formHelper !== null && $this->formHelper->hasErrors())
				?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()):null
		);

		return $this->generatePageFromTemplate('backend-account-user-edit', array_merge($tplVars, $formVars));
	}

	public function processEditUser() {
		$this->abortIfUserHasNotRights('BACKEND_USERS_EDIT');

		$rightgroupModel = new RightGroupModel($this->db);
		$rightgroups = array();

		foreach($rightgroupModel->getRightGroups() as $rg) {
			$rightgroups[$rg->ID] = $rg->groupname;
		}

		$this->formHelper = new FormHelper(FormHelper::METHOD_POST);

		$this->formHelper->addField('name', null, FormHelper::TYPE_STRING, true, array(
			'missingError' => 'Please insert an username'
		));
		$this->formHelper->addField('email', null, FormHelper::TYPE_EMAIL, true, array(
			'missingError' => 'Please insert an e-mail address',
			'invalidError' => 'Please insert a valid e-mail address'
		));
		$this->formHelper->addField('active', null, FormHelper::TYPE_CHECKBOX, false);
		$this->formHelper->addField('rightgroups', null, FormHelper::TYPE_MULTIOPTIONS, true, array(
			'missingError' => 'Please choose at least one rightgroup',
			'options' => $rightgroups

		));

		if(!$this->formHelper->sent() || !$this->formHelper->validate())
			return $this->getEditUser();

		try {
			if($this->route->getParam(0) === null) {
				// Create
				$login = new \stdClass();
				$login->username = $this->formHelper->getFieldValue('name');
				$login->email = $this->formHelper->getFieldValue('email');
				$login->active = $this->formHelper->getFieldValue('active');
				$login->token = uniqid();
				$login->registeredBy = $this->auth->getUserID();

				$userID = $this->auth->signUp($login);

				// Send mail
				$mailer = MailFactory::getMailer();

				// Create a message
				$message = \Swift_Message::newInstance('Your new metanet.ch account');
				$message->setFrom(array($this->core->getSettings()->logincontroller->sender_email => $this->core->getSettings()->logincontroller->sender_name));
				$message->setTo(array($this->formHelper->getFieldValue('email')));
				$message->setBody("Hi,\n\nYou've gotten a new account to scatter stuff at this website in all directions.\n\nTo log you in you have to choose a strong password for your new account.\n\nPlease visit this link for that: https://" . $this->httpRequest->getHost() . '/backend/restore-pw/' . $login->token . $userID);

				// Send the message
				$result = $mailer->send($message);

				if(!$result) {
					$this->formHelper->addError(null, 'The link to reset your password could not been sent to you. Sorry!');
					return $this->getEditUser();
				}
			} else {
				$userID = $this->route->getParam(0);

				$stmntLogin = $this->db->prepare("
					UPDATE login SET name = ?, email = ?, active = ? WHERE ID = ?
				");

				$this->db->update($stmntLogin, array(
					$this->formHelper->getFieldValue('name'),
					$this->formHelper->getFieldValue('email'),
					$this->formHelper->getFieldValue('active'),
					$userID
				));
			}


			$removeRights = $this->db->prepare("DELETE FROM login_has_rightgroup WHERE loginIDFK = ?");
			$this->db->delete($removeRights, array($userID));

			$stmntInsertRight = $this->db->prepare("
				INSERT INTO login_has_rightgroup
				SET loginIDFK = ?, rightgroupIDFK = ?, datefrom = NOW()
			");

			foreach($this->formHelper->getFieldValue('rightgroups') as $r) {
				$this->db->insert($stmntInsertRight, array($userID, $r));
			}


		} catch(\Exception $e) {
			$this->formHelper->addError(null, 'Could not save user to database. Reason: ' . $e->getMessage());

			return $this->getEditUser();
		}

		RequestHandler::redirect('/backend/users');
	}

	public function deleteAccount($accountID) {
		try {
			$this->db->beginTransaction();

			$stmntCheckAdmin = $this->db->prepare("
				SELECT COUNT(*) count_admins
				FROM login_has_rightgroup lhr
				JOIN rightgroup r ON r.ID = lhr.rightgroupIDFK
				JOIN login l ON l.ID = lhr.loginIDFK
				WHERE r.root = 1 AND lhr.loginIDFK != ?
			");

			$resCheckAdmin = $this->db->select($stmntCheckAdmin, array($accountID));

			if($resCheckAdmin[0]->count_admins <= 0)
				throw new CMSException('You\'re the last admin, you can\'t delete yourself!');

			$stmntDelLoginHasRg = $this->db->prepare("
				DELETE FROM login_has_rightgroup WHERE loginIDFK = ?
			");
			$this->db->delete($stmntDelLoginHasRg, array($accountID));

			$stmntDelAcc = $this->db->prepare("DELETE FROM login WHERE ID = ?");
			$this->db->delete($stmntDelAcc, array($accountID));

			$this->db->commit();
		} catch(DBException $e) {
			$this->db->rollBack();

			throw $e;
		}
	}

	private function getAllRights() {
		$moduleModel = new ModuleModel($this->db);

		$rights = array();

		foreach($moduleModel->getAllModules() as $mod) {
			if(isset($mod->manifest_content->rights) === false)
				continue;

			foreach($mod->manifest_content->rights as $rKey => $rLang) {
				$rights[] = $rKey;
			}
		}

		return $rights;
	}
}

/* EOF */