<?php


namespace ch\metanet\cms\module\mod_search\common;
use ZendSearch\Lucene\Index;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
abstract class SearchPlugin
{
	protected $indexer;

	public function __construct(Indexer $indexer)
	{
		$this->indexer = $indexer;
	}

	public abstract function index();
	public abstract function renderResults();
}

/* EOF */