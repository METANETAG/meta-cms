<?php

namespace ch\metanet\cms\module\mod_users\backend;

use ch\metanet\formHandler\field\OptionsField;
use ch\metanet\formHandler\renderer\OptionsFieldRenderer;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2015, METANET AG
 */
class RightsOptionsFieldRenderer extends OptionsFieldRenderer
{
	protected $options;

	public function __construct(array $options)
	{
		$this->options = $options;
	}

	/**
	 * @param OptionsField $field The field instance to render
	 *
	 * @return string The rendered field
	 */
	public function render(OptionsField $field)
	{
		$html = '<ul class="assignment-of-rights">';

		foreach($this->options as $modName => $rights) {
			$html .= '<li>
				<label><input type="checkbox" class="toggle-all"> ' . $modName . '</label>
				<ul>';

			foreach($rights as $key => $label)
			{
				$checked = (is_array($field->getValue()) && in_array($key, $field->getValue())) ? ' checked' : null;
				$html .= '<li><label><input type="checkbox" value="' . $key . '" name="' . $field->getName() . '[]"' . $checked . '> ' . $label . '</label>';
			}

			$html .= '</ul>
			</li>';
		}

		$html .= '</ul>';

		return $html;
	}
}

/* EOF */