<?php

namespace ch\metanet\cms\tablerenderer;
use ch\timesplinter\db\DB;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2015, METANET AG
 */
class CheckboxColumnFilter extends ColumnFilter
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
		$html = '<ul class="checkbox-filter">';
		
		foreach($this->values as $key => $value) {
			$checked = ($selection !== null && in_array($key, $selection)) ? ' checked' : null;
			$html .= '<li><label><input type="checkbox" value="' . $key . '" name="filter[' . $this->filterName . '][]"' . $checked . '> ' . $value . '</label></li>';
		}
		
		$html .= '</ul>';
			
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
		if($selection === null) return null;
		
		$not = ($this->compareOperator == '!=') ? ' NOT' : null;
		return $columnSelector . $not . ' IN(' . DB::createInQuery($selection) . ')';
	}
}

/* EOF */ 