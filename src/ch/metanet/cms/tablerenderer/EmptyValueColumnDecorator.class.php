<?php

namespace ch\metanet\cms\tablerenderer;

use stdClass;

/**
 * @author Pascal Muenst <dev@timesplinter.ch>
 * @copyright Copyright (c) 2014, TiMESPLiNTER Webdevelopment
 * @version 1.0.0
 */
class EmptyValueColumnDecorator extends ColumnDecorator {
	protected $nullValueReplacement;

	public function __construct($nullValueReplacement) {
		$this->nullValueReplacement = $nullValueReplacement;
	}

	public function modify($value, stdClass $record, $selector, TableRenderer $tableRenderer) {
		if($value === null || strlen($value) === 0)
			return $this->nullValueReplacement;

		return $value;
	}
}

/* EOF */