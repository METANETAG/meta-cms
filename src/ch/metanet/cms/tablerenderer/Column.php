<?php

namespace ch\metanet\cms\tablerenderer;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class Column
{
	private $sqlColumn;
	private $label;
	private $sortable;
	private $sort;
	private $sortSelector;
	private $decorators;
	private $filterable;
	private $hidden;
	private $cssClasses;
	private $filter;

	/**
	 * Creates a column object which can be added to the @see TableRenderer
	 * @param string $sqlColumn The SQL column to select for this column
	 * @param string $label The label for this column
	 * @param array $decorators Set decorators for this column
	 * @param bool $sortable Defines if the column is sortable or not (true or false)
	 * @param null $sortSelector The SQL selector to use for the ORDER BY clause.
	 * By default it's the same as @see Column::$sqlColumn.
	 * @param string $sortDefault Should it sort first @see TableRenderer::SORT_ASC or @see TableRenderer::SORT_ASC.
	 * Default is to @see TableRenderer::SORT_ASC.
	 */
	public function __construct($sqlColumn, $label, $decorators = array(), $sortable = false, $sortSelector = null, $sortDefault = TableRenderer::SORT_ASC)
	{
		$this->sqlColumn = $sqlColumn;
		$this->label = $label;
		$this->sortable = $sortable;
		$this->sort = $sortDefault;
		$this->decorators = is_array($decorators) ? $decorators : array($decorators);
		$this->filterable = null;
		$this->sortSelector = ($sortSelector === null)?$sqlColumn:$sortSelector;
		$this->hidden = false;
		$this->cssClasses = array();
	}

	public function getSQLColumn()
	{
		return $this->sqlColumn;
	}

	public function getLabel()
	{
		return $this->label;
	}

	public function isSortable()
	{
		return $this->sortable;
	}

	public function getSort()
	{
		return $this->sort;
	}

	public function getDecorators()
	{
		return $this->decorators;
	}

	public function setDecorator(ColumnDecorator $decorator)
	{
		$this->decorators[] = $decorator;
	}

	public function setFilter($type = 'text', $highlight = true, array $keyValue = array())
	{
		$this->filterable = new \stdClass;
		$this->filterable->type = $type;
		$this->filterable->highlight = $highlight;
		$this->filterable->keyValue = $keyValue;
	}

	public function isFilterable()
	{
		return ($this->filterable !== null);
	}

	public function getFilterable()
	{
		return $this->filterable;
	}
	
	public function setColumnFilter(ColumnFilter $filter)
	{
		$this->filter = $filter;
	}

	/**
	 * @return ColumnFilter|null
	 */
	public function getColumnFilter()
	{
		return $this->filter;
	}

	public function getSortSelector()
	{
		return $this->sortSelector;
	}
	
	public function isHidden()
	{
		return $this->hidden;
	}
	
	public function setHidden($hidden)
	{
		$this->hidden = $hidden;
	}

	/**
	 * @param string $cssClass
	 */
	public function addCssClass($cssClass)
	{
		$this->cssClasses[] = $cssClass;
	}

	/**
	 * @return array
	 */
	public function getCssClasses()
	{
		return $this->cssClasses;
	}
}

/* EOF */