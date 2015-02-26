<?php


namespace ch\metanet\cms\module\mod_core\search;
use ch\metanet\cms\common\CmsSearchPlugin;
use ch\metanet\cms\common\CmsSearchResult;
use ch\metanet\cms\common\CmsSearchResultSet;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class PageSearchPlugin extends CmsSearchPlugin {

	public function doSearch($keywords) {
		/*$stmntPageTitle = $this->db->prepare("");
		$resPageTitle = $this->db->select($stmntPageTitle, array('%' . $keywords . '%', '%' . $keywords . '%'));

		$results = array();

		foreach($resPageTitle as $r) {
			$sr = new CmsSearchResult($r->title, 'http://www.google.com/', $r->language_codeFK);
			$sr->setSummary($r->description);

			$results[] = $sr;
		}*/

		$results = new CmsSearchResultSet('Seiten');

		$stmntPageContent = $this->db->prepare("
			SELECT title, description, language_codeFK, 100 relevance, r.pattern
			FROM page p
			JOIN route r ON r.page_IDFK = p.ID
			WHERE title LIKE ? OR description LIKE ?
			UNION
			SELECT p.title, p.description, p.language_codeFK, COUNT(*) relevance, r.pattern
			FROM element_search_index esi
			LEFT JOIN page p ON p.ID = esi.page_IDFK
			JOIN route r ON r.page_IDFK = p.ID
			WHERE esi.searchable_content LIKE ?
			GROUP BY esi.page_IDFK
			ORDER BY relevance DESC
		");
		$resPageContent = $this->db->select($stmntPageContent, array('%' . $keywords . '%', '%' . $keywords . '%', '%' . $keywords . '%'));

		foreach($resPageContent as $r) {
			$sr = new CmsSearchResult($r->title, $r->pattern, $r->language_codeFK);
			$sr->setSummary($r->description);
			$sr->setRelevance($r->relevance);

			$results->push($sr);
		}

		return $results;
	}
}

/* EOF */