<?php

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */

namespace ch\metanet\cms\common;

use ch\metanet\cms\tablerenderer\TableRenderer;

class CmsCallbackColumnDecorators {
	public static function getRightsAsString($value, \stdClass $record, $selector, TableRenderer $tableRenderer) {
		return CmsUtils::getRightsAsString($value);
	}
}

/* EOF */