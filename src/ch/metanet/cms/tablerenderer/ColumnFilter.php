<?php

namespace ch\metanet\cms\tablerenderer;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2015, METANET AG
 */
abstract class ColumnFilter
{
	protected $filterName;
	protected $compareOperator;

	/**
	 * @param string $filterName
	 */
	public function __construct($filterName)
	{
		$this->filterName = $filterName;
		$this->compareOperator = '=';
	}
	
	/**
	 * @param mixed $selection
	 * 
	 * @return string
	 */
	public abstract function renderHtml($selection);

	/**
	 * @param string $columnSelector
	 * @param mixed $selection
	 *
	 * @return mixed
	 */
	public abstract function renderSql($columnSelector, $selection);
	
	/**
	 * @return string
	 */
	public function getFilterName()
	{
		return $this->filterName;
	}
}

/* EOF */