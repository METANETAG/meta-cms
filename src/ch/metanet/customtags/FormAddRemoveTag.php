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
class FormAddRemoveTag extends TemplateTag implements TagNode
{
	public function replaceNode(TemplateEngine $tplEngine, ElementNode $node)
	{
		$tplEngine->checkRequiredAttrs($node, array('chosen', 'name'));

		// DATA
		$chosenEntries = $tplEngine->getSelectorAsPHPStr($node->getAttribute('chosen')->value);

		$poolEntries = null;

		if($node->doesAttributeExist('pool') === true)
			$poolEntries = $tplEngine->getSelectorAsPHPStr($node->getAttribute('pool')->value);

		$name = $node->getAttribute('name')->value;

		// Generate
		$textContent = null;

		$html = '<div class="add-remove" name="' . $name . '">';

		// Choosen
		$html .= '<ul class="option-list chosen">';

		$html .= "<?php foreach(" . $chosenEntries . " as \$id => \$title) {
			echo '<li id=\"" . $name . "-' . \$id . '\">' . \$title . '</li>';
		} ?>";

		$html .= '</ul>';

		if($poolEntries !== null) {
			// left or right
			$html .= '<div class="between">
				<a href="#" class="entries-add" title="add selected entries">&larr;</a>
				<br>
				<a href="#" class="entries-remove" title="remove selected entries">&rarr;</a>
			</div>';

			// Pool
			$html .= '<ul class="option-list pool">';

			$html .= "<?php foreach(" . $poolEntries . " as \$id => \$title) {
				echo '<li id=\"" . $name . "-' . \$id . '\">' . \$title . '</li>';
			} ?>";

			$html .= '</ul>';
		}

		$html .= '</div>';

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
		return 'formAddRemove';
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