<?php

namespace ch\metanet\cms\module\mod_search\common;

use ch\metanet\cms\controller\common\FrontendController;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
abstract class SearchPlugin
{
	/** @var Indexer The pending indexer */
	protected $indexer;

	public function __construct(Indexer $indexer)
	{
		$this->indexer = $indexer;
	}

	/**
	 * Adds or updates the documents in the search index
	 * 
	 * @see Indexer::getIndex
	 */
	public abstract function index();

	/**
	 * Returns all search result entries as a HTML string
	 *
	 * @param \stdClass[] $results
	 * @param FrontendController $fec
	 *
	 * @return string
	 */
	public abstract function renderResults(array $results, FrontendController $fec);
}