<?php

namespace ch\metanet\cms\common;

use ch\metanet\cms\tablerenderer\TableRenderer;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class CmsCallbackColumnDecorators
{
	public static function getRightsAsString($value, \stdClass $record, $selector, TableRenderer $tableRenderer)
	{
		return CmsUtils::getRightsAsString($value);
	}
}

/* EOF */