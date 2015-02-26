<?php

namespace ch\metanet\cms\searchplugins;

use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsElementSearchable;
use ch\metanet\cms\model\ElementModel;
use ch\metanet\cms\model\PageModel;
use ch\metanet\cms\module\layout\LayoutElement;
use ch\metanet\cms\module\mod_search\common\SearchPlugin;
use ch\metanet\cms\module\mod_search\model\Document;
use ch\metanet\cms\module\mod_search\model\SearchModel;
use ZendSearch\Lucene\Document\Field;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class PageSearchPlugin extends SearchPlugin
{
	public function index()
	{
		$oldReqUri = $_SERVER['REQUEST_URI'];

		$_SERVER['REQUEST_URI'] = '';
		
		$pageModel = new PageModel($this->indexer->getDB());
		$elementModel = new ElementModel($this->indexer->getDB());
		$searchModel = new SearchModel($this->indexer->getDB());

		$stmntPages = $this->indexer->getDB()->prepare("
			SELECT p.ID, p.language_codeFK lang, p.title, p.description, r.pattern, p.role
			FROM page p
			LEFT JOIN route r ON r.page_IDFK = p.ID
			WHERE r.ID IS NOT NULL
		");

		$resPages = $this->indexer->getDB()->select($stmntPages);
		$indexedPages = 0;

		foreach($resPages as $p) {
			if($p->role !== 'page') {
				echo "  Skipped page #" . $p->ID . ": reason -> unusable role: " . $p->role . PHP_EOL;
				continue;
			}

			$searchIndexInterface = $this->indexer->getIndex($p->lang);

			// Index page
			echo "  Indexing page #" . $p->ID . " into index \"" . $p->lang . "\": ";

			$cmsPage = $pageModel->getPageByID($p->ID);
			$elementTree = $elementModel->getElementTree($cmsPage);

			try {
				$searchableContent = $this->renderElementTreeRecursive($elementTree, $cmsPage->getLanguage());
			} catch(\Exception $e) {
				echo " Error -> " . $e->getMessage() . "\n";

				continue;
			}

			$searchDoc = new Document();
			$searchDoc->setInternalID($p->ID);
			$searchDoc->setLanguage($p->lang);
			$searchDoc->setTitle($p->title);
			$searchDoc->setDescription($searchableContent);
			$searchDoc->setPath($p->pattern);
			$searchDoc->setType('core_page');

			$docID = $searchModel->saveDocument($searchDoc);

			$luceneDocument = new \ZendSearch\Lucene\Document();
			$luceneDocument->addField(Field::keyword('ID', $docID));
			$luceneDocument->addField(Field::unStored('content', $searchableContent));
			$luceneDocument->addField(Field::unStored('description', $p->description));

			$searchIndexInterface->addDocument($luceneDocument);

			echo "done";

			echo "\n";

			++$indexedPages;
		}

		$_SERVER['REQUEST_URI'] = $oldReqUri;

		echo "  Total indexed pages: " . $indexedPages . "\n";
	}

	public function renderResults() {
		// TODO: Implement renderResults() method.
	}

	private function renderElementTreeRecursive($elementTree, $language) {
		$pageSearchContent = '';

		foreach($elementTree as $e) {
			/** @var CmsElement $e */
			//$this->logger->debug('render search index element: ' . $e->getIdentifier() . ' (#' . $e->getID() . ')');

			/** @var CmsElement|CmsElementSearchable $e */
			if($e instanceof CmsElementSearchable/* && $e->hasSettings()*/)
				$pageSearchContent .= str_replace("\n", ' ', $e->renderSearchIndexContent($this->indexer->getDB(), $language)) . ' | ';

			if($e instanceof LayoutElement) {
				/** @var LayoutElement $e */
				$pageSearchContent .= $this->renderElementTreeRecursive($e->getElements(), $language);
			}
		}

		return $pageSearchContent;
	}
}

/* EOF */