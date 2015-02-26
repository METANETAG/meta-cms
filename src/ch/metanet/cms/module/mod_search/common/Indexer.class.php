<?php


namespace ch\metanet\cms\module\mod_search\common;
use ch\timesplinter\common\JsonUtils;
use ch\timesplinter\core\Core;
use ch\timesplinter\core\FrameworkUtils;
use ch\timesplinter\db\DB;
use ZendSearch\Lucene\Analysis\Analyzer\Analyzer;
use ZendSearch\Lucene\Analysis\Analyzer\Common\Utf8\CaseInsensitive;
use ZendSearch\Lucene\Lucene;
use ZendSearch\Lucene\SearchIndexInterface;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class Indexer {
	private $db;
	private $indexes;
	private $indexesBasePath;
	private $core;

	/**
	 * @param DB $db
	 * @param Core $core
	 * @param string $indexesBasePath
	 */
	public function __construct(DB $db, Core $core, $indexesBasePath) {
		$this->db = $db;
		$this->core = $core;
		$this->indexesBasePath = $indexesBasePath;
		$this->indexes = array();

		Analyzer::setDefault(new CaseInsensitive());
	}

	public function start($indexSettings) {
		set_time_limit(0); // We need a lot of time!

		echo "Truncate the search_data table...\n";

		$this->db->exec("TRUNCATE mod_search_data");

		echo "Start indexing (this will take a long time, thats why we made this damn cronjob)...\n";

		foreach($indexSettings->sections as $section => $plugin) {
			$className = FrameworkUtils::stringToClassName($plugin, false);

			/** @var \ch\metanet\cms\module\mod_search\common\SearchPlugin $pluginInstance */
			$pluginInstance = new $className->className($this);

			echo "...indexing section: " . $section . "\n";
			$pluginInstance->index();
		}

		echo "\n";
	}

	/**
	 * @param string $indexName The name of the index
	 * @return SearchIndexInterface
	 */
	public function getIndex($indexName) {
		if(isset($this->indexes[$indexName]) === false) {
			$indexPath = $this->indexesBasePath . $indexName;

			//if(is_dir($indexPath)) rmdir($indexPath);

			echo "+++Allocated index: " . $indexName . "\n";

			$this->indexes[$indexName] = Lucene::create($indexPath);
		}

		return $this->indexes[$indexName];
	}

	public function __destruct() {
		foreach($this->indexes as $key => $index) {
			/** @var SearchIndexInterface $index */
			$index->commit();
			$index->optimize();

			echo "Index: \"" . $key . "\" closed and optimized\n";

			unset($index);
		}
	}

	public function getDB() {
		return $this->db;
	}

	/**
	 * @return \ch\timesplinter\core\Core
	 */
	public function getCore() {
		return $this->core;
	}
}

/* EOF */