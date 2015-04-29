<?php

namespace ch\metanet\cms\common;

use ch\metanet\cms\controller\backend\ModuleController;
use ch\metanet\cms\controller\common\BackendController;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\HttpResponse;
use Zend\Stdlib\Exception\InvalidArgumentException;

/**
 * The basic controller which should each backend controller from a CMS module extend. This class provides some basic
 * and fundamental backend features and make a backend controller of a module recognizable for the CMS as such.
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * 
 * @property BackendController $cmsController
 */
abstract class CmsModuleBackendController extends CmsModuleController
{
	/** @var string */
	protected $baseLink;
	/** @var CmsBackendMessage[] */
	protected $messages;

	/**
	 * @param BackendController $moduleController
	 * @param string $moduleName
	 */
	public function __construct(BackendController $moduleController, $moduleName)
	{
		parent::__construct($moduleController, $moduleName);

		$this->baseLink = '/backend/module/' . $moduleName;
		$this->messages = $this->setMessages();
	}

	/**
	 * Set the messages for this module which you can render for successfully write to db or warn / inform for some reason
	 * 
	 * @return CmsBackendMessage[]
	 */
	protected function setMessages()
	{
		return array(
			'created' => new CmsBackendMessage('The entry has been created successfully', CmsBackendMessage::MSG_TYPE_SUCCESS),
			'updated' => new CmsBackendMessage('The entry has been updated successfully', CmsBackendMessage::MSG_TYPE_SUCCESS),
			'deleted' => new CmsBackendMessage('The entry has been deleted successfully', CmsBackendMessage::MSG_TYPE_SUCCESS)
		);
	}

	/**
	 * Registers a new message for this module
	 * 
	 * @param string|int $key The key of the message
	 * @param string $text The text of the message
	 * @param string $type The message type
	 */
	protected function registerMessage($key, $text, $type)
	{
		$this->messages[$key] = new CmsBackendMessage($text, $type);
	}

	/**
	 * Sets a key of a message to be displayed on the next page generated. After that the temp key gets reset.
	 * 
	 * @param string|int $msgKey The message key for displaying the message content
	 * @throws \InvalidArgumentException
	 */
	protected function setMessageKeyForNextPage($msgKey)
	{
		if(isset($this->messages[$msgKey]) === false)
			throw new \InvalidArgumentException('The message with the key ' . $msgKey . ' is not registered for this module.');

		$_SESSION['cms_backend_msg_key'] = $msgKey;
	}

