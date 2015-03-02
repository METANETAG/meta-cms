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
class FormAddRemoveTag extends TemplateTag implements TagNode
{
	public function replaceNode(TemplateEngine $tplEngine, ElementNode $node)
	{
		$tplEngine->checkRequiredAttrs($node, array('chosen', 'name'));

		// DATA
		$chosenEntriesSelector = $node->getAttribute('chosen')->value;
		$poolEntriesSelector = $node->doesAttributeExist('pool') ? $node->getAttribute('pool')->value : null;
		$nameSelector = $node->getAttribute('name')->value;

		// Generate
		$newNode = new TextNode($tplEngine->getDomReader());
		$newNode->content = '<?= ' . self::class . '::render(\'' . $nameSelector . '\', \'' . $chosenEntriesSelector . '\', \'' . $poolEntriesSelector . '\', $this); ?>';

		$node->parentNode->insertBefore($newNode, $node);
		$node->parentNode->removeNode($node);
	}
	
	public static function render($name, $chosenSelector, $poolSelector, TemplateEngine $tplEngine)
	{
		$chosenEntries = $tplEngine->getDataFromSelector($chosenSelector);
		$poolEntries = array();

		if($poolSelector !== null)
			$poolEntries = $tplEngine->getDataFromSelector($poolSelector);
		
		$html = '<div class="add-remove" name="' . $name . '">';

		// Choosen
		$html .= '<ul class="option-list chosen">';

		foreach($chosenEntries as $id => $title) {
			$html .= '<li id=\"" . $name . "-' . $id . '\">' . $title . '</li>';
		}

		$html .= '</ul>';

		if(count($poolEntries) > 0) {
			// left or right
			$html .= '<div class="between">
				<a href="#" class="entries-add" title="add selected entries">&larr;</a>
				<br>
				<a href="#" class="entries-remove" title="remove selected entries">&rarr;</a>
			</div>';

			// Pool
			$html .= '<ul class="option-list pool">';

			foreach($poolEntries as $id => $title) {
				$html .= '<li id=\"" . $name . "-' . $id . '\">' . $title . '</li>';
			}

			$html .= '</ul>';
		}

		$html .= '</div>';
		
		return $html;
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