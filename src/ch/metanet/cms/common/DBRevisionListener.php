<?php

namespace ch\metanet\cms\common;

use ch\timesplinter\common\StringUtils;
use ch\timesplinter\core\FrameworkLoggerFactory;
use ch\timesplinter\db\DBListener;
use ch\timesplinter\db\DB;
use ch\timesplinter\db\DBException;
use \PDOStatement;
use ch\metanet\cms\common\CMSException;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class DBRevisionListener extends DBListener {
	private $savePath;
	private $logger;
	private $cachedXML;
	private $transactionName;

	public function __construct($savePath) {
		$this->logger = FrameworkLoggerFactory::getLogger($this);

		$this->savePath = $savePath;
		$this->cachedXML = null;
		$this->transactionName = null;
	}

	public function beforeBeginTransaction(DB $db) {
		$this->cachedXML = null;
	}

	public function afterCommit(DB $db) {
		$this->saveOldRevision($this->cachedXML, $db->getTransactionName());
	}

	public function afterMutation(DB $db, PDOStatement $stmnt, array $params, $queryType) {
		$cond = null;

		if(preg_match('/^\s*(SELECT|UPDATE|DELETE\s+FROM|INSERT\s+INTO|INSERT\s+IGNORE|REPLACE\s+INTO)\s+(.+?)\s+(?:SET\s+(.+))?(?:ON DUPLICATE KEY UPDATE|WHERE\s+(.+))?$/ims', $stmnt->queryString, $mT) === false)
			throw new CMSException('Revision control could not parse sql statement: ' . $stmnt->queryString);

		$sqlFunction = $mT[1];
		$sqlTable = trim($mT[2]);
		$sqlColumns = isset($mT[3])?$mT[3]:null;
		$sqlCond = isset($mT[4])?$mT[4]:null;

		if(in_array($sqlFunction, array('REPLACE INTO', 'INSERT INTO', 'UPDATE', 'INSERT IGNORE'))) {
			$resPK = $this->getPKColumnsForTable($db, $sqlTable);
			$pkArray = array();

			foreach($resPK as $pk) {
				$pkArray[] = $pk . ' = ?';
			}

			// params
			$cols = explode(',', $sqlColumns);

			$colsArr = array();
			$pkArr = array();

			$i = 0;
			foreach($cols as $c) {
				$colClean = trim(preg_replace('/=\s*\?/', null, $c)) . ' = ?';
				$colsArr[] = $colClean;

				if(in_array($colClean, $pkArray))
					$pkArr[] = $params[$i];

				++$i;
			}

			$joinedArr = array_intersect($colsArr, $pkArray);

			if(count($joinedArr) <= 0)
				return;

			$cond = implode(' AND ', $joinedArr);
		} elseif(in_array($sqlFunction, array('SELECT'))) {
			// ignore
			return;
		} else {
			//var_dump($mT); exit;
			$offsetParams = substr_count(StringUtils::beforeLast($stmnt->queryString, 'WHERE'), '?');
			$anzParams = substr_count($sqlCond, '?');
			$pkArr = array_slice($params, $offsetParams, $anzParams);
			$cond = trim($sqlCond);
		}

		$revSql = "SELECT * FROM " . $sqlTable . " WHERE " .  $cond;


		try {
			$stmntRev = $db->prepare($revSql);

			$resRev = $db->select($stmntRev, $pkArr);

			if(count($resRev) <= 0)
				return;

			$xmlStr  = "\t" . '<table name="' . $sqlTable . '" exectime="' . date('Y-m-d H:i:s') . '">' . "\n";
			$xmlStr .= "\t\t" . '<records>' . "\n";

			foreach($resRev as $r) {
				$xmlStr .= "\t\t\t" . '<record>' . "\n";
				$xmlStr .= "\t\t\t\t" . '<fields>' . "\n";

				foreach($r as $col => $val) {
					$xmlStr .= "\t\t\t\t\t" . '<field name="' . $col. '"><![CDATA[' . $val . ']]></field>' . "\n";
				}

				$xmlStr .= "\t\t\t\t" . '</fields>' . "\n";
				$xmlStr .= "\t\t\t" . '</record>' . "\n";
			}

			$xmlStr .= "\t\t" . '</records>' . "\n";
			$xmlStr .= "\t" . '</table>' . "\n";

			if($db->inTransaction() === true) {
				$this->cachedXML .= $xmlStr;
				return;
			}

			$this->saveOldRevision($xmlStr, $sqlTable . '.' . implode('-', $pkArr));
		} catch(DBException $e) {
			$this->logger->error('Problem with generated statement: ' . $e->getQueryString(), $e);

			throw $e;
		}
	}

	private function getPKColumnsForTable(DB $db, $tableName) {
		$stmntPK = $db->prepare("
				SELECT k.COLUMN_NAME
				FROM information_schema.table_constraints t
				LEFT JOIN information_schema.key_column_usage k
				USING(constraint_name, table_schema, table_name)
				WHERE t.constraint_type = 'PRIMARY KEY'
				AND t.table_schema = DATABASE()
				AND t.table_name= ?
			");

		$resPK = $db->select($stmntPK, array($tableName));
		$pkArray = array();

		foreach($resPK as $pk)
			$pkArray[] = $pk->COLUMN_NAME;

		return $pkArray;
	}

	private function saveOldRevision($xmlStr, $fileName) {
		if($xmlStr === null || strlen($xmlStr) <= 0)
			return;

		$saveXml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<data>' . "\n" . $xmlStr . '</data>';

		$fileName = $fileName . '.xml';

		$transactionNameParts = explode('.', $fileName);
		$dirName = array_shift($transactionNameParts);
		$revDir = $this->savePath . $dirName . DIRECTORY_SEPARATOR;

		if(file_exists($revDir) === false) {
			mkdir($revDir);
		}

		if(file_put_contents($revDir . $fileName, $saveXml) === false) {
			throw new CMSException('Could not write revision file: ' . $revDir . $fileName);
		}
	}
}

/* EOF */