<?php


namespace ch\metanet\customtags;

use timesplinter\tsfw\htmlparser\ElementNode;
use timesplinter\tsfw\htmlparser\TextNode;
use timesplinter\tsfw\template\TagNode;
use timesplinter\tsfw\template\TemplateEngine;
use timesplinter\tsfw\template\TemplateTag;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class FormComponentTag extends TemplateTag implements TagNode
{
	public function replaceNode(TemplateEngine $tplEngine, ElementNode $node)
	{
		$tplEngine->checkRequiredAttrs($node, array('form', 'name'));

		// DATA
		$newNode = new TextNode($tplEngine->getDomReader());
		$newNode->content = '<?= ' . self::class . '::render(\'' . $node->getAttribute('form')->value . '\', \'' . $node->getAttribute('name')->value . '\', $this); ?>';

		$node->parentNode->insertBefore($newNode, $node);
		$node->parentNode->removeNode($node);
	}
	
	public static function render($formSelector, $componentName, TemplateEngine $tplEngine)
	{
		$callback = array($tplEngine->getDataFromSelector($formSelector), 'getComponent');
		$component = call_user_func($callback, $componentName);
		
		return call_user_func(array($component, 'render'));
	}
	
	/**
	 * @return string
	 */
	public static function getName()
	{
		return 'formComponent';
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