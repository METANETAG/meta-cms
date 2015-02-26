<?php

namespace ch\metanet\cms\tablerenderer;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class CallbackColumnDecorator extends  ColumnDecorator
{
	protected $callback;

	/**
	 * @param callable|string|array $callback A function or class method
	 */
	public function __construct($callback)
	{
		$this->callback = $callback;
	}

	public function modify($value, \stdClass $record, $selector, TableRenderer $tableRenderer)
	{
		return call_user_func_array($this->callback, array($value, $record, $selector, $tableRenderer));
	}
}

/* EOF */