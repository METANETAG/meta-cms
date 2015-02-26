<?php

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */

namespace ch\metanet\cms\tablerenderer;

use ch\metanet\cms\tablerenderer\ColumnDecorator;
use stdClass;

class SortColumnDecorator extends ColumnDecorator {

	public function modify($value, stdClass $record, $selector, TableRenderer $tableRenderer) {
		return '<a href="?up=' . $value . '">up</a> <a href="?down=' . $value . '">down</a>';
	}
}

/* EOF */