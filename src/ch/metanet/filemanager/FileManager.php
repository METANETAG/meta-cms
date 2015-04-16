<?php

namespace ch\metanet\filemanager;

/**
 * Description of Browser
 *
 * @author Pascal Münst <entwicklung@metanet.ch>
 * @copyright (c) 2012, METANET AG
 */
class FileManager
{
	private $db;
	private $fileManager;
	private $rootDir;
	
	public function __construct($db, $rootDir)
	{
		$this->db = $db;
		$this->rootDir = $rootDir;
		$this->fileManager = new FileHandler($db, $rootDir);
	}


	private function convertBytes($value)
	{
		if (is_numeric($value))
			return $value;

		$value_length = strlen($value);
		$qty = substr($value, 0, $value_length - 1);
		$unit = strtolower(substr($value, $value_length - 1));

		switch($unit) {
			case 'k': $qty *= 1024; break;
			case 'm': $qty *= 1048576; break;
			case 'g': $qty *= 1073741824; break;
		}

		return $qty;
	}

	protected function getMaxUploadSize()
	{
		$maxUploadFileSize = $this->convertBytes(ini_get('upload_max_filesize'));
		$postMaxSize = $this->convertBytes(ini_get('post_max_size'));

		$uploadSize = ($maxUploadFileSize < $maxUploadFileSize)?$maxUploadFileSize:$postMaxSize;

		return round($uploadSize/1024/1024, 2) . ' MB';
	}

