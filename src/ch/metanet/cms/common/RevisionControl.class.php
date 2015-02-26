<?php


namespace ch\metanet\cms\common;
use ch\timesplinter\common\SimpleXMLUtils;
use ch\timesplinter\db\DB;
use sqlparser\PHPSQLParser;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class RevisionControl {
	private $db;
	private $revisionPath;

	public function __construct(DB $db) {
		$this->db = $db;
		$this->revisionPath = SITE_ROOT . 'revision' . DIRECTORY_SEPARATOR;
	}

	public function restoreFromFile($filePath) {
		$xmlContent = simplexml_load_file($this->revisionPath . $filePath);

		foreach($xmlContent->table as $t)
			$this->createQueryFromTable($t);
	}

	private function createQueryFromTable(\SimpleXMLElement $table) {
		$tableAttrs = $table->attributes();


		foreach($table->records as $r) {
			$cols = array();
			$vals = array();

			foreach($r->record->fields->field as $f) {
				$fAttrs = $f->attributes();

				$cols[] = (string)$fAttrs['name'] . ' = ?';
				$vals[] = (string)$f;
			}

			$sqlQuery =  "INSERT INTO " . (string)$tableAttrs['name'] . " SET " . implode(', ', $cols) . "\nON DUPLICATE KEY UPDATE " . implode(', ', $cols);

			$stmnt = $this->db->prepare($sqlQuery);
			$this->db->update($stmnt, array_merge($vals, $vals) );
			//var_dump($vals);
		}
	}
}

/* EOF */