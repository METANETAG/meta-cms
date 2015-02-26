<?php


namespace ch\metanet\cms\module\mod_search\plugins;
use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsElementSearchable;
use ch\metanet\cms\common\CmsPage;
use ch\metanet\cms\common\CmsPlugin;
use ch\metanet\cms\common\LuceneUtils;
use ch\metanet\cms\controller\common\CmsController;
use ch\metanet\cms\model\ElementModel;
use ch\metanet\cms\model\PageModel;
use ch\metanet\cms\model\RouteModel;
use ch\metanet\cms\module\layout\LayoutElement;
use ch\metanet\cms\module\mod_search\model\SearchModel;
use ch\timesplinter\common\JsonUtils;
use ch\timesplinter\core\FrameworkLoggerFactory;
use ch\timesplinter\db\DB;
use ZendSearch\Lucene\Document;
use ZendSearch\Lucene\Index\Term;
use ZendSearch\Lucene\Lucene;
use ZendSearch\Lucene\SearchIndexInterface;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class UpdateSearchIndexPlugin extends CmsPlugin {
	private $logger;
	private $pageModel;
	private $routeModel;
	private $elementModel;
	private $searchModel;

	public function __construct(CmsController $cmsController) {
		parent::__construct($cmsController);

		$this->pageModel = new PageModel($this->cmsController->getDB());
		$this->routeModel = new RouteModel($this->cmsController->getDB());
		$this->elementModel = new ElementModel($this->cmsController->getDB());
		$this->searchModel = new SearchModel($this->cmsController->getDB());

		$this->logger = FrameworkLoggerFactory::getLogger($this);
	}

	private function indexPage(SearchIndexInterface $searchIndexInterface, CmsPage $cmsPage) {
		$elementTree = $this->elementModel->getElementTree($cmsPage);
		$pageRoutes = $this->routeModel->getRoutesByPageID($cmsPage->getID());

		if(count($pageRoutes) <= 0)
			return;

		$pageSearchContent = $this->renderElementTreeRecursive($elementTree);

		$doc = $this->searchModel->getDocument($cmsPage->getID(), 'core_page');

		$doc->setInternalID($cmsPage->getID());
		$doc->setType('core_page');
		$doc->setTitle($cmsPage->getTitle());
		$doc->setDescription($pageSearchContent);
		$doc->setPath($pageRoutes[0]->pattern);
		$doc->setLanguage($cmsPage->getLanguage());

		$docDbID = $this->searchModel->saveDocument($doc);

		$term = new Term($docDbID, 'ID');
		$docIds  = $searchIndexInterface->termDocs($term);

		foreach($docIds as $docID)
			$searchIndexInterface->delete($docID);

		$document = new Document();
		$document->addField(Document\Field::keyword('ID', $docDbID));
		$document->addField(Document\Field::unStored('title', $cmsPage->getTitle()));
		$document->addField(Document\Field::unStored('description', $cmsPage->getDescription()));
		$document->addField(Document\Field::unStored('content', $pageSearchContent));
		$document->addField(Document\Field::unStored('path', $pageRoutes[0]->pattern));

		$searchIndexInterface->addDocument($document);
	}

	private function indexPageRecursive(SearchIndexInterface $searchIndexInterface, CmsPage $cmsPage) {
		$this->indexPage($searchIndexInterface, $cmsPage);

		foreach($this->pageModel->getChildPagesByPageId($cmsPage->getID()) as $cp) {
			$childCmsPage = $this->pageModel->getPageByID($cp);

			$this->indexPageRecursive($searchIndexInterface, $childCmsPage);
		}
	}

	public function pageModified($pageID) {

		$cmsPage = $this->pageModel->getPageByID($pageID);

		// Save in Lucene
		$searchIndex = Lucene::open($this->cmsController->getCore()->getSiteRoot() . 'index' . DIRECTORY_SEPARATOR . $cmsPage->getLanguage());

		//$this->indexPageRecursive($searchIndex, $cmsPage);
		$this->indexPage($searchIndex, $cmsPage);

		$searchIndex->commit();
	}

	public function modContentModified($modName, $funcName, $data = null) {
		$elements = $this->elementModel->getElementsByModuleName($modName);

		$this->logger->debug('update search index for pages which have elements from ' . $modName  . ' (function: ' . $funcName . ')', array($elements));
		$this->updateModuleSpecificDocument($modName, $funcName, $data);

		$elementIdsAffected = array();

		foreach($elements as $e)
			$elementIdsAffected[] = $e->ID;

		// Which pages are now modified cause they contain a elementId affected?
		$stmntPages = $this->cmsController->getDB()->prepare("
			SELECT page_IDFK
			FROM element_instance
			WHERE mod_IDFK IN(" . DB::createInQuery($elementIdsAffected) . ")
			GROUP BY page_IDFK
		");

		$resPages = $this->cmsController->getDB()->select($stmntPages, $elementIdsAffected);

		/*$searchIndex = Lucene::open('/tmp/index');

		foreach($resPages as $p) {
			if(count($routeModel->getRoutesByPageID($p->page_IDFK)) <= 0)
				continue;

			$cmsPage = $pageModel->getPageByID($p->page_IDFK);
			$this->indexPage($searchIndex, $cmsPage);
		}

		$searchIndex->commit();*/

		$this->logger->debug('Update this pages cause they contain a elementId affected: ', array($resPages));
	}

	public function rebuildSearchIndex() {
		$pageModel = new PageModel($this->cmsController->getDB());
		$routeModel = new RouteModel($this->cmsController->getDB());
		$allPages = $pageModel->getAllPages();

		$searchIndexes = array();

		foreach($allPages as $p) {
			if(count($routeModel->getRoutesByPageID($p->ID)) <= 0)
				continue;

			if(isset($searchIndexes[$p->language_codeFK]) === false)
				$searchIndexes[$p->language_codeFK] = LuceneUtils::openOrCreate($this->cmsController->getCore()->getSiteRoot() . 'index' . DIRECTORY_SEPARATOR . $p->language_codeFK);

			$cmsPage = $pageModel->getPageByID($p->ID);
			$this->indexPage($searchIndexes[$p->language_codeFK], $cmsPage);
		}

		// TODO rebuild module indexes

		foreach($searchIndexes as $si) {
			$si->commit();
			$si->optimize();

		}

		unset($searchIndexes);
	}

	private function renderElementTreeRecursive($elementTree) {
		$pageSearchContent = '';

		foreach($elementTree as $e) {
			/** @var CmsElement $e */
			//$this->logger->debug('render search index element: ' . $e->getIdentifier() . ' (#' . $e->getID() . ')');

			/** @var CmsElement|CmsElementSearchable $e */
			if($e instanceof CmsElementSearchable && $e->hasSettings())
				$pageSearchContent .= str_replace("\n", ' ', $e->renderSearchIndexContent($this->cmsController)) . ' | ';

			if($e instanceof LayoutElement) {
				/** @var LayoutElement $e */
				$pageSearchContent .= $this->renderElementTreeRecursive($e->getElements());
			}
		}

		return $pageSearchContent;
	}

	private function updateModuleSpecificDocument($modName, $funcName, $updatedData) {
		$searchConfig = null;

		try {
			$modSearchConfig = $this->cmsController->getCore()->getSiteRoot() . 'settings' . DIRECTORY_SEPARATOR . 'mod_search' . DIRECTORY_SEPARATOR . $modName . '.config.json';

			$searchConfig = JsonUtils::decodeFile($modSearchConfig);
		} catch(\Exception $e) {
			$this->logger->info('Could not update module specific search index: JSON file decode: ' . $modSearchConfig);
			return;
		}

		if(isset($searchConfig->events->$funcName) === false)
			return;

		$lang = $this->getFieldValue($updatedData, $searchConfig->general->lang_field);


		$searchIndexInterface = $this->getSearchIndexInterface($lang);

		if($searchConfig->events->$funcName === 'add') {
			$this->addModuleSpecificDocument($searchIndexInterface, $searchConfig, $modName, $updatedData);
		} elseif($searchConfig->events->$funcName === 'remove') {

			$this->removeModuleSpecificDocument(
				$searchIndexInterface,
				$searchConfig->general->id_field,
				$this->getFieldValue($updatedData, $searchConfig->general->id_field)
			);
		} else {
			return;
		}

		$searchIndexInterface->commit();
		//$searchIndexInterface->optimize();

		//var_dump($searchConfig->events->$funcName, $updatedData, $funcName); exit;
	}

	private function addModuleSpecificDocument(SearchIndexInterface $searchIndexInterface, $modConfig, $modName, $updatedData) {
		$idValue = $this->getFieldValue($updatedData, $modConfig->general->id_field);

		$this->removeModuleSpecificDocument($searchIndexInterface, $modConfig->general->id_field, $idValue);

		$document = new Document();

		foreach($modConfig->document as $fldName => $type) {
			$fld = null;

			if($type === 'keyword') {
				$fld = Document\Field::keyword($fldName, $this->getFieldValue($updatedData, $fldName));
			} elseif($type === 'unstored') {
				$fld = Document\Field::keyword($fldName, $this->getFieldValue($updatedData, $fldName));
			} elseif($type === 'text') {
				$fld = Document\Field::text($fldName, $this->getFieldValue($updatedData, $fldName));
			} elseif($type === 'binary') {
				$fld = Document\Field::binary($fldName, $this->getFieldValue($updatedData, $fldName));
			}

			if($fld === null)
				continue;

			$document->addField(Document\Field::binary('type', $modName));
			$document->addField($fld);
		}

		$searchIndexInterface->addDocument($document);
	}

	private function removeModuleSpecificDocument(SearchIndexInterface $searchIndexInterface, $idField, $idValue) {
		$term = new Term($idValue, $idField);
		$docIds  = $searchIndexInterface->termDocs($term);

		foreach($docIds as $docID)
			$searchIndexInterface->delete($docID);
	}

	private function getFieldValue($obj, $prop) {
		$refProperty = new \ReflectionProperty($obj, $prop);

		if($refProperty->isProtected() || $refProperty->isPrivate())
			return call_user_func(array($obj, 'get' . ucfirst($prop)));

		return $obj->$prop;
	}

	private function getSearchIndexInterface($language) {
		return LuceneUtils::openOrCreate($this->cmsController->getCore()->getSiteRoot() . 'index' . DIRECTORY_SEPARATOR . $language);
	}
}

/* EOF */