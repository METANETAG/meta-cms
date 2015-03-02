<?php

namespace ch\metanet\cms\module\mod_search\backend;

use ch\metanet\cms\common\CmsModuleBackendController;
use ch\metanet\cms\common\CmsUtils;
use ch\metanet\cms\common\LuceneUtils;
use ch\metanet\cms\controller\backend\ModuleController;
use ch\metanet\cms\controller\common\BackendController;
use ch\metanet\cms\module\mod_gallery\model\GalleryModel;
use ch\metanet\cms\module\mod_highlights\model\HighlightsModel;
use ch\metanet\cms\module\mod_slideshows\model\SlideshowsModel;
use ch\metanet\cms\tablerenderer\Column;
use ch\metanet\cms\tablerenderer\DateColumnDecorator;
use ch\metanet\cms\tablerenderer\FileSizeColumnDecorator;
use ch\metanet\cms\tablerenderer\LinkColumnDecorator;
use ch\metanet\cms\tablerenderer\RewriteColumnDecorator;
use ch\timesplinter\common\StringUtils;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\HttpResponse;
use ch\metanet\cms\tablerenderer\TableRenderer;
use ch\timesplinter\core\RequestHandler;
use timesplinter\tsfw\db\DB;
use ch\timesplinter\formhelper\FormHelper;
use ZendSearch\Lucene\Lucene;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class BackendSearchController extends CmsModuleBackendController
{
	public function __construct(BackendController $moduleController, $moduleName)
	{
		parent::__construct($moduleController, $moduleName);

		//$this->baseLink = '/backend/module/mod_search';
		$this->controllerRoutes = array(
			'/' => array(
				'GET' => 'getModuleOverview'
			),
			'/optimize' => array(
				'GET' => 'optimizeSearchIndex'
			),

			'/rebuild' => array(
				'GET' => 'rebuildSearchIndex'
			)
		);
	}

	public function getModuleOverview() {
		$searchIndex = LuceneUtils::openOrCreate($this->cmsController->getCore()->getSiteRoot() . 'index' . DIRECTORY_SEPARATOR . $this->getEditLanguage());

		$tplVars = array(
			'siteTitle' => 'Module: Search',
			'base_link' => $this->baseLink,
			'indexed_docs' => $searchIndex->count(),
			'index_name' => $this->getEditLanguage()
		);

		return $this->renderModuleContent('mod-search-overview', $tplVars);
	}

	public function optimizeSearchIndex(){
		$startTime = microtime(true);

		$indexRootPath = $this->cmsController->getCore()->getSiteRoot() . 'index' . DIRECTORY_SEPARATOR;

		foreach(scandir($indexRootPath) as $d) {
			if(is_dir($indexRootPath . $d) === false || in_array($d, array('.', '..')))
				continue;

			try {
				$searchIndex = Lucene::open($indexRootPath . $d);
				$searchIndex->optimize();
				$searchIndex->commit();
			} catch(\Exception $e) {
				continue;
			}
		}



		$endTime = microtime(true);

		$tplVars = array(
			'siteTitle' => 'Optimize search index',
			'duration'=> round($endTime-$startTime,3)
		);

		return $this->renderModuleContent('mod-search-optimize', $tplVars);
	}

	public function rebuildSearchIndex(){
		$startTime = microtime(true);

		$currentEnv = $this->cmsController->getCore()->getCurrentDomain()->environment;
		$securityToken = $this->cmsController->getCore()->getSettings()->cms->$currentEnv->security_token;
		
		$ch = curl_init('https://' . $this->cmsController->getHttpRequest()->getHost() . '/metacms/services/mod_search/index?token=' . $securityToken);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false
		));
		
		$content = curl_exec($ch);
		
		if(($errorNo = curl_errno($ch)) !== 0) {
			$content = '<span style="color: #f00">' . curl_error($ch) . '</span>';
		}
		
		curl_close($ch);
		
		$endTime = microtime(true);

		//echo 'rebuilt in duration: ' . round($endTime-$startTime,3) . ' seconds'; exit;

		$tplVars = array(
			'siteTitle' => 'Rebuild search index',
			'duration'=> round($endTime-$startTime,3),
			'content' => $content
		);

		return $this->renderModuleContent('mod-search-rebuild', $tplVars);
	}
}

/* EOF */