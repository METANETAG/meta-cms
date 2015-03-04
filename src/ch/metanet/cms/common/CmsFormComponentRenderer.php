<?php

namespace ch\metanet\cms\common;

use ch\metanet\formHandler\field\Field;
use ch\metanet\formHandler\renderer\FieldComponentRenderer;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2015, METANET AG
 */
class CmsFormComponentRenderer extends FieldComponentRenderer
{
	public function render(Field $field, $renderedField)
	{
		$errorHtmlBefore = null;
		$errorHtmlAfter = null;

		if($field->hasErrors() === true) {
			$errorHtmlBefore = '<div class="input-error"><img class="input-error-mark" src="/images/icon-input-error.png" alt="!">';

			$errorHtmlAfter = '<ul class="input-error-list">';

			foreach($field->getErrors() as $error) {
				$errorHtmlAfter .= '<li>' . $error . '</li>';
			}

			$errorHtmlAfter .= '</ul></div>';
		}

		return $errorHtmlBefore . $renderedField . $errorHtmlAfter;
	}
}

/* EOF */