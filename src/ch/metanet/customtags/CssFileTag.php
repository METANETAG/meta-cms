<?php

namespace ch\metanet\customtags;

use timesplinter\tsfw\template\TemplateEngine;
use timesplinter\tsfw\template\TagNode;
use timesplinter\tsfw\template\TemplateTag;
use ch\timesplinter\htmlparser\ElementNode;
use ch\timesplinter\htmlparser\HtmlAttribute;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright (c) 2012, METANET AG
 * @version 1.0.0
 */
class CssFileTag extends TemplateTag implements TagNode
{
	public function replaceNode(TemplateEngine $tplEngine, ElementNode $node)
	{
		// DATA
		$value = $node->getAttribute('href')->value;
		$node->removeAttribute('href');
		
		$node->namespace = null;
		$node->tagName = 'link';

		$cssRevisionData = $tplEngine->getData('css_revision');

		$cssRevision = isset($cssRevisionData)?'?' . $cssRevisionData:null;

		$node->addAttribute(new HtmlAttribute('href', $value . $cssRevision));
	}

	/**
	 * @return string
	 */
	public static function getName()
	{
		return 'cssFile';
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