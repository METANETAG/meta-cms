<?php

namespace ch\metanet\cms\tablerenderer;

use ch\timesplinter\common\StringUtils;
use ch\timesplinter\db\DB;
use timesplinter\tsfw\i18n\common\AbstractTranslator;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class TableRenderer
{
	const SORT_DESC = 'DESC';
	const SORT_ASC = 'ASC';

	const OPT_EDIT = 'edit';
	const OPT_DELETE = 'delete';
	const OPT_REMOVE = 'remove';
	const OPT_SHOW = 'show';

	protected $sqlQuery;
	protected $stmnt;
	protected $stmntLimited;
	/** @var Column[] */
	protected $columns;
	protected $options;
	protected $db;
	protected $cssClass;
	protected $displayInfo;
	protected $tableName;
	protected $orderBy;
	protected $selectable;
	protected $sortable;
	protected $pageLimit;
	protected $currentPage;

	protected $filtersApplied;
	protected $keywords;
	protected $keywordStr;
	protected $reorder;
	
	/** @var AbstractTranslator */
	protected $translator;

	/**
	 * @param string $tableName
	 * @param DB $db
	 * @param string $sqlQuery The SQL query to get the data for the table
	 * @param string $cssClass The CSS class the HTML table should have
	 * @param bool $displayInfo Displays information of the entries found (on top of the table)
	 * @param int $pageLimit The limit of entries per page
	 */
	public function __construct($tableName, DB $db, $sqlQuery, $cssClass = 'table-data', $displayInfo = true, $pageLimit = 25)
	{
		$this->selectable = false;
		$this->tableName = $tableName;
		$this->orderBy = null;
		$this->pageLimit = $pageLimit;
		$this->currentPage = 1;
		$this->keywords = null;
		$this->filtersApplied = false;

		$this->entityWord = 'entry';
		$this->entityWordPlural = 'entries';

		$this->sqlQuery = StringUtils::beforeLast($sqlQuery, 'ORDER BY');

		if(($orderByStr = StringUtils::afterLast($sqlQuery, 'ORDER BY')) !== null) {
			$res = preg_split('/\s+/ims', $orderByStr, -1, PREG_SPLIT_NO_EMPTY);
			
			$this->orderBy = array(
				'column' => $res[0],
				'sort' => isset($res[1]) ? $res[1] : 'ASC'
			);
		}
		
		$this->columns = null;
		$this->options = null;
		$this->db = $db;
		$this->cssClass = $cssClass;
		$this->displayInfo = $displayInfo;
		$this->sortable = null;
	}

	/**
	 * @param array $sqlParams The params for the SQL query of the renderer
	 *
	 * @return string The rendered HTML table
	 * 
	 * @throws \Exception
	 */
	public function display(array $sqlParams = array())
	{
		$this->appendTableActions();
		
		$textSearch = array();
		$filterForm = false;

		foreach($this->columns as $c) {
			/** @var Column $c */
			if(!$c->isFilterable())
				continue;

			$filterForm = true;

			if($this->filtersApplied === false)
				break;

			if($c->getFilterable()->type === 'text') {
				$keywordGroupQueryStr = null;
				
				foreach($this->keywords as $k) {
					$compareWord = 'LIKE';
					$prefix = ' OR ';

					if(StringUtils::startsWith($k, '+')) {
						$k = substr($k, 1);
						$compareWord = 'LIKE';
						$prefix = ' AND ';
					} elseif(StringUtils::startsWith($k, '-')) {
						$k = substr($k, 1);
						$compareWord = 'NOT LIKE';
						$prefix = ' AND ';
					}

					$keywordGroupQueryStr .= (($keywordGroupQueryStr !== null)?$prefix:null) . $c->getSQLColumn() . " " . $compareWord . " ?";
					$sqlParams[] = '%' . $k . '%';
				}

				if($keywordGroupQueryStr === null)
					continue;
				
				$textSearch[] = '(' . $keywordGroupQueryStr . ')';
			}
		}
		
		$hasColumnFilter = false;
		$columnFilterHtml = '<tr class="column-filters">';
		$columnFilterSql = array();
		
		foreach($this->columns as $c) {
			if($c->getColumnFilter() === null) {
				$columnFilterHtml .= '<th class="no-filter">&nbsp;</th>';
				continue;
			}
			
			$selection = isset($_SESSION['table'][$this->tableName]['filter'][$c->getColumnFilter()->getFilterName()]) ? $_SESSION['table'][$this->tableName]['filter'][$c->getColumnFilter()->getFilterName()] : null;
			
			$hasColumnFilter = true;
			$columnFilterHtml .= '<th>' . $c->getColumnFilter()->renderHtml($selection) . '</th>';
			
			if(($filterSql = $c->getColumnFilter()->renderSql($c->getSortSelector() !== null ? $c->getSortSelector() : $c->getSQLColumn(), $selection)) !== null) {
				$columnFilterSql[] = $filterSql;
				$sqlParams = array_merge($sqlParams, (array)$selection);
			}
		}

		if($this->options !== null) {
			$columnFilterHtml .= "\t\t<th class=\"no-filter\">&nbsp;</th>\n";
		}
		
		$columnFilterHtml .= '</tr>';

		$searchHtml = null;
		$filterInfo = null;
		
		if($filterForm === true)
			$searchHtml = '<div class="table-data-search"><input type="hidden" name="table" value="' . $this->tableName . '"><input type="text" name="filter[keywords]" value="' . str_replace('"', '&quot;', $this->keywordStr) . '" placeholder="' . $this->getText('Keywords') . '"><button type="submit">' . $this->getText('Go') . '</button></div>';

		$whereConds = array();
		
		if(count($textSearch) > 0) {
			$whereConds[] = '(' . implode(' OR ', $textSearch) . ')';

			$keywordsHtml = array();

			foreach($this->keywords as $k)
				$keywordsHtml[] = '<span class="' . $this->cssClass .  '-keyword">' . $k . '</span>';

			$filterInfo = ' (' . $this->getText('Result is filtered by keywords') . ': ' . implode(null, $keywordsHtml);
		}

		if($this->filtersApplied)
			$filterInfo .= ' (<a href="?table=' . $this->tableName . '&amp;resetfilters">' . $this->getText('reset filters') . '</a>)';
		
		// filter
		foreach($columnFilterSql as $filterSql) {
			$whereConds[] = '(' . $filterSql . ')';
		}
		
		if(count($whereConds) > 0)
			$this->sqlQuery .= ' WHERE ' . implode(' AND ', $whereConds);
		
		if($this->sortable !== null) {
			$this->sqlQuery .= ' ORDER BY ' . $this->sortable['sort'];
		} elseif($this->orderBy !== null && isset($this->columns[$this->orderBy['column']]) === true) {
			$this->sqlQuery .= ' ORDER BY ' . $this->columns[$this->orderBy['column']]->getSortSelector() . ' ' . $this->orderBy['sort'];
		}

		if(preg_match('/[\s\)]+FROM(?![^\(]*\)).*/ims', $this->sqlQuery, $fromMatches) === 0)
			throw new \Exception('No FROM found in query: ' . $this->sqlQuery);
		
		$this->stmnt = $this->db->prepare("SELECT COUNT(*) total_records " . $fromMatches[0]);
		$this->stmntLimited = $this->db->prepare($this->sqlQuery . " LIMIT ?,?");
		
		$res = $this->db->select($this->stmnt, $sqlParams);
		$entriesCount = $res[0]->total_records;

		$resStart = ($this->currentPage-1)*$this->pageLimit;

		if($resStart > $entriesCount)
			$resStart = 0;

		$sqlParams[] = $resStart;
		$sqlParams[] = $this->pageLimit;

		$resLimited = $this->db->select($this->stmntLimited, $sqlParams);



		$tableHtml = '<div id="' . $this->tableName . '"><form method="post" action="?table=' . $this->tableName . '#' . $this->tableName . '" class="' . $this->cssClass .  '-filters">';

		/*if($entriesCount <= 0)
			return $tableHtml . $searchHtml . '<p class="' . $this->cssClass . '-info">' . $this->getText('No entries found.') . $filterInfo . '</p>';*/

		if($this->columns === null)
			$this->columns = $this->getColumnsByQuery($res);
		
		$tableHtml .= $searchHtml;

		// PAGINATION
		$numPages = ceil($entriesCount / $this->pageLimit);

		if($numPages > 1) {
			$tableHtml .= '<ul class="' . $this->cssClass . '-pagination">';

			for($i = 1; $i <= $numPages; ++$i) {
				$active = ($i == $this->currentPage)?' class="active"':null;
				$tableHtml .= '<li><a href="?table=' . $this->tableName . '&amp;page=' . $i . '#' . $this->tableName . '"' . $active . '>' . $i . '</a></li>';
			}

			$tableHtml .= '</ul>';
		}

		if($this->displayInfo === true) {
				$tableHtml .= "<p class=\"" . $this->cssClass . "-info\">" . sprintf($this->getText('There is <b>%d</b> entry.', 'There are <b>%d</b> entries.', $entriesCount), $entriesCount) . $filterInfo . "</p>\n";
		}

		$tableHtml .= "<table class=\"" . $this->cssClass . "\">\n\t<thead>\n\t<tr>\n";

		if($this->selectable === true)
			$tableHtml .= "\t\t<th class=\"header-selectable\">&nbsp;</th>\n";
		
		if($this->reorder === true)
			$tableHtml .= "\t\t<th class=\"header-reorder\">&nbsp;</th>\n";

		foreach($this->columns as $col) {
			/** @var Column $col */
			if($col->isHidden() === true)
				continue;
			
			if($this->sortable === null && $col->isSortable()) {
				$sortStr = ($this->orderBy !== null && $this->orderBy['column'] == $col->getSQLColumn() && $this->orderBy['sort'] == 'ASC') ? 'DESC' : 'ASC';

				$sortClass = null;
				$sortFontIcon = '<i class="fa fa-sort"></i>';
				
				if($this->orderBy !== null && $this->orderBy['column'] == $col->getSQLColumn()) {
					$sortFontIcon = '<i class="fa ' . ($this->orderBy['sort'] === 'ASC' ? 'fa-caret-up' : 'fa-caret-down') . '"></i>';
					$sortClass = ' class="active-' . strtolower($this->orderBy['sort']) . '"';
				}
				
				$label = '<a href="?table=' . $this->tableName . '&amp;sort=' . $col->getSQLColumn() . '-' . $sortStr . '#' . $this->tableName . '"' . $sortClass . '>' .  $col->getLabel() . $sortFontIcon . '</a>';
			} else {
				$label = $col->getLabel();
			}

			$tableHtml .= "\t\t<th>" . $label . "</th>\n";
		}

		if($this->options !== null) {
			$tableHtml .= "\t\t<th>&nbsp;</th>\n";
		}
		
		$tableHtml .= '</tr>';
		
		if($hasColumnFilter) 
			$tableHtml .= $columnFilterHtml;

		$sortableClass = ($this->sortable !== null)?' class="sortable-table"':null;

		$tableHtml .= "\t</thead>\n\t<tbody" . $sortableClass . ">\n";

		$optsHtml = $this->getOptsAsHtml();

		$i = 0;
		foreach($resLimited as $r) {
			$class = ($i%2 == 0)?'odd':'even';

			$entryID = null;

			if($this->sortable !== null) {
				$kcArr = array();

				foreach($this->sortable['key'] as $kc)
					$kcArr[] = $r->$kc;

				$entryID = 'id="sort-' . implode('-', $kcArr) . '" ';
			}

			$tableHtml .= "\t<tr " . $entryID . "class=\"" . $class . "\">\n";

			if($this->selectable === true) {
				$tableHtml .= '<td class="selectable" style="vertical-align: middle;"><input type="checkbox" value="" name="' . $this->tableName . '[]"></td>';
			}
			
			if($this->reorder === true) {
				$tableHtml .= '<td class="reorder"><span>' . $this->getText('move') . '</span></td>';
			}

			foreach($this->columns as $col) {
				/** @var Column $col */
				if($col->isHidden() === true)
					continue;

				// @TODO Generate sql query don't give it as param in constructor so we can move this code to trash
				$sqlColumnIdentifier = StringUtils::afterFirst($col->getSQLColumn(),'.');
				if($sqlColumnIdentifier === '')
					$sqlColumnIdentifier = $col->getSQLColumn();

				$value = ($col->getSQLColumn() !== null)?$r->{$sqlColumnIdentifier}:null;

				foreach($col->getDecorators() as $d) {
					/** @var  ColumnDecorator $d */
					$value = $d->modify($value, $r, $col->getSQLColumn(), $this);
				}

				if($col->isFilterable() && $this->filtersApplied) {
					foreach($this->keywords as $k) {
						if(StringUtils::startsWith($k, '+') || StringUtils::startsWith($k, '-'))
							$k = substr($k, 1);

						$k = str_replace(array('/','(',')'),array('\/','\(','\)'),$k);

							// regex look behind if we're not in a html tag
						$value = preg_replace('/(?![^<]*>)(' . $k . ')/ims', '<span class="' . $this->cssClass . '-highlighted">$1</span>', $value);
					}
				}
				
				$cssClassesAttr = (count($col->getCssClasses()) > 0)?' class="' . implode(' ', $col->getCssClasses()) . '"':null;
				
				$tableHtml .= "\t\t<td" . $cssClassesAttr . ">" . $value . "</td>\n";
			}

			$tableHtml .= $this->prepareOptLink($optsHtml, $r);

			$tableHtml .= "\t</tr>\n";

			++$i;
		}

		$tableHtml .= '</tbody></table>';

		if($this->selectable === true) {
			$tableHtml .= '<p><a href="">delete</a> or <a href="">edit</a> choosen ones </p>';
		}

		return $tableHtml . '</form></div>';
	}
	
	protected function appendTableActions()
	{
		$tableSort = null;
		$tablePage = null;
		$tableResetFilters = false;
		
		if(isset($_SESSION['table'][$this->tableName]) === true) {
			if(isset($_SESSION['table'][$this->tableName]['sort']))
				$tableSort = $_SESSION['table'][$this->tableName]['sort'];

			if(isset($_SESSION['table'][$this->tableName]['page']))
				$tablePage = $_SESSION['table'][$this->tableName]['page'];
		}

		if(isset($_GET['table']) && $_GET['table'] == $this->tableName) {
			if(isset($_GET['sort']))
				$tableSort = $_SESSION['table'][$this->tableName]['sort'] = strip_tags($_GET['sort']);

			if(isset($_GET['page']))
				$tablePage = $_SESSION['table'][$this->tableName]['page'] = strip_tags($_GET['page']);

			if(isset($_GET['resetfilters']) || (isset($_GET['filter']) && strlen($_GET['filter']) === 0))
				$tableResetFilters = true;
		}

		if($tableSort !== null) {
			$sortParts = explode('-', $tableSort);

			if(count($sortParts) == 2) {
				$this->orderBy = array(
					'column' => $sortParts[0],
					'sort' => $sortParts[1]
				);
			}
		}

		if($tablePage !== null) {
			$this->currentPage = $tablePage;
		}

		if($tableResetFilters === true && isset($_SESSION['table'][$this->tableName]['filter']) === true) {
			unset($_SESSION['table'][$this->tableName]['filter']);
		}

		if((isset($_GET['table']) && $_GET['table'] == $this->tableName) || isset($_SESSION['table'][$this->tableName])) {
			// TODO move that out of here

			// Filters
			if((isset($_POST['filter']) /*&& strlen($_GET['filter']) > 0*/) || isset($_SESSION['table'][$this->tableName]['filter'])) {
				if(isset($_POST['filter']))
					$_SESSION['table'][$this->tableName]['filter'] = $_POST['filter'];
				
				if(isset($_SESSION['table'][$this->tableName]['filter']['keywords']) === true) {
					$this->keywordStr = $_SESSION['table'][$this->tableName]['filter']['keywords'];
					$this->keywords = $this->parseKeywords($_SESSION['table'][$this->tableName]['filter']['keywords']);
					$this->filtersApplied = true;
				}
				
				foreach($this->columns as $c) {
					if($c->getColumnFilter() === null)
						continue;
					
					if(isset($_SESSION['table'][$this->tableName]['filter'][$c->getColumnFilter()->getFilterName()]) === false)
						continue;
				}
			}
		}
	}

	protected function getColumnsByQuery($res)
	{
		$columns = array_keys((array)$res[0]);

		$colArr = array();

		foreach($columns as $c) {
			$tableRendererColumn = new Column($c, $c);

			$colArr[$c] = $tableRendererColumn;
		}

		return $colArr;
	}

	protected function getOptsAsHtml()
	{
		if($this->options === null)
			return null;

		$optsHtml = "\t\t<td class=\"options\"><ul>";

		foreach($this->options as $opt => $link) {
			$optLabel = $opt;

			if($opt === TableRenderer::OPT_DELETE) {
				$optLabel = $this->getText('delete');
			} elseif($opt === TableRenderer::OPT_EDIT) {
				$optLabel = $this->getText('edit');
			}

			$optsHtml .= "\t\t\t<li><a href=\"" . $link . "\" class=\"" . $opt . "\">" . $optLabel . "</a></li>\n";
		}

		$optsHtml .= "</ul></td>\n";

		return $optsHtml;
	}

	private function prepareOptLink($optStr, $r)
	{
		$repl = array();

		foreach($r as $k => $v) {
			$repl['{' . $k . '}'] = $v;
		}

		return str_replace(array_keys($repl), $repl, $optStr);
	}

	public function setCssClass($cssClass)
	{
		$this->cssClass = $cssClass;
	}

	public function addColumn(Column $column)
	{
		$this->columns[$column->getSQLColumn()] = $column;
	}

	public function setColumns(array $columns)
	{
		$this->columns = array();

		foreach($columns as $c) {
			/** @var Column $c */
			$this->columns[$c->getSQLColumn()] = $c;
		}
	}

	public function setOptions(array $options)
	{
		$this->options = $options;
	}

	public function setSelectable($selectable)
	{
		$this->selectable = $selectable;
	}

	public function setDefaultOrder(Column $column)
	{
		$this->orderBy = array(
			'column' => $column->getSQLColumn(),
			'sort' => $column->getSort()
		);

		$_SESSION['table'][$this->tableName]['sort'] = $this->orderBy['column'] . '-' . $this->orderBy['sort'];
	}

	/**
	 * @param array $keyColumn The keyColumns which build the WHERE condition with AND
	 * @param callable $reorderFunction The function to reorder an element
	 * @param string $sortColumn This column defines the order by for the statement
	 */
	public function reorder(array $keyColumn, \Closure $reorderFunction, $sortColumn = 'sort')
	{
		$this->reorder = true;
		$this->sortable = array(
			'sort' => $sortColumn,
			'key' => $keyColumn
		);

		if(!isset($_GET['table']) || $_GET['table'] != $this->tableName)
			return;

		if(!isset($_GET['reorder']) || !is_array($_GET['reorder']))
			return;

		foreach($_GET['reorder'] as $k => $v) {
			$reorderFunction($this->db, explode('-', $v), ($k+1));
		}
	}

	protected function parseKeywords($keywords)
	{
		preg_match_all('/[\+\-]?"[^"]+"|\S+/', $keywords, $matches);

		$cleanedMatches = array();

		foreach($matches[0] as $m)
			$cleanedMatches[] = str_replace('"', null, $m);

		return $cleanedMatches;
	}
	
	protected function getText($msgId, $msgIdPlural = null, $n = 0)
	{
		if($this->translator instanceof AbstractTranslator === false)
			return ($msgIdPlural === null || $n == 1) ? $msgId : $msgIdPlural;
		
		return $this->translator->_d('table_renderer', $msgId, $msgIdPlural, $n);
	}
	
	public function setDefaultOrderBy($column, $sort)
	{
		$this->orderBy = array(
			'column' =>  $column,
			'sort' => $sort
		);
	}

	/**
	 * @param AbstractTranslator $translator
	 */
	public function setTranslator(AbstractTranslator $translator)
	{
		$translator->bindTextDomain('table_renderer', 'UTF-8');
		
		$this->translator = $translator;
	}
}

/* EOF */