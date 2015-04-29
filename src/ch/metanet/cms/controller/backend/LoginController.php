<?php

namespace ch\metanet\cms\controller\backend;

use ch\metanet\cms\common\BackendControllerUnprotected;
use ch\metanet\cms\common\CMSException;
use ch\metanet\cms\common\CmsUtils;
use ch\metanet\cms\controller\common\BackendController;
use ch\timesplinter\core\Core;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\HttpResponse;
use ch\timesplinter\core\RequestHandler;
use ch\timesplinter\core\Route;
use ch\timesplinter\formhelper\FormHelper;
use ch\timesplinter\mailer\MailFactory;
use stdClass;

/**
 * The login controller handles login, password restore, signup
 *
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class LoginController extends BackendController implements BackendControllerUnprotected
{
	/** @var $formHelper FormHelper */
	protected $formHelper;
	
	public function __construct(Core $core, HttpRequest $httpRequest, Route $route)
	{
		parent::__construct($core, $httpRequest, $route);
	}
	
    public function processSignUp()
    {
		$this->formHelper = new FormHelper(FormHelper::METHOD_POST);
		
		$this->formHelper->addField('email', null, FormHelper::TYPE_EMAIL, true, array(
			'missingError' => 'Please fill in your e-mail address',
			'invalidError' => 'Please fill in your correct e-mail address'
		));
		$this->formHelper->addField('password', null, FormHelper::TYPE_STRING, true, array(
			'missingError' => 'Please fill in your password'
		));
		$this->formHelper->addField('pwrepeat', null, FormHelper::TYPE_STRING, true, array(
			'missingError' => 'Please repeat your password'
		));
		
		if($this->formHelper->sent()) {
			$this->formHelper->validate();
			
			if(!$this->formHelper->hasErrors()) {
				if($this->formHelper->getFieldValue('password') !== $this->formHelper->getFieldValue('pwrepeat')) {
					$this->formHelper->addError('password', 'Your password an the repetition do not match');
				}
				
				// E-Mail check
				if($this->auth->accountExists($this->formHelper->getFieldValue('email'))){
					$this->formHelper->addError('email', 'This email address is already registered');
				}
			}
			
			if(!$this->formHelper->hasErrors()) {
				$activationToken = uniqid();

				// eintrag
				$login = new stdClass();
				$login->username = null;
				$login->email = $this->formHelper->getFieldValue('email');
				$login->password = $this->formHelper->getFieldValue('password');
				$login->rightGroups = array(1);
				$login->token = $activationToken;
				
				if(($userID = $this->auth->signUp($login)) !== false) {
					$mailer = MailFactory::getMailer();

					// Create a message
					$message = \Swift_Message::newInstance('Activate your account');
					$message->setFrom(array($this->core->getSettings()->logincontroller->sender_email => $this->core->getSettings()->logincontroller->sender_name));
					$message->setTo(array($login->email));
					$message->setBody('To activate your account please visit this link: https://' . $this->httpRequest->getHost() . '/backend/activate/' . $activationToken . $userID);

					// Send the message
					$result = $mailer->send($message);

					if($result !== 0) {
						RequestHandler::redirect('/signup-success');
					}

					$this->formHelper->addError(null, 'The link to reset your password could not been sent to you. Sorry!');
				}
				
				$this->formHelper->addError(null, 'Signup failed because of a db issue');
			}
		}
		
		return $this->getSignUpSite();
	}
	
	public function getSignUpSite()
	{
		return $this->generatePageFromTemplate('backend-signup', array(
			'siteTitle' => 'Sign up',
			'formData' => ($this->formHelper !== null)?$this->formHelper->getAllValues():array(),
			'status' => ($this->formHelper !== null)?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()): null
		));
	}
	
	public function getSignUpSuccessSite()
	{
		return $this->generatePage(array(
			'siteTitle' => 'Sign up succeeded'
		));
	}

	public function getActivationSite()
	{
		$tokenUserID = $this->route->getParam(0);
		$token = substr($tokenUserID, 0, 13);
		$userID = substr($tokenUserID, 13);

		$stmntActivate = $this->db->prepare("
			UPDATE login SET active = 1, token = NULL, confirmed = NOW() WHERE active = 0 AND confirmed IS NULL AND ID = ? AND token = ?
		");
		
		$updatedRows = $this->db->update($stmntActivate, array(
			$userID,
			$token
		));

		$tplVars = array();

		if($updatedRows > 0) {
			// Successful
			$tplVars['siteTitle'] = 'Account activation successfully';
			$tplVars['message'] = 'Your account has been activated successfully. You can now log you in.';
		} else {
			// failed
			$tplVars['siteTitle'] = 'Account activation failed';
			$tplVars['message'] = 'Sorry, there is not account we can activate with this token.';
		}

		return $this->generatePage($tplVars);
	}
	
	public function getLoginSite()
	{
		if($this->auth->isLoggedIn())
			RequestHandler::redirect ($this->core->getSettings()->logincontroller->page_after_login);

		$errors = ($this->formHelper !== null) ? $this->formHelper->getErrors() : array();
		
		if($this->httpRequest->getVar('session_expired') !== null) {
			$errors[] = 'Your session has expired';
		}
		
		$tplVars = array(
			'form_status' => (count($errors) > 0) ? CmsUtils::getErrorsAsHtml($errors): null,
			'form_username' => null,
			'siteTitle' => 'Login'
		);

		if($this->formHelper !== null && $this->formHelper->sent()) {
			$tplVars['form_username'] = $this->formHelper->getFieldValue('email');
		} elseif(isset($_COOKIE['username'])) {
			$tplVars['form_username'] = $_COOKIE['username'];
		}

		return $this->generatePageFromTemplate('backend-login', $tplVars);
	}
	
	public function processLogin()
	{
		$this->formHelper = new FormHelper(FormHelper::METHOD_POST);
		$this->formHelper->addField('email', null, FormHelper::TYPE_EMAIL, true, array(
			'missingError' => 'Please fill in your login e-mail address',
			'invalidError' => 'Please fill in your correct login e-mail address'
		));
		$this->formHelper->addField('password', null, FormHelper::TYPE_STRING, true, array(
			'missingError' => 'Please fill in your password'
		));

		if(!$this->formHelper->sent())
			return $this->getLoginSite();

		$this->formHelper->validate();

		if($this->formHelper->hasErrors())
			return $this->getLoginSite();

		$checkLogin = $this->auth->checkLogin(
			$this->formHelper->getFieldValue('email'), 
			$this->formHelper->getFieldValue('password')
		);

		if(!$checkLogin) {
			$this->formHelper->addError(null, 'Login failed because of invalid login data');
			return $this->getLoginSite();
		}

		setcookie('username', $this->formHelper->getFieldValue('email'), time()+31536000, '/', $this->httpRequest->getHost(), true, true);

		// Update cms_login_history
		try {
			$stmntLoginHistory = $this->db->prepare("
				INSERT INTO cms_login_history
					SET login_IDFK = ?, sessionid = ?, ipaddress = ?
			");
			$this->db->insert($stmntLoginHistory, array(
				$this->auth->getUserID(),
				$this->core->getSessionHandler()->getID(),
				$this->httpRequest->getRemoteAddress()
			));
		} catch(\Exception $e) {
			$this->formHelper->addError(null, 'Login failed because of an internal error');
			return $this->getLoginSite();
		}
		
		RequestHandler::redirect($this->core->getSettings()->logincontroller->page_after_login);
	}
	
	public function getLogoutSite()
	{
		if(!$this->auth->isLoggedIn())
			RequestHandler::redirect($this->core->getSettings()->logincontroller->login_page);
		else
			$this->auth->logout();

		$prev_uri = $this->httpRequest->getVar('ref');
		
		return $this->generatePage(array(
			'siteTitle' => 'Logout successful',
			'prev_uri' => ($prev_uri !== null)?urldecode($prev_uri):'/'
		));
	}

	/**
	 * Shows the "Restore password" site
	 * @param string $statusHtml
	 * @return HttpResponse
	 */
	public function getRestorePwSite($statusHtml = null) {
		if($this->auth->isLoggedIn())
			RequestHandler::redirect($this->core->getSettings()->logincontroller->page_after_login);

		return $this->generatePage(array(
			'siteTitle' => 'Restore password',
			'status' => ($this->formHelper !== null && $this->formHelper->hasErrors())?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()): $statusHtml
		));
	}

	/**
	 * Process the password restore request and shows the "Restore password" site afterwards
	 * @return HttpResponse
	 */
	public function processRestorePw() {
		$this->formHelper = new FormHelper(FormHelper::METHOD_POST);
		$this->formHelper->addField('email', null, FormHelper::TYPE_EMAIL, true, array(
			'missingError' => 'Please fill in your login e-mail address',
			'invalidError' => 'Please fill in your correct login e-mail address'
		));

		if(!$this->formHelper->sent())
			return $this->getRestorePwSite();

		if(!$this->formHelper->validate() || $this->formHelper->hasErrors())
			return $this->getRestorePwSite();

		/* do something funky here */
		$emailAddress = $this->formHelper->getFieldValue('email');

		if(($userID = $this->auth->accountExists($emailAddress)) === false) {
			$this->formHelper->addError('email', 'The e-mail address you\'ve entered is not registered!');
			return $this->getRestorePwSite();
		}

		$token = $this->auth->generateToken($userID);

		$mailer = MailFactory::getMailer();

		// Create a message
		$message = \Swift_Message::newInstance('Password restore link');
		$message->setFrom(array($this->core->getSettings()->logincontroller->sender_email => $this->core->getSettings()->logincontroller->sender_name));
		$message->setTo(array($emailAddress));
		$message->setBody("To choose a new password please visit this link: https://" . $this->httpRequest->getHost() . '/backend/restore-pw/' . $token . $userID);

		// Send the message
		$result = $mailer->send($message);

		if(!$result) {
			$this->formHelper->addError(null, 'The link to reset your password could not been sent to you. Sorry!');
		}

		return $this->getRestorePwSite('<div class="msg-success">Check your mailbox. An email has been sent to <b>' . $emailAddress . '</b> with a link to set a new password for your account.</div>');
	}

	public function getNewPasswordPage() {
		if($this->auth->isLoggedIn())
			RequestHandler::redirect($this->core->getSettings()->logincontroller->page_after_login);

		// Do some checks
		$tokenUserID = $this->route->getParam(0);
		$token = substr($tokenUserID, 0, 13);
		$userID = substr($tokenUserID, 13);

		$tokenValid = $this->auth->checkToken($token, $userID);

		return $this->generatePage(array(
			'siteTitle' => 'Choose your new password',
			'status' => ($this->formHelper !== null && $this->formHelper->hasErrors())?CmsUtils::getErrorsAsHtml($this->formHelper->getErrors()): null,
			'token_valid' => $tokenValid
		));
	}

	public function processNewPasswordPage() {
		if($this->auth->isLoggedIn())
			RequestHandler::redirect($this->core->getSettings()->logincontroller->page_after_login);

		$tokenUserID = $this->route->getParam(0);
		$token = substr($tokenUserID, 0, 13);
		$userID = substr($tokenUserID, 13);

		$this->formHelper = new FormHelper(FormHelper::METHOD_POST);
		$this->formHelper->addField('password', null, FormHelper::TYPE_STRING, true, array(
			'missingError' => 'Please type in your new password'
		));
		$this->formHelper->addField('pwrepeat', null, FormHelper::TYPE_STRING, true, array(
			'missingError' => 'Please retype your new password'
		));

		if(!$this->formHelper->sent() || !$this->formHelper->validate())
			return $this->getNewPasswordPage();

		$newpw = $this->formHelper->getFieldValue('password');

		if(strlen($newpw) < 8)
			$this->formHelper->addError(null, 'Your password has to be at least 8 characters long');

		if(preg_match('/^\d+$/', $newpw) || preg_match('/^[A-Za-z]+$/', $newpw))
			$this->formHelper->addError(null, 'Your password has to be a mix of alpha and numeric signs');

		if($newpw !== $this->formHelper->getFieldValue('pwrepeat'))
			$this->formHelper->addError(null, 'Your new password and the repetition do not match');

		if(!$this->auth->checkToken($token, $userID))
			$this->formHelper->addError(null, 'Sorry the token you submittd is not valid anymore');

		if($this->formHelper->hasErrors())
			return $this->getNewPasswordPage();

		try {
			$stmntSalt = $this->db->prepare("SELECT salt, confirmed FROM login WHERE token = ? AND ID = ?");
			$resSalt = $this->db->select($stmntSalt, array(
				$token, $userID
			));

			if(count($resSalt) <= 0)
				throw new CMSException('Could not find user');

			$stmntUpdatePw = $this->db->prepare("
				UPDATE login SET password = ?, confirmed = ?, token = NULL, tokentime = NULL WHERE token = ? AND ID = ?
			");

			$this->db->update($stmntUpdatePw, array(
				$this->auth->encryptPassword($newpw, $resSalt[0]->salt),
				($resSalt[0]->confirmed === null)?date('Y-m-d H:i:s'):$resSalt[0]->confirmed,
				$token,
				$userID
			));
		} catch(\Exception $e) {
			$this->formHelper->addError(null, 'Could not update password. Reason: ' . $e->getMessage());
			return $this->getNewPasswordPage();
		}

		RequestHandler::redirect('/backend');
	}

	public static function getUnprotectedMethods()
	{
		return array();
	}
}

/* EOF */