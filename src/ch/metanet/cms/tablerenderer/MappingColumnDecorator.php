<?php

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */

namespace ch\metanet\cms\tablerenderer;


class MappingColumnDecorator extends ColumnDecorator {
	private $mapTable;

	public function __construct($mapTable) {
		$this->mapTable = $mapTable;
	}

	public function modify($value, \stdClass $record, $selector, TableRenderer $tableRenderer) {
		if($value === null)
			return null;

		return isset($this->mapTable[$value])?$this->mapTable[$value]:$value;
	}
}

/* EOF */