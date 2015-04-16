<?php

namespace ch\metanet\cms\controller\backend;

use ch\metanet\cms\common\BackendControllerUnprotected;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\filemanager\FileHandler;
use ch\metanet\filemanager\FileManager;
use ch\timesplinter\core\FrameworkLoggerFactory;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpResponse;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\Core;
use ch\timesplinter\core\Route;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class FileManagerController extends BackendController implements BackendControllerUnprotected
{
	private $logger;

	public function __construct(Core $core, HttpRequest $httpRequest, Route $route)
	{
		parent::__construct($core, $httpRequest, $route);

		$this->logger = FrameworkLoggerFactory::getLogger($this);
	}

	public function getBrowserWindow()
	{
		if(isset($_SESSION['view']) === false)
			$_SESSION['view'] = isset($_GET['view'])?$_GET['view']:'list';
		
		if(isset($_SESSION['filter']) === false)
			$_SESSION['filter'] = array(
				'keyword' => null,
				'types' => null
			);
		
		if(isset($_GET['type']) && $_GET['type'] != $_SESSION['filter']['types'])
			$_SESSION['filter']['types'] = $_GET['type'];
			
		if(isset($_POST['searchterm']) && $_POST['searchterm'] != $_SESSION['filter']['keyword'])
			$_SESSION['filter']['keyword'] = strlen($_POST['searchterm']) > 0 ? strip_tags($_POST['searchterm']) : null;
		
		$browser = new FileManager($this->db, $this->core->getSiteRoot() . '/data/');
		$html = null;

		if(isset($_GET['refresh'])) {
			if(isset($_GET['view']))
				$_SESSION['view'] = $_GET['view'];
			
			$html = $browser->getFileList($_SESSION['view'], $_SESSION['filter']);
		} elseif(isset($_GET['delete'])) {
			$browser->deleteFile($_GET['delete']);
		} else {
			$html = $browser->getBrowserWindow($_SESSION['view'], $_SESSION['filter']);
		}

		return new HttpResponse(200, $html,	array(
			'Content-Type' => 'text/html; charset=utf-8'
		));
	}

	public function processFileUploads()
	{
		try {
			if(isset($_GET['nojs'])) {
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

				$savePath = $this->core->getSiteRoot() . 'data' . DIRECTORY_SEPARATOR;
				
				$fileManager = new FileHandler($this->db, $savePath);
				$fileObj = $fileManager->storeFile($file);

				$subDir = ($fileObj->getCategory() !== null) ? $fileObj->getCategory() . '/' : null;
				
				$fileResponse = new \stdClass();
				$fileResponse->name = $file['name'];
				$fileResponse->size = $file['size'];
				$fileResponse->type = $file['type'];
				$fileResponse->url = '/files/' . $subDir . $fileObj->getNameSys() . DIRECTORY_SEPARATOR . $fileObj->getName();
				$fileResponse->delete_type = 'DELETE';

				$filesResponse[] = $fileResponse;
			}

			$response = new \stdClass();
			$response->files = $filesResponse;
			
			header_remove();

			if(!isset($_GET['nojs'])) {
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
			$this->logger->error('Could not upload some files', $e);

			return new HttpResponse(500, 'Could not upload some files. View log for more information', array(
				'Content-Type' => 'text/html; charset=utf-8'
			));
		}
	}

	public function getThumb()
	{
		$fileName = $this->route->getParam(0) . '.jpg';
		$filePath = $this->core->getSiteRoot() . 'data/.thumbs/' . $fileName;

		if(file_exists($filePath) === false)
			return $this->core->getErrorHandler()->displayHttpError(404, $this->httpRequest);

		if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= filemtime($filePath))
			return new HttpResponse(304);

		return new HttpResponse(200, $filePath,	array(
			'Content-Description' => 'File Transfer',
			'Content-Type' => 'image/jpeg',
			'Content-Disposition' => 'filename="' . $fileName . '"',
			'Content-Length' => filesize($filePath),
			'Content-Transfer-Encoding' => 'binary',
			'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000),
			'Cache-Control' => 'public, max-age=31536000',
			'Last-Modified' => date('D, d M Y H:i:s \G\M\T', filemtime($filePath)),
			'Pragma' => null
		), true);
	}

	public function getFile()
	{
		$headers = array();

		$filename = $this->route->getParam(1);

		$stmnt = $this->db->prepare("SELECT filename, filenamesys, filesize, filetype, send, category, upload_date FROM file WHERE filenamesys = ?");
		$res = $this->db->select($stmnt, array($filename));

		if(count($res) === 0) {
			throw new HttpException('Could not find file: ' . $filename, 404);
		}

		$fileRes = $res[0];

		$filePath = $this->core->getSiteRoot() . 'data' . DIRECTORY_SEPARATOR . $fileRes->category . DIRECTORY_SEPARATOR . $fileRes->filenamesys;

		if(file_exists($filePath) === false)
			throw new HttpException('File not found', 404);

		if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= filemtime($filePath))
			return new HttpResponse(304);

		$headers['Content-Description'] = 'File Transfer';
		$headers['Content-Type'] = $fileRes->filetype;

		if((int)$fileRes->send === 0)
			$headers['Content-Disposition'] = 'filename="' . $fileRes->filename . '"';
		else
			$headers['Content-Disposition'] = 'attachment; filename="' . $fileRes->filename . '"';

		$dtUploadDate = new \DateTime($fileRes->upload_date);

		$headers['Content-Transfer-Encoding'] = 'binary';
		$headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000);
		$headers['Last-Modified'] = $dtUploadDate->format('D, d M Y H:i:s \G\M\T');
		$headers['Cache-Control'] = 'public, max-age=31536000';
		$headers['Pragma'] = null;

		$fileSize = (int)$fileRes->filesize;

		if($fileSize === null) {
			$fileSize = filesize($filePath);

			$stmntUpFsize = $this->db->prepare("UPDATE file SET filesize = ? WHERE ID = ?");
			$this->db->update($stmntUpFsize, array($fileSize, $filename));
		}

		if($fileSize !== null)
			$headers['Content-Length'] = $fileSize;

		return new HttpResponse(200, $filePath, $headers, true);
	}

	public static function getUnprotectedMethods()
	{
		return array('getFile');
	}
}

/* EOF */