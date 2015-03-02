<?php

namespace ch\metanet\customtags;

use ch\timesplinter\htmlparser\ElementNode;
use ch\timesplinter\htmlparser\TextNode;
use timesplinter\tsfw\template\TagInline;
use timesplinter\tsfw\template\TagNode;
use timesplinter\tsfw\template\TemplateEngine;
use timesplinter\tsfw\template\TemplateTag;
use timesplinter\tsfw\i18n\common\AbstractTranslator;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2012, METANET AG, www.metanet.ch
 */
class TranslateTag extends TemplateTag implements TagNode, TagInline
{
	public function replaceNode(TemplateEngine $tplEngine, ElementNode $node)
	{
		$tplEngine->checkRequiredAttrs($node, 'id');
		
		$replValue = self::replace($tplEngine, $node->getAttribute('id')->value, $node->getAttribute('domain')->value);

		$replNode = new TextNode($tplEngine->getDomReader());
		$replNode->content = $replValue;

		$node->parentNode->replaceNode($node, $replNode);
	}

	public function replaceInline(TemplateEngine $tplEngine, $params) 
	{
		$domain = (array_key_exists('domain', $params)) ? $params['domain'] : null;
		
		return self::replace($tplEngine, $params['id'], $domain);
	}

	public function replace(TemplateEngine $tplEngine, $key, $domain)
	{
		$domainParam = ($domain !== null) ? ', \'' . $domain . '\'' : null;
		
		return '<?php echo ' . __CLASS__ . '::getText($this, \'' . $key . '\'' . $domainParam . '); ?>';
	}
	
	public static function getText(TemplateEngine $tplEngine, $key, $domain = null)
	{
		if(($translator = $tplEngine->getData('translator')) instanceof AbstractTranslator === false)
			return $key;
		
		/** @var AbstractTranslator $translator */
		
		if($domain === null)
			return $translator->_($key);
		
		return $translator->_d($domain, $key);
	}

	/**
	 * @return string
	 */
	public static function getName()
	{
		return 'translate';
	}

	/**
	 * @return bool
	 */
	public static function isElseCompatible()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	public static function isSelfClosing()
	{
		return true;
	}
}

/* EOF */