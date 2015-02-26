<?php

namespace ch\metanet\cms\tablerenderer;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2015, METANET AG
 */
class SelectBoxColumnFilter extends ColumnFilter
{
	protected $values;
	
	public function __construct($filterName, array $values)
	{
		parent::__construct($filterName);
		
		$this->values = $values;
	}

	/**
	 * @param mixed $selection
	 *
	 * @return string
	 */
	public function renderHtml($selection)
	{
		$html = '<select name="filter[' . $this->filterName . ']" class="select-box-filter">' . $this->renderOptions($this->values, $selection) . '</select>';
		
		return $html;
	}
	
	protected function renderOptions($options, $selection)
	{
		$html = '';
		
		foreach($options as $key => $value) {
			if(is_array($value) === true) {
				$html .= '<optgroup label="' . $key . '">' . $this->renderOptions($value, $selection) . '</optgroup>';
			} else {
				$selected = ($selection !== null && $selection == $key) ? ' selected' : null;
				$html .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
			}
		}
		
		return $html;
	}

	/**
	 * @param string $columnSelector
	 * @param mixed $selection
	 *
	 * @return mixed
	 */
	public function renderSql($columnSelector, $selection)
	{
		if($selection === null || strlen($selection) === 0) return null;
		
		return $columnSelector . ' ' . $this->compareOperator . ' ?';
	}
}

/* EOF */