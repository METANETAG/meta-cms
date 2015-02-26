<?php

namespace ch\metanet\cms\tablerenderer;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class RewriteColumnDecorator extends ColumnDecorator
{
	protected $newStr;

	public function __construct($newStr)
	{
		$this->newStr = $newStr;
	}

	public function modify($value, \stdClass $record, $selector, TableRenderer $tableRenderer)
	{
		if($value === null)
			return null;

		return $this->replaceColumnValues($this->newStr, $record);
	}
}

/* EOF */