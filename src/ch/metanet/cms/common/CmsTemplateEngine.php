<?php


namespace ch\metanet\cms\common;

use timesplinter\tsfw\template\TemplateCacheStrategy;
use timesplinter\tsfw\template\TemplateEngine;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class CmsTemplateEngine extends TemplateEngine
{
	/**
	 *
	 * @param TemplateCacheStrategy $tplCacheInterface The template cache object
	 * @param string $tplNsPrefix The prefix for custom tags in the template file
	 * 
	 * @return \ch\metanet\cms\common\CmsTemplateEngine A template engine instance to render files
	 */
	public function __construct(TemplateCacheStrategy $tplCacheInterface, $tplNsPrefix)
	{
		parent::__construct($tplCacheInterface, $tplNsPrefix, array(
			'formComponent' => '\ch\metanet\customtags\FormComponentTag',
			'formAddRemove' => '\ch\metanet\customtags\FormAddRemoveTag',
			'cssFile' => '\ch\metanet\customtags\CssFileTag',
			'translate' => '\ch\metanet\customtags\TranslateTag'
		));
	}
}

/* EOF */