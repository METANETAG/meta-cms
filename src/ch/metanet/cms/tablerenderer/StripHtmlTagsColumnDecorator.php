<?php

namespace ch\metanet\cms\tablerenderer;

use stdClass;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2015, METANET AG
 */
class StripHtmlTagsColumnDecorator extends ColumnDecorator
{
	protected $convertHtmlTags;
	
	public function __construct($convertHtmlTags = false)
	{
		$this->convertHtmlTags = $convertHtmlTags;
	}
	
	public function modify($value, stdClass $record, $selector, TableRenderer $tableRenderer)
	{
		return ($value === null) ? null : ($this->convertHtmlTags ? htmlspecialchars($value) : strip_tags($value));
	}
}

/* EOF */