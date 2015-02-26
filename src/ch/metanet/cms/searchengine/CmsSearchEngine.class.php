<?php


namespace ch\metanet\cms\searchengine;

use ch\metanet\cms\controller\common\CmsController;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
abstract class CmsSearchEngine {
	private $cmsController;

	private $transaction;

	public function __construct(CmsController $cmsController) {
		$this->cmsController = $cmsController;
		$this->transaction = false;
	}

	public function storeDocument(CmsSearchDocument $cmsSearchDocument) {
		if($this->transaction === false)
			$this->commit();
	}

	public function beginTransaction() {
		$this->transaction = true;
	}

	public function commit() {
		$this->transaction = false;
	}
}

/* EOF */