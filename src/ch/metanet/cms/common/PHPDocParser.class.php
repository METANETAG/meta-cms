<?php

namespace ch\metanet\cms\common;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class PHPDocParser
{
	public static function parse($docStr)
	{
		$data = new \stdClass();
		$data->comment = null;

		if(preg_match('/\/\*\*(.+?)@/ims', $docStr, $resComment)) {
			$strComment = trim(preg_replace('/\n\s\*\s*/', "\r\n", $resComment[1]));
			$data->comment = $strComment;
		}

		preg_match_all('/@(.+?)\s+(.+?)$/ims', $docStr, $resAttrs, PREG_SET_ORDER);

		foreach($resAttrs as $attr) {
			$key = $attr[1];
			$value = $attr[2];

			if($key == 'author') {
				if(preg_match('/(.+?)\s+<(.+?)>/', $value, $resAuthor)) {
					$data->author = $resAuthor[1];
					$data->author_email = $resAuthor[2];
				} else {
					$data->$key = $value;
				}
			} else {
				/*if(property_exists($data, $attr[1])) {
					if(is_array($data->$attr[1])) {
						$data->$key[] = $value;
					} else {
						$data->$key = array($data->$key, $value);
					}
				} else {*/
					$data->$key = $value;
				//}
			}
		}

		return $data;
	}
}

/* EOF */