	/**
	 * Sets a message which will be rendered at the next page view
	 * 
	 * @param CmsBackendMessage $cmsBackendMessage
	 */
	protected function setMessageForNextPage(CmsBackendMessage $cmsBackendMessage)
	{
		$_SESSION['cms_backend_msg_key'] = $cmsBackendMessage;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function renderModuleContent($tplFile, array $tplVars = array())
	{
		$tplVars['message'] = $this->renderPendingMessage();
		$tplVars['module_settings'] = $this->moduleSettings;
		$tplVars['base_link'] = $this->baseLink;
		
		return new CmsModuleResponse($tplFile . '.html', $tplVars);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getBaseURI()
	{
		return $this->baseLink;
	}

	/**
	 * Returns a unordered HTML list which includes all the pending messages
	 * 
	 * @return string|null A unordered list with all pending messages or null if no messages are pending
	 */
	protected function renderPendingMessage()
	{
		$msgKey = isset($_SESSION['cms_backend_msg_key'])?$_SESSION['cms_backend_msg_key']:null;

		if($msgKey === null || (($msgKey instanceof CmsBackendMessage) === false && isset($this->messages[$msgKey]) === false))
			return null;

		/** @var CmsBackendMessage $msgObj */
		$msgObj = ($msgKey instanceof CmsBackendMessage)?$msgKey:$this->messages[$msgKey];

		// reset message key
		$_SESSION['cms_backend_msg_key'] = null;

		return CmsUtils::renderMessage($msgObj->getMessage(), $msgObj->getType());
	}

	public function getEditLanguage()
	{
		return isset($_SESSION['mod_edit_lang']) ? $_SESSION['mod_edit_lang'] : $this->cmsController->getCore()->getLocaleHandler()->getLanguage();
	}

	/**
	 * Renders a language selector for editing content. The current active language is preselected.
	 * 
	 * @return string The preselected select box in a form
	 */
	public function getEditLanguageChanger()
	{
		$stmntLangs = $this->cmsController->getDB()->prepare("SELECT code, name FROM language ORDER BY name");

		$resLangs = $this->cmsController->getDB()->select($stmntLangs);

		$langOptsHtml = '';

		foreach($resLangs as $l) {
			$selected = (isset($_SESSION['mod_edit_lang']) && $_SESSION['mod_edit_lang'] == $l->code) ? ' selected' : null;
			$langOptsHtml .= '<option value="' . $l->code . '"' . $selected . '>' . $l->name . '</option>';
		}

		return '<form method="post" action="' . $_SERVER['REQUEST_URI'] . '" class="mod-edit-lang">
			<label for="lang">Edit content language</label>
			<select id="lang" name="mod_edit_lang">' . $langOptsHtml . '</select>
			<input type="submit" value="change" class="submit">
		</form>';
	}

	/**
	 * A method to enable file uploads for backend modules. Well this needs some rewrite later and should not really be
	 * inside this class anymore.
	 * 
	 * @param string $savePath
	 * @param null $callback
	 *
	 * @return HttpResponse
	 */
	public function processFileUploads($savePath, $callback = null)
	{
		try {

			if($this->cmsController->getHttpRequest()->getVar('nojs') !== null) {
				ob_start();
			}

			$filesResponse = array();
			$countFiles = count($_FILES['files']['name']);

			for($i = 0; $i < $countFiles; ++$i) {
				$file = array(
					'name' => $_FILES['files']['name'][$i],
					'tmp_name' => $_FILES['files']['tmp_name'][$i],
					'type' => $_FILES['files']['type'][$i],
					'error' => $_FILES['files']['error'][$i],
					'size' => $_FILES['files']['size'][$i]
				);

				if($callback !== null) {
					call_user_func_array($callback, array($savePath, $file));
				} else {
					$resMove = move_uploaded_file($file['tmp_name'], $savePath . $file['name']);

					if($resMove === false)
						throw new HttpException('Could not move file to: ' . $savePath . $file['name']);
				}

				$fileResponse = new \stdClass();
				$fileResponse->name = $file['name'];
				$fileResponse->size = $file['size'];
				$fileResponse->type = $file['type'];
				$fileResponse->url = 'http://www.google.com/robots.txt';
				$fileResponse->delete_type = 'DELETE';

				$filesResponse[] = $fileResponse;
			}

			$response = new \stdClass();
			$response->files = $filesResponse;

			//echo '{"files":[{"name":"special.csv (2).php","size":5300,"type":"application\/octet-stream","url":"http:\/\/henauer-kaffee.ch.metdev.ch\/upload\/special.csv%20%282%29.php","delete_url":"http:\/\/henauer-kaffee.ch.metdev.ch\/?file=special.csv%20%282%29.php","delete_type":"DELETE"}]}';

			header_remove();

			if($this->cmsController->getHttpRequest()->getVar('nojs') === null) {
				return new HttpResponse(200, json_encode($response),	array(
					'Content-Type' => 'text/html; charset=utf-8'
				));
			}

			//$json = ob_get_clean();

			$html = '<!doctype html>
			<html>
				<head>
					<title>File Upload</title>

					<link rel="stylesheet" type="text/css" href="/css/browse.css">
				</head>
				<body>
				<p>The following files were uploaded:</p>
				<ul>';

			foreach($response->files as $f) {
				$html .= '<li>' . $f->name . '</li>';
			}

			$html .= '</ul>
			<p><a href="' . $_SERVER['HTTP_REFERER']. '">back</a></p>
			</body></html>';

			return new HttpResponse(200, $html,	array(
				'Content-Type' => 'text/html; charset=utf-8'
			));
		} catch(\Exception $e) {
			return new HttpResponse(500, 'Could not upload some files. View log for more information', array(
				'Content-Type' => 'text/html; charset=utf-8'
			));
		}
	}
}

/* EOF */