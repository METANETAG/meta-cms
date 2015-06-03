<?php

namespace ch\metanet\cms\module\mod_search\frontend;

use ch\metanet\cms\common\CmsModuleFrontendController;
use ch\metanet\cms\controller\common\FrontendController;
use ch\metanet\cms\module\mod_search\common\CmsSearchHighlighter;
use ch\metanet\cms\module\mod_search\common\Indexer;
use ch\metanet\cms\module\mod_search\model\SearchModel;
use timesplinter\tsfw\common\JsonUtils;
use ch\timesplinter\core\FrameworkLoggerFactory;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpResponse;
use ZendSearch\Lucene\Analysis\Analyzer\Analyzer;
use ZendSearch\Lucene\Analysis\Analyzer\Common\Utf8\CaseInsensitive;
use ZendSearch\Lucene\Document;
use ZendSearch\Lucene\Lucene;
use ZendSearch\Lucene\Search\QueryHit;
use ZendSearch\Lucene\Search\QueryParser;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class FrontendSearchController extends CmsModuleFrontendController
{
	public function __construct(FrontendController $frontendController, $moduleName)
	{
		parent::__construct($frontendController, $moduleName);

		$this->controllerRoutes = array(
			'/' => array(
				'GET' => 'showSearchResults'
			),
			'/rebuild-index' => array(
				'GET' => 'rebuildIndex'
			)
		);

		Analyzer::setDefault(new CaseInsensitive());
		QueryParser::setDefaultEncoding('UTF-8');

		$this->registerService('index', 'generateIndex');

		$cmsPage = $frontendController->getCmsPage();

		if($cmsPage !== null)
			$cmsPage->setLastModified(date('Y-m-d H:i:s'));
	}

	public function showSearchResults()
	{
		$keywords = $this->cmsController->getHttpRequest()->getVar('q');

		if($keywords === null)
			return $this->cmsController->getCmsView()->render('mod-search-results.html');

		$cmsResults = $this->getCmsSearchResults($keywords);

		return $this->cmsController->getCmsView()->render('mod-search-results.html', array(
			'keywords' => htmlspecialchars($keywords),
			'result_sets' => $cmsResults
		));
	}

	public function generateIndex()
	{
		$logger = FrameworkLoggerFactory::getLogger($this);
		$responseCode = 200;
		$responseStr = null;

		if($this->cmsController->getHttpRequest()->getVar('token') !== $this->cmsController->getCmsSettings()->security_token)
			throw new HttpException('Access denied! No or wrong security token submitted.', 403);

		ob_start();

		try {
			echo "Load mod_search settings...\n";

			$searchSettingsPath = $this->cmsController->getCore()->getSiteRoot() . 'settings' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mod_search.config.json';
			$searchSettings = JsonUtils::decodeFile($searchSettingsPath);

			$indexesBasePath = $this->cmsController->getCore()->getSiteRoot() . 'index' . DIRECTORY_SEPARATOR;

			$indexer = new Indexer($this->cmsController->getDB(), $this->cmsController->getCore(), $indexesBasePath);
			$indexer->start($searchSettings);

			unset($indexer);

			$responseStr = ob_get_clean();
		} catch(\Exception $e) {
			$logger->error('Could not create index: ', $e);

			$responseCode = 500;
			$responseStr = 'Could not create index: ' . $e->getMessage();
		}

		return new HttpResponse($responseCode, $responseStr, array(
			'Content-Type' => 'text/plain; charset=utf-8'
		));
	}

	/**
	 * @param string $keywords
	 *
	 * @return \stdClass[]
	 */
	protected function getCmsSearchResults($keywords)
	{
		$searchModel = new SearchModel($this->cmsController->getDB());

		$pageLang = $this->cmsController->getCmsPage()->getLanguage();

		$searchIndex = Lucene::open($this->cmsController->getCore()->getSiteRoot() . 'index' . DIRECTORY_SEPARATOR . $pageLang);
		/*$query = new Boolean(); // new Fuzzy()
		$query->addSubquery(QueryParser::parse(
			$keywords
		), true);*/
		QueryParser::suppressQueryParsingExceptions();
		
		$query = QueryParser::parse(
			/*QueryParser::escape(*/$keywords//)
		);


		//$hits = $searchIndex->find($query, 'score', SORT_NUMERIC, SORT_DESC);
		$hits = $searchIndex->find($query);
		//echo'<pre>'; var_dump(/*$hits, */$indexSize, $documents);

		$searchResultsArr = array();

		$highlighter = new CmsSearchHighlighter($keywords);
		//$highlighter = new DefaultHighlighter();

		foreach($hits as $hit) {
			/** @var QueryHit $hit */
			$searchResult = new \stdClass();

			// Gibt Zend_Search_Lucene_Document Objekte für diesen Treffer zurück
			/** @var Document $document */
			$document = $hit->getDocument();

			$doc = $searchModel->getDocumentByID($document->getFieldUtf8Value('ID'));

			if($doc->getID() === null)
				continue;

			$fldType = $doc->getType();

			if($fldType !== 'core_page') {
				$contentChunks = $highlighter->highlightMatches(strip_tags($doc->getDescription()), 'UTF-8');

				if($contentChunks == '')
					$contentChunks = null;

				// Gibt ein Zend_Search_Lucene_Field Objekt von
				// Zend_Search_Lucene_Document zurück
				$searchResult->title = $highlighter->highlightMatches(strip_tags($doc->getTitle()), 'UTF-8');
				$searchResult->description = $contentChunks;
				$searchResult->url = $doc->getPath();

				if(isset($searchResultsArr[$fldType]) === false) {
					$stmntModName = $this->cmsController->getDB()->prepare("
						SELECT manifest_content FROM cms_mod_available WHERE name = ?
					");
					$resModName = $this->cmsController->getDB()->select($stmntModName, array($fldType));

					$displayName = $fldType;

					try {
						$manifestObj = JsonUtils::decode($resModName[0]->manifest_content);

						if(isset($manifestObj->name->$pageLang))
							$displayName = $manifestObj->name->$pageLang;
						elseif(isset($manifestObj->name->en))
							$displayName = $manifestObj->name->en;
					} catch(\Exception $e) {

					}

					$searchResultsArr[$fldType] = new \stdClass();
					$searchResultsArr[$fldType]->title = $displayName;
					$searchResultsArr[$fldType]->results = array();

				}

				$searchResultsArr[$doc->getType()]->results[] = $searchResult;
			} else {
				$contentChunks = $this->createChunkedHighlighting($highlighter->highlightMatches(strip_tags($doc->getDescription()), 'UTF-8'));

				if($contentChunks == '')
					$contentChunks = null;

				// Gibt ein Zend_Search_Lucene_Field Objekt von
				// Zend_Search_Lucene_Document zurück
				$searchResult->title = $highlighter->highlightMatches(strip_tags($doc->getTitle()), 'UTF-8');
				$searchResult->description = $contentChunks;
				$searchResult->url = $doc->getPath();

				if(isset($searchResultsArr[$fldType]) === false) {
					$searchResultsArr[$fldType] = new \stdClass();
					$searchResultsArr[$fldType]->title = 'Andere Suchresultate';
					$searchResultsArr[$fldType]->results = array();

				}

				$searchResultsArr[$doc->getType()]->results[] = $searchResult;
			}
		}

		return $searchResultsArr;
	}

	protected function createChunkedHighlighting($stringToHighlight, $chunks = 5, $numWords = 5)
	{
		$elements = explode('|', $stringToHighlight);

		$newContent = array();

		foreach($elements as $e) {
			$e = str_replace(array("\n"), array(' '), $e);

			$returnVal = preg_match_all('/<span class="search-term">.+?<\/span>/', $e, $matches, PREG_OFFSET_CAPTURE);

			if($returnVal === 0 || $returnVal === false)
				continue;

			//var_dump($matches[0]);
			$currentOffset = 0;
			$matchesCount = count($matches[0]);

			for($i = 0; $i < $matchesCount; ++$i) {
				$currentMatch = $matches[0][$i];
				$nextMatch = isset($matches[0][$i+1])?$matches[0][$i+1]:null;

				// n words forward
				$before = substr($e, $currentOffset, ($currentMatch[1] - $currentOffset));
				$beforeWords = explode(' ', $before);

				$wordsBeforeArr = array();
				$wordsBeforeCount = 0;

				foreach($beforeWords as $w) {
					if($wordsBeforeCount === $numWords)
						break;

					$wordsBeforeArr[] = $w;

					if(strlen($w) <= 2)
						continue;

					++$wordsBeforeCount;
				}

				// n words backward
				$afterBegin = $currentMatch[1] + strlen($currentMatch[0]);

				$after = ($nextMatch !== null)?substr($e, $afterBegin, $nextMatch[1] - $afterBegin):substr($e, $afterBegin);
				$afterWords = explode(' ', $after);

				$wordsAfterArr = array();
				$wordsAfterCount = 0;

				foreach($afterWords as $w) {
					if($wordsAfterCount === $numWords)
						break;

					$wordsAfterArr[] = $w;

					if(strlen($w) <= 2)
						continue;

					++$wordsAfterCount;
				}

				$currentOffset = $currentMatch[1] + strlen($currentMatch[0]) + strlen($after);

				$newContent[] = implode(' ', $wordsBeforeArr) . ' ' . $currentMatch[0] . ' ' . implode(' ', $wordsAfterArr);
			}
		}

		$chunksTotal = count($newContent);

		if($chunksTotal > $chunks)
			$chunksTotal = $chunks;

		if($chunksTotal === 0)
			return null;

		$randKeys = array_rand($newContent, $chunksTotal);
		$randKeys = is_array($randKeys)?$randKeys:array($randKeys);
		asort($randKeys);

		$chunkedArr = array();

		foreach($randKeys as $k)
			$chunkedArr[] = $newContent[$k];

		return implode(' ... ', $chunkedArr) . ' ...';
	}
}

/* EOF */