	public function getBrowserWindow($view, array $filter = array('types' => null, 'keyword' => null))
	{
		$html = '<!DOCTYPE html>
		<html>
		<head>
		<meta charset="UTF-8">
		<title>METAfilemanager</title>
		<link rel="stylesheet" type="text/css" href="/css/browse.css">
		</head>
		<body>
		<div class="header">
			<h1><a href="javascript:alert(\'Copyright (c) 2013 by METANET AG\n\nMade with ♥ in Winterthur\n\nPascal Münst\nSascha Scholz\');"><b>META</b>filemanager</a></h1>

			<ul class="nav-view"><li><a href="thumbs" class="change-view"><span class="thumb">Thumbs</span></a></li><li><a href="list" class="change-view"><span class="list">List</span></a></li></ul>		
			<form method="post" action="' . $_SERVER['REQUEST_URI'] . '" class="search">
			<input type="text" name="searchterm" placeholder="' . (($filter['keyword'] !== null)?htmlspecialchars($filter['keyword']):'Search') . '">
			</form>
		</div>
		
		<div class="tree">
			<form class="upload" action="/backend/filemanager/upload?nojs" enctype="multipart/form-data" method="post">
				<input id="fileupload" type="file" name="files[]" data-url="/backend/filemanager/upload" multiple>
				<input type="submit" class="submit" value="upload">
			</form>
			<div id="dragndrop">
				<div id="dropzone">Dateien hier ablegen</div>
				<div id="progress">
					<span class="bar"></span>					
					<span class="label"></span>
				</div>
				<!--<div class="bitrate"></div>-->
				<ul class="uploaded-files"></ul>
			</div>
			<p>Max. file size: ' . $this->getMaxUploadSize() . '<br>Max. files: ' . ini_get( 'max_file_uploads' ) . '</p>
		</div>
		<div class="content-wrap">
			<div class="content">' . $this->getFileList($view, $filter) . '</div>
		</div>

		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
		<script>window.jQuery || document.write(\'<script src="/js/jquery-1.9.0.min.js"><\/script>\')</script>
		<script src="/js/jquery.ui.widget.js"></script>
		<script src="/js/jquery.iframe-transport.js"></script>
		<script src="/js/jquery.fileupload.js"></script>
		<script src="/js/cms.filechooser.js"></script>
		<script>';

		if(isset($_GET['CKEditorFuncNum'])) {
			$html .= "$('body').on('click', '.js-open', function() {
				openCKEditor($(this).attr('href'));

				return false;
			});";
		} else {
			$html .= "$('body').on('click', '.js-open', function() {
				var fileLink = $(this).attr('href');
				openFile(fileLink);

				return false;
			});";
		}

		$html .= '</script>
			</body>
		</html>';

		return $html;
	}
	
	public function getFileList($view, array $filter = array('types' => null, 'keyword' => null))
	{
		// Create type array
		$typesAvailable = $this->fileManager->getFileCategories(); 
		$types = null;
		
		if($filter['types'] !== null) {
			$types = array();
			$filterTypes = explode(',', $filter['types']);
			
			foreach($filterTypes as $ft) {
				if(!isset($typesAvailable[strtolower($ft)]))
					continue;
				
				$types += $typesAvailable[strtolower($ft)]['types'];
			}
		}

		$typeIcons = array('application/pdf' => '/images/icon-pdf.png');

		$html = '';
		$files = $this->fileManager->getAllFiles(
			array('column' => 'filename', 'sort' => 'ASC'), 
			$filter['keyword'], 
			array_keys($this->fileManager->getFileCategories())
		);
		
		if(count($files) === 0)
			return '<p>No files found</p>';
		
		if($view === 'thumbs') {
			$thumbDir = $this->rootDir . '.thumbs' . DIRECTORY_SEPARATOR;

			if(file_exists($thumbDir) === false)
				mkdir($thumbDir);

			$html .= '<ul class="files-thumbs clearfix">';
		
			foreach($files as $file) {
				/*if($this->filter['keyword'] !== null && strpos($file->filename, $this->filter['keyword']) === false)
					continue;*/

				if($types !== null && !in_array($file->getType(), $types))
					continue;
				
				$fileType = $this->fileManager->getDirForMimeType($file->getType());

				if($fileType === 'image') {
					$serverPath = $this->rootDir .  'image/' . $file->getNameSys();

					if(file_exists($serverPath) === false)
						continue;

					$thumbPath = $thumbDir . $file->getNameSys() . '.jpg';
					$imgPath = '/files/' . $fileType . '/' . $file->getNameSys() . '/' . $file->getName();
					
					if(file_exists($thumbPath) === false) {
						// create thumb
						$thumbnailer = \PhpThumbFactory::create($serverPath);
						$thumbnailer->adaptiveResize(175,175)->save($thumbPath);
					}
					
					$imgSize = ($file->getOtherInfo() !== null)?str_replace(';', 'x', $file->getOtherInfo()) . ', ':null;
					$imgTitle = $file->getName() . ' (' . $imgSize . $file->getType() . ', ' . number_format(round($file->getSize()/1024,2),2) . ' KB)';
					$html .= '<li><a href="' . $imgPath .'" class="img js-open" title="' . $imgTitle . '"><img src="/backend/filemanager/thumb/' . $file->getNameSys() . '.jpg" alt="' . $imgTitle . '"></a><a class="delete js-delete" href="?delete=' . $file->getID() . '">löschen</a></li>';
				} else {
					$iconFile = isset($typeIcons[$file->getType()]) ? $typeIcons[$file->getType()] : '/images/icon-file.png';

					$fileTitle = $file->getName() . ' (' . $file->getType() . ', ' . number_format(round($file->getSize()/1024,2),2) . ' KB)';
					$html .= '<li><a href="/files/' . $fileType . '/' . $file->getNameSys() . '/' . $file->getName() .'" class="img js-open" title="' . $fileTitle . '"><img src="' . $iconFile . '" alt=""></a><a class="delete js-delete" href="?delete=' . $file->getID() . '">löschen</a></li>';
				}
			}
			
			$html .= '</ul>';
		} elseif($view == 'list') {
			$html .= '<ul class="files-list">';
		
			foreach($files as $file) {
				/*if($filter['keyword'] !== null && strpos($file->filename, $filter['keyword']) === false)
					continue;
				*/
				if($types !== null && !in_array($file->getType(), $types))
					continue;

				$html .= '<li class="clearfix"><span><a href="/files/' . $this->fileManager->getDirForMimeType($file->getType()) . '/' . $file->getNameSys() . '/' . $file->getName() .'" class="file-link js-open">' . $file->getName() . '</a> <em class="file-type">' . $file->getType() . ', ' . number_format(round($file->getSize()/1024,2),2) . ' KB</em></span> <a class="delete js-delete" href="?delete=' . $file->getID() . '">löschen</a></li>';
			}
			
			$html .= '</ul>';
		} else {
			$html = '<p>Unsupported view mode</p>';
		}
		
		return $html;
	}
	
	public function deleteFile($fileID)
	{
		if($this->fileManager->deleteFile($fileID)) {
			header('HTTP/1.1 200 OK');
		} else {
			header('HTTP/1.1 500 Internal Error');
		}
		
		exit;
	}
}

/* EOF */