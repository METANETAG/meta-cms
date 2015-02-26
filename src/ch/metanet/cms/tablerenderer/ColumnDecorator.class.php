<?php

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */

namespace ch\metanet\cms\tablerenderer;

use \stdClass;

abstract class ColumnDecorator {
	abstract public function modify($value, stdClass $record, $selector, TableRenderer $tableRenderer);

	protected function replaceColumnValues($str, stdClass $record) {
		$repl = array();

		foreach($record as $k => $v) {
			$repl['{' . $k . '}'] = $v;
		}

		return str_replace(array_keys($repl), $repl, $str);
	}
}

/* EOF */