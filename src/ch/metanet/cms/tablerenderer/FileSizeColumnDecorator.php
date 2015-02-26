<?php

namespace ch\metanet\cms\tablerenderer;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class FileSizeColumnDecorator extends ColumnDecorator {
	public function __construct() {
	}

	public function modify($value, \stdClass $record, $selector, TableRenderer $tableRenderer) {
		if($value === null)
			return null;

		return $this->humanFilesize($value);
	}

	private function humanFilesize($bytes, $decimals = 2) {
		$size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . (isset($size[$factor])?$size[$factor]:null);
	}
}

/* EOF */