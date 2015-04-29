<?php


namespace ch\metanet\cms\common;

use timesplinter\tsfw\template\TemplateCacheStrategy;
use timesplinter\tsfw\template\TemplateEngine;

/**
 * An extensions of the basic template engine. Injects some additional custom tags which the CMS needs.
 * 
 * @see TemplateEngine
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class CmsTemplateEngine extends TemplateEngine
{
	/**
	 * @param TemplateCacheStrategy $tplCacheInterface The template cache strategy
	 * @param string $tplNsPrefix The prefix for custom tags in the template file
	 */
	public function __construct(TemplateCacheStrategy $tplCacheInterface, $tplNsPrefix)
	{
		parent::__construct($tplNsPrefix, $tplCacheInterface, array(
			'formComponent' => '\ch\metanet\customtags\FormComponentTag',
			'formAddRemove' => '\ch\metanet\customtags\FormAddRemoveTag',
			'cssFile' => '\ch\metanet\customtags\CssFileTag',
			'translate' => '\ch\metanet\customtags\TranslateTag'
		));
	}
}