<?php

namespace ch\metanet\cms\module\mod_users\backend;

use ch\metanet\cms\common\CmsBackendMessage;
use ch\metanet\cms\common\CmsModuleBackendController;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\model\Login;
use ch\metanet\cms\model\LoginModel;
use ch\metanet\cms\model\RightGroup;
use ch\metanet\cms\model\RightGroupModel;
use ch\metanet\cms\module\mod_metanet_orders\forms\MetanetForm;
use ch\metanet\cms\tablerenderer\BooleanColumnDecorator;
use ch\metanet\cms\tablerenderer\CallbackColumnDecorator;
use ch\metanet\cms\tablerenderer\Column;
use ch\metanet\cms\tablerenderer\DateColumnDecorator;
use ch\metanet\cms\tablerenderer\EmptyValueColumnDecorator;
use ch\metanet\cms\tablerenderer\RewriteColumnDecorator;
use ch\metanet\cms\tablerenderer\TableRenderer;
use ch\metanet\formHandler\component\Form;
use ch\metanet\formHandler\field\InputField;
use ch\metanet\formHandler\field\OptionsField;
use ch\metanet\formHandler\renderer\CheckboxOptionsFieldRenderer;
use ch\metanet\formHandler\renderer\EmailInputFieldRenderer;
use ch\metanet\formHandler\rule\RequiredRule;
use ch\metanet\formHandler\rule\ValidEmailAddressRule;
use ch\timesplinter\core\RequestHandler;
use ch\timesplinter\mailer\MailFactory;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class BackendUsersController extends CmsModuleBackendController
{
	protected $loginModel;
	protected $rightGroupModel;
	/** @var MetanetForm|null */
	protected $form;
	protected $translator;
	protected $translatorTableRenderer;

	public function __construct(BackendController $moduleController, $moduleName)
	{
		parent::__construct($moduleController, $moduleName);

		$this->controllerRoutes = array(
			'/' => array(
				'GET' => 'getModuleOverview'
			),
			
			'/account/(\d+)/view' => array(
				'GET' => 'getViewAccount',
				'POST' => 'postViewAccount'
			),

			'/account/add' => array(
				'GET' => 'getEditAccount',
				'POST' => 'postEditAccount'
			),
			
			'/account/(\d+)/edit' => array(
				'GET' => 'getEditAccount',
				'POST' => 'postEditAccount'
			),
			
			'/rightgroup/add' => array(
				'GET' => 'getEditRightGroup',
				'POST' => 'postEditRightGroup'
			),
			
			'/rightgroup/(\d+)/edit' => array(
				'GET' => 'getEditRightGroup',
				'POST' => 'postEditRightGroup'
			)
		);

		$localePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR;
		$this->translator = $this->cmsController->getTranslator($localePath);
		$this->translator->bindTextDomain('backend', 'UTF-8');

		$this->loginModel = new LoginModel($this->cmsController->getDB());
		$this->rightGroupModel = new RightGroupModel($this->cmsController->getDB());
	}

	public function getModuleOverview()
	{
		$this->cmsController->abortIfUserHasNotRights('MOD_USERS_OVERVIEW');
		
		if(($groupIdToDelete = $this->cmsController->getHttpRequest()->getVar('rgdelete')) !== null) {
			try {
				$this->rightGroupModel->deleteRightGroup($groupIdToDelete);

				$this->setMessageForNextPage(new CmsBackendMessage(
					$this->translator->_d('backend', 'The right group has been deleted successfully'), 
					CmsBackendMessage::MSG_TYPE_SUCCESS
				));
			} catch(\Exception $e) {
				$this->setMessageForNextPage(new CmsBackendMessage(
					$this->translator->_d('backend', 'The right group has not been deleted') . ': ' . $e->getMessage(),
					CmsBackendMessage::MSG_TYPE_ERROR
				));
			}
		}

		if(($userIdToDelete = $this->cmsController->getHttpRequest()->getVar('delete_account')) !== null) {
			try {
				$this->loginModel->deleteLogin($userIdToDelete);

				$this->setMessageForNextPage(new CmsBackendMessage(
					$this->translator->_d('backend', 'The account has been deleted successfully'),
					CmsBackendMessage::MSG_TYPE_SUCCESS
				));
			} catch(\Exception $e) {
				$this->setMessageForNextPage(new CmsBackendMessage(
					$this->translator->_d('backend', 'The account has not been deleted') . ': ' . $e->getMessage(),
					CmsBackendMessage::MSG_TYPE_ERROR
				));
			}
		}

		$stmntUsers = "
			SELECT ID, username, email, lastlogin, wronglogins, confirmed, token, tokentime
			FROM login
			WHERE ID != 0
			ORDER BY username
		";
		
		$tableRendererTranslator = $this->cmsController->getTranslator(
			$this->cmsController->getCore()->getSiteRoot() . 'locale' . DIRECTORY_SEPARATOR
		);

		$tableRendererUsers = new TableRenderer('users', $this->cmsController->getDB(), $stmntUsers);
		$tableRendererUsers->setTranslator($tableRendererTranslator);
		$tableRendererUsers->setColumns(array(
			new Column('ID', '#', array(), true),
			new Column('username', $this->translator->_d('backend', 'Username'), array(), true),
			new Column('email', $this->translator->_d('backend', 'E-mail'), array(new RewriteColumnDecorator('<a href="mailto:{email}">{email}</a>')), true),
			new Column(null, $this->translator->_d('backend', 'Right groups'), array(new CallbackColumnDecorator(function($value, $record) {
				$rgs = $this->rightGroupModel->getRightGroupByUser($record->ID);

				$rgArr = array();

				foreach($rgs as $r) {
					$rgArr[] = '<a href="' . $this->getBaseURI() . '/rightgroup/' . $r->ID . '/edit">' . $r->groupname;
				}

				return implode(', ', $rgArr);
			})), true),
			new Column('lastlogin', $this->translator->_d('backend', 'Last login'), array(
				new DateColumnDecorator($this->cmsController->getLocaleHandler()->getDateTimeFormat()), 
				new EmptyValueColumnDecorator('<em>' . $this->translator->_d('backend', 'never') . '</em>')
			), true),
			new Column('wronglogins', $this->translator->_d('backend', 'Wrong logins'), array(), true),
			new Column('confirmed', $this->translator->_d('backend', 'Confirmed'), array(
				new DateColumnDecorator($this->cmsController->getLocaleHandler()->getDateTimeFormat()),
				new EmptyValueColumnDecorator('<em>' . $this->translator->_d('backend', 'not yet') . '</em>')
			), true)
		));
		$tableRendererUsers->setOptions(array(
			$this->translator->_d('backend', 'view') => $this->getBaseURI() . '/account/{ID}/view',
			'delete' => $this->getBaseURI() . '?delete_account={ID}'
		));
		
		$users = $this->loginModel->getAllLogins();

		foreach($users as $u) {
			$rgs = $this->rightGroupModel->getRightGroupByUser($u->ID);

			$rgArr = array();

			foreach($rgs as $r) {
				$rgArr[] = '<a href="' . $this->getBaseURI() . '/rightgroup/' . $r->ID . '/edit">' . $r->groupname;
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

		$tableRendererRightGroups = new TableRenderer('rightgroups', $this->cmsController->getDB(), $stmntRightGroups);
		$tableRendererRightGroups->setTranslator($tableRendererTranslator);
		$tableRendererRightGroups->setColumns(array(
			new Column('ID', '#', array(), true),
			new Column('groupname', $this->translator->_d('backend', 'Name'), array(), true),
			new Column('root', $this->translator->_d('backend', 'Root access'), array(new BooleanColumnDecorator()), true)
		));
		$tableRendererRightGroups->setOptions(array(
			'edit' => $this->getBaseURI() . '/rightgroup/{ID}/edit',
			'delete' => $this->getBaseURI() . '?rgdelete={ID}'
		));

		return $this->renderModuleContent('mod-users/accounts-overview', array(
			'siteTitle' => $this->translator->_d('backend', 'Users & Rights'),
			'logins' => $users,
			'users' => $tableRendererUsers->display(),
			'right_groups' => $tableRendererRightGroups->display(),
			'status_message' => $this->renderPendingMessage(),
			'translator' => $this->translator
		));
	}

	public function getViewAccount(array $params)
	{
		$this->cmsController->abortIfUserHasNotRights('MOD_USERS_EDIT_USER');
		
		$userID = $params[0];

		if(($rightGroupID = $this->cmsController->getHttpRequest()->getVar('remove_right_group')) !== null) {
			try {
				$this->loginModel->removeRightgroupFromLogin($rightGroupID, $userID);

				$this->setMessageForNextPage(new CmsBackendMessage(
					$this->translator->_d('backend', 'The right group has been removed'),
					CmsBackendMessage::MSG_TYPE_SUCCESS
				));
			} catch(\Exception $e) {
				$this->setMessageForNextPage(new CmsBackendMessage(
					$this->translator->_d('backend', 'Could not remove right group') . ': ' . $e->getMessage(),
					CmsBackendMessage::MSG_TYPE_ERROR
				));
			}
		}

		$rightGroupsAddArr = array(0 => '- ' . $this->translator->_d('backend', 'please choose') . ' -');

		foreach($this->rightGroupModel->getRightGroups() as $rg) {
			if($this->loginModel->hasLoginRightGroup($userID, $rg->getID()) || $rg->getID() == 0)
				continue;

			$rightGroupsAddArr[$rg->getID()] = $rg->getGroupName();
		}

		$userData = $this->loginModel->getLoginByID($userID);
		$lastLogin = $userData->getLastLogin();
		$confirmed = $userData->getConfirmed();

		$stmntUserRights = "
			SELECT rg.ID, rg.groupname, rg.groupkey, rg.root, lhr.datefrom, lhr.dateto
			FROM login_has_rightgroup lhr
			LEFT JOIN rightgroup rg ON rg.ID = lhr.rightgroupIDFK
			WHERE lhr.loginIDFK = ?
		";

		$tableRendererTranslator = $this->cmsController->getTranslator(
			$this->cmsController->getCore()->getSiteRoot() . 'locale' . DIRECTORY_SEPARATOR
		);
		
		$trRights = new TableRenderer('user-rights-' . $userID, $this->cmsController->getDB(), $stmntUserRights);
		$trRights->setTranslator($tableRendererTranslator);
		$trRights->setColumns(array(
			new Column('groupname', $this->translator->_d('backend', 'Right group'), array(), true),
			new Column('datefrom', $this->translator->_d('backend', 'From'), array(
				new DateColumnDecorator($this->cmsController->getLocaleHandler()->getDateTimeFormat())
			), true),
			new Column('dateto', $this->translator->_d('backend', 'To'), array(
				new DateColumnDecorator($this->cmsController->getLocaleHandler()->getDateTimeFormat()),
				new EmptyValueColumnDecorator('<em>' . $this->translator->_d('backend', 'indeterminate') . '</em>')
			), true)
		));
		
		$trRights->setOptions(array(
			'delete' => '?remove_right_group={ID}'
		));

		return $this->renderModuleContent('mod-users/account-view', array(
			'siteTitle' => ($userID == $this->cmsController->getAuth()->getUserID()) ? $this->translator->_d('backend', 'My account overview') : $this->translator->_d('backend', 'Account') . ' ' . $userData->getEmail(),
			'user_id' => $userData->getID(),
			'user_username' => $userData->getUsername(),
			'user_email' => $userData->getEmail(),
			'last_login' => ($lastLogin instanceof \DateTime) ? $lastLogin->format($this->cmsController->getLocaleHandler()->getDateTimeFormat()) : $this->translator->_d('backend', 'never'),
			'confirmed' => ($confirmed instanceof \DateTime) ? $confirmed->format($this->cmsController->getLocaleHandler()->getDateTimeFormat()) : $this->translator->_d('backend', 'not yet'),
			'registered' => $userData->getRegistered()->format($this->cmsController->getLocaleHandler()->getDateTimeFormat()),
			'active' => $this->translator->_d('backend', ($userData->getActive() == 1) ? 'yes' : 'no'),
			'wrong_logins' => $userData->getWrongLogins(),
			'right_groups' => $trRights->display(array($userID)),
			'rightgroupsAdd' => $rightGroupsAddArr,
			'rightgroupsAddSelected' => array(0),
			'status_message' => $this->renderPendingMessage(),
			'translator' => $this->translator
		));
	}

	public function postViewAccount($params)
	{
		$this->cmsController->abortIfUserHasNotRights('MOD_USERS_EDIT_USER');
		
		$rightGroupsAddArr = array();
		$userId = $params[0];
		
		foreach($this->rightGroupModel->getRightGroups() as $rg) {
			if($this->loginModel->hasLoginRightGroup($userId, $rg->getID()) || $rg->getID() == 0)
				continue;

			$rightGroupsAddArr[$rg->getID()] = $rg->getGroupName();
		}

		$this->form = new Form(Form::METHOD_POST, 'addrightgroup');
		$this->form->setInputData($_POST + $_GET);

		$fldRightGroup = new OptionsField('rightgroup', null, $rightGroupsAddArr);
		$fldRightGroup->addRule(new RequiredRule('Please choose a valid right group'));
		
		$this->form->addField($fldRightGroup);

		if(!$this->form->isSent() || !$this->form->validate())
			return $this->getViewAccount($params);

		try {
			$this->rightGroupModel->addRightGroupToUser(
				$fldRightGroup->getValue(),
				$userId,
				new \DateTime()
			);

			$this->setMessageForNextPage(new CmsBackendMessage(
				$this->translator->_d('backend', 'The right group has been added'),
				CmsBackendMessage::MSG_TYPE_SUCCESS
			));
		} catch(\Exception $e) {
			$this->setMessageForNextPage(new CmsBackendMessage(
				$this->translator->_d('backend', 'Could not add right group') . ': ' . $e->getMessage(),
				CmsBackendMessage::MSG_TYPE_ERROR
			));
		}

		return $this->getViewAccount($params);
	}

	/**
	 * @param array $params
	 *
	 * @return \ch\metanet\cms\common\CmsModuleResponse
	 * @throws \ch\timesplinter\core\HttpException
	 */
	public function getEditAccount(array $params)
	{
		$this->cmsController->abortIfUserHasNotRights('MOD_USERS_EDIT_USER');

		$userID = isset($params[0]) ? $params[0] : null;
		
		if(($userData = $this->loginModel->getLoginByID($userID)) === null)
			$userData = new Login();
		
		$this->prepareEditAccountForm($userData);
		
		$rightGroups = array();

		foreach($this->rightGroupModel->getRightGroups() as $rg) {
			$rightGroups[$rg->getID()] = $rg->getGroupName();
		}
		
		if($this->form instanceof Form && $this->form->hasErrors()) {
			$this->setMessageForNextPage(new CmsBackendMessage(
				$this->translator->_d('backend', 'There were errors during submitting the form'),
				CmsBackendMessage::MSG_TYPE_ERROR
			));
		}

		return $this->renderModuleContent('mod-users/account-edit', array(
			'siteTitle' => $this->translator->_d('backend', ($userID === null)? 'Create new user' : 'Edit user'),
			'opts_right_groups' => $rightGroups,
			'form' => $this->form,
			'submit_label' => $this->translator->_d('backend', ($userID === null) ? 'Create' : 'Save changes'),
			'translator' => $this->translator
		));
	}

	public function postEditAccount(array $params)
	{
		$this->cmsController->abortIfUserHasNotRights('MOD_USERS_EDIT_USER');

		$userID = isset($params[0]) ? $params[0] : null;

		if(($userData = $this->loginModel->getLoginByID($userID)) === null)
			$userData = new Login();
		
		$this->prepareEditAccountForm($userData);
		
		if(!$this->form->isSent() || !$this->form->validate())
			return $this->getEditAccount($params);

		try {
			$userData->setUsername($this->form->getField('name')->getValue());
			$userData->setEmail($this->form->getField('email')->getValue());
			$userData->setActive($this->form->getField('active')->getValue() == 1);
			
			if(isset($params[0]) === false) {
				$userData->setSalt($this->cmsController->getAuth()->generateSalt());
				$userData->setRegisteredBy($this->cmsController->getAuth()->getUserID());
				
				$userID = $this->loginModel->storeLogin($userData);

				$securityToken = $this->cmsController->getAuth()->generateToken($userID);
				
				// Send mail
				$mailer = MailFactory::getMailer();

				$loginControllerSettings = $this->cmsController->getCore()->getSettings()->logincontroller;
				
				// Create a message
				$message = \Swift_Message::newInstance('Your new metanet.ch account');
				$message->setFrom(array(
					$loginControllerSettings->sender_email => $loginControllerSettings->sender_name
				));
				$message->setTo(array($userData->getEmail()));
				$message->setBody(sprintf($this->translator->_d('backend', "Hi,\n\nYou've gotten a new account to scatter stuff at this website in all directions.\n\nTo log you in you have to choose a strong password for your new account.\n\nPlease visit this link for that: %s"), "https://" . $this->cmsController->getHttpRequest()->getHost() . '/backend/restore-pw/' . $securityToken . $userID));

				// Send the message
				$result = $mailer->send($message);

				if(!$result) {
					$this->setMessageForNextPage(new CmsBackendMessage(
						$this->translator->_d('backend', 'The link to reset your password could not been sent to you. Sorry!'),
						CmsBackendMessage::MSG_TYPE_ERROR
					));
					
					return $this->getEditAccount($params);
				}

				$this->setMessageForNextPage(new CmsBackendMessage(
					$this->translator->_d('backend', 'The user has been created successfully'),
					CmsBackendMessage::MSG_TYPE_SUCCESS
				));
			} else {
				$this->loginModel->storeLogin($userData);

				$this->setMessageForNextPage(new CmsBackendMessage(
					$this->translator->_d('backend', 'The user information has been updated successfully'),
					CmsBackendMessage::MSG_TYPE_SUCCESS
				));
			}
		} catch(\Exception $e) {
			
			$this->setMessageForNextPage(new CmsBackendMessage(
				$this->translator->_d('backend', 'Could not save user to database. Reason: ' . $e->getMessage()),
				CmsBackendMessage::MSG_TYPE_ERROR
			));

			return $this->getEditAccount($params);
		}

		RequestHandler::redirect($this->getBaseURI());
	}

	public function getEditRightGroup(array $params)
	{
		$this->cmsController->abortIfUserHasNotRights('BACKEND_RIGHTGROUPS_EDIT');

		if(isset($params[0]) === false || ($rightGroup = $this->rightGroupModel->getRightGroupByID($params[0])) === null)
			$rightGroup = new RightGroup();

		$this->prepareEditRightGroupForm($rightGroup);
		
		return $this->renderModuleContent('mod-users/right-group-edit', array(
			'siteTitle' => $this->translator->_d('backend', isset($params[0]) ? 'Edit rightgroup':'Create rightgroup'),
			'submit_label' => $this->translator->_d('backend', isset($params[0]) ? 'Save changes':'Create'),
			'form' => $this->form,
			'form_status' => ($this->form instanceof Form && $this->form->hasErrors()) ? 'Da gibts aber Fehler' : null,
			'translator' => $this->translator
		));
	}

	public function postEditRightGroup(array $params)
	{
		$this->cmsController->abortIfUserHasNotRights('BACKEND_RIGHTGROUPS_EDIT');

		if(isset($params[0]) === false || ($rightGroup = $this->rightGroupModel->getRightGroupByID($params[0])) === null)
			$rightGroup = new RightGroup();
		
		$this->prepareEditRightGroupForm($rightGroup);
		
		if(!$this->form->isSent() || !$this->form->validate())
			return $this->getEditRightGroup($params);

		$rightValue = $this->form->getField('rights')->getValue();
		
		$rootValue = $this->form->getField('root')->getValue();
		
		$rightGroup->setGroupName($this->form->getField('name')->getValue());
		$rightGroup->setGroupKey($this->form->getField('key')->getValue());
		$rightGroup->setRoot(($rootValue === null) ? 0 : 1);
		$rightGroup->setRights(is_array($rightValue) ? $rightValue : array());

		$this->rightGroupModel->storeRightGroup($rightGroup);

		RequestHandler::redirect($this->getBaseURI());
	}
	
	protected function prepareEditAccountForm(Login $userData)
	{
		if($this->form instanceof Form)
			return;
		
		$rightGroups = array();

		foreach($this->rightGroupModel->getRightGroups() as $rg) {
			$rightGroups[$rg->getID()] = $rg->getGroupName();
		}
		
		$this->form = new MetanetForm();
		$this->form->setInputData(array_merge($_POST, $_GET));

		$fldName = new InputField('name', 'Name');
		$fldName->setValue($userData->getUsername());
		$fldName->addRule(new RequiredRule($this->translator->_d('backend', 'Please insert a username')));
		$this->form->addField($fldName);
		
		$fldEmail = new InputField('email', 'E-Mail');
		$fldEmail->setValue($userData->getEmail());
		$fldEmail->setInputFieldRenderer(new EmailInputFieldRenderer());
		$fldEmail->addRule(new RequiredRule($this->translator->_d('backend', 'Please insert an e-mail address')));
		$fldEmail->addRule(new ValidEmailAddressRule($this->translator->_d('backend', 'Please insert a valid e-mail address')));
		$this->form->addField($fldEmail);
		
		$fldActive = new OptionsField('active', 'Active', array(
			1 => $this->translator->_d('backend', 'This account is active')
		));
		$fldActive->setValue($userData->getActive());
		$fldActive->setOptionsFieldRenderer(new CheckboxOptionsFieldRenderer());
		$this->form->addField($fldActive);
				
		$this->form->addFields(array(
			$fldName,
			$fldEmail,
			$fldActive
		));
	}

	/**
	 * @param RightGroup $rightGroup
	 */
	protected function prepareEditRightGroupForm(RightGroup $rightGroup)
	{
		if($this->form instanceof MetanetForm)
			return;
		
		$lang = $this->cmsController->getLocaleHandler()->getLanguage();
		
		$checkOptionsRights = array();
		$options = array();
		$allModules = $this->moduleModel->getAllModules();
		
		foreach($allModules as $mod) {
			if(isset($mod->manifest_content->rights) === false || count((array)$mod->manifest_content->rights) === 0)
				continue; 

			$optionsTemp = array();
			
			foreach($mod->manifest_content->rights as $key => $label) {
				$checkOptionsRights[$key] = $key;
				$optionsTemp[$key] = isset($label->$lang) ? $label->$lang : $label->en;
			}

			$options[isset($mod->manifest_content->name->$lang) ? $mod->manifest_content->name->$lang : $mod->manifest_content->name->en] = $optionsTemp;
		}
		
		$this->form = new MetanetForm();
		$this->form->setInputData(array_merge($_POST, $_GET));
		
		$fldName = new InputField('name', 'Name');
		$fldName->setValue($rightGroup->getGroupName());
		$fldName->addRule(new RequiredRule($this->translator->_d('backend', 'Please insert a group name')));

		$fldKey = new InputField('key', 'Key');
		$fldKey->setValue($rightGroup->getGroupKey());
		$fldKey->addRule(new RequiredRule($this->translator->_d('backend', 'Please insert a group key')));

		$fldRoot = new OptionsField('root', 'Root', array(
			1 => $this->translator->_d('backend', 'This group has root rights')
		));
		$fldRoot->setValue(array((int)$rightGroup->isRoot()));
		$fldRoot->setOptionsFieldRenderer(new CheckboxOptionsFieldRenderer());
		
		$fldRights = new OptionsField('rights', 'Rights', $checkOptionsRights);
		$fldRights->setOptionsFieldRenderer(new RightsOptionsFieldRenderer($options));
		$fldRights->setValue($rightGroup->getRights());
		$this->form->addFields(array(
			$fldName,
			$fldKey,
			$fldRoot,
			$fldRights
		));
	}
}

/* EOF */