<?php

namespace ch\metanet\cms\common;

class Utils {
	public static function getErrorsAsList($errors) {
		if($errors === null || count($errors) <= 0)
			return null;

		$errorList = '<ul class="msg-error">';

		foreach($errors as $e) {
			$errorList .= '<li>' . $e . '</li>';
		}

		$errorList .= '</ul>';

		return $errorList;
	}
}