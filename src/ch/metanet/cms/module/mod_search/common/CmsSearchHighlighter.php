<?php


namespace ch\metanet\cms\module\mod_search\common;
use ZendSearch\Lucene\Analysis\Analyzer\Analyzer;
use ZendSearch\Lucene\Analysis\Analyzer\Common\Utf8\CaseInsensitive;
use ZendSearch\Lucene\Analysis\Token;
use ZendSearch\Lucene\Document;
use ZendSearch\Lucene\Search\Highlighter\HighlighterInterface;


/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class CmsSearchHighlighter {
	private $wordsToHighlight;
	private $analyzer;
	private $callback;

	public function __construct($keywords, $callback = null) {
		if (!is_array($keywords)) {
			$keywords = array($keywords);
		}

		$this->callback = array($this, 'applyHighlighting');

		if($callback !== null && is_callable($callback)) {
			$this->callback = $callback;
		}

		$wordsToHighlightList = array();
		$this->analyzer = new CaseInsensitive();

		foreach($keywords as $wordString) {
			$wordsToHighlightList[] = $this->analyzer->tokenize($wordString);
		}

		$wordsToHighlight = call_user_func_array('array_merge', $wordsToHighlightList);

		if(count($wordsToHighlight) == 0) {
			return /*$this->_doc->saveHTML()*/;
		}

		$wordsToHighlightFlipped = array();
		foreach ($wordsToHighlight as $id => $token) {
			$wordsToHighlightFlipped[$token->getTermText()] = $id;
		}

		$this->wordsToHighlight = $wordsToHighlightFlipped;

		mb_internal_encoding("UTF-8");
	}

	public function highlightMatches($value, $encoding = 'UTF-8') {
		$this->analyzer->setInput($value, $encoding);

		$matchedTokens = array();

		while (($token = $this->analyzer->nextToken()) !== null) {
			if (isset($this->wordsToHighlight[$token->getTermText()])) {
				$matchedTokens[] = $token;
			}
		}

		if (count($matchedTokens) == 0) {
			return $value;
		}

		$matchedTokens = array_reverse($matchedTokens);
		$tmpValue = null;

		foreach($matchedTokens as $token) {
			/** @var Token $token */
			$tmpValue = mb_substr($value, 0, $token->getStartOffset());

			$tmpValue .= call_user_func($this->callback, mb_substr($value, $token->getStartOffset(), ($token->getEndOffset() - $token->getStartOffset())));

			$tmpValue .= mb_substr($value, $token->getEndOffset());

			$value = $tmpValue;
		}

		return $tmpValue;
	}

	public function applyHighlighting($textToHighlight) {
		return '<span class="search-term">' . $textToHighlight . '</span>';
	}
}

/* EOF */