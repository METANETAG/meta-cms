<?php


namespace ch\metanet\customtags;

use ch\timesplinter\htmlparser\TextNode;
use ch\timesplinter\htmlparser\ElementNode;
use timesplinter\tsfw\template\TemplateTag;
use timesplinter\tsfw\template\TagNode;
use timesplinter\tsfw\template\TemplateEngine;

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
		$formHandler = $tplEngine->getSelectorAsPHPStr($node->getAttribute('form')->value);
		$fieldName = $node->getAttribute('name')->value;

		// Generate
		$textContent = null;

		$fieldGetterCall = $formHandler . '->getComponent(\'' . $fieldName . '\')';
		$html = '<?php echo ' . $fieldGetterCall . '->render(); ?>';

		$newNode = new TextNode($tplEngine->getDomReader());
		$newNode->content = $html;

		$node->parentNode->insertBefore($newNode, $node);
		$node->parentNode->removeNode($node);
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