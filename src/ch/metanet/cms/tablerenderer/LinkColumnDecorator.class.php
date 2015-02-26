<?php

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */

namespace ch\metanet\cms\tablerenderer;


class LinkColumnDecorator extends ColumnDecorator {
	private $title;
	private $class;

	public function __construct($title = null, $class = null) {
		$this->title = $title;
		$this->class = ($class !== null)?' class="' . $class . '"':null;
	}

	public function modify($value, \stdClass $record, $selector, TableRenderer $tableRenderer) {
		if($value === null)
			return null;

		$attrTitle = ($this->title !== null)?' title="' . $this->replaceColumnValues($this->title, $record) . '"':null;

		return '<a href="' . $value . '"' . $this->class . $attrTitle . '>' . $value . '</a>';
	}
}

/* EOF */