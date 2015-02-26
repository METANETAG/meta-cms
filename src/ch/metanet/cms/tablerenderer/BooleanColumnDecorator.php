<?php

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */

namespace ch\metanet\cms\tablerenderer;


class BooleanColumnDecorator extends ColumnDecorator {
	private $true;
	private $false;

	public function __construct($true = 'yes', $false = 'no') {
		$this->true = $true;
		$this->false = $false;
	}

	public function modify($value, \stdClass $record, $selector, TableRenderer $tableRenderer) {
		if($value === null)
			return null;

		return ($value == 1)?$this->true:$this->false;
	}
}

/* EOF */