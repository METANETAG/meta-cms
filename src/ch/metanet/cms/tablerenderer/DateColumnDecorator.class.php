<?php

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */

namespace ch\metanet\cms\tablerenderer;


class DateColumnDecorator extends ColumnDecorator {
	private $dateFormatTo;
	private $dateFormatFrom;

	public function __construct($dateFormatTo, $dateFormatFrom = null) {
		$this->dateFormatTo = $dateFormatTo;
		$this->dateFormatFrom = $dateFormatFrom;
	}

	public function modify($value, \stdClass $record, $selector, TableRenderer $tableRenderer) {
		if($value === null)
			return null;

		try {
			$dt = ($this->dateFormatFrom === null)?new \DateTime($value):\DateTime::createFromFormat($this->dateFormatFrom, $value);

			$errors = $dt->getLastErrors();

			if($errors['error_count'] > 0)
				return $value;

			return '<span class="date">' . $dt->format($this->dateFormatTo) . '</span>';
		} catch(\Exception $e) {

			return $value;
		}
	}
}

/* EOF */