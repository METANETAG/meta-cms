<?php

namespace ch\metanet\cms\locale;

use PoParser\Parser;
use timesplinter\tsfw\i18n\gettext\PoParserInterface;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class PoParser implements PoParserInterface
{
	/** @var Parser */
	protected $parser;
	
	public function __construct()
	{
		$this->parser = new Parser();
	}
	
	/**
	 * @param string $filePath
	 *
	 * @return array
	 */
	public function extract($filePath)
	{
		if(($entries = $this->parser->read($filePath)) === false)
			return array();
		
		return $entries;
	}
}

/* EOF */ 