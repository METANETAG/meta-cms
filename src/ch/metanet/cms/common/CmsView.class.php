<?php

namespace ch\metanet\cms\common;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
class CmsView
{
	protected $tplSection;
	protected $tplEngine;

	public function __construct(CmsTemplateEngine $tplEngine, $tplSection)
	{
		$this->tplSection = $tplSection;
		$this->tplEngine = $tplEngine;
	}

	public function render($tplFile, array $tplVars = array())
	{
		$tplFilePath = $this->tplSection . $tplFile;

		return $this->tplEngine->getResultAsHtml(
			$tplFilePath,
			$tplVars
		);
	}
	
	public function renderModuleResponse(CmsModuleResponse $moduleResponse)
	{
		$tplFilePath = $this->tplSection . $moduleResponse->getTplFile();

		return $this->tplEngine->getResultAsHtml(
			$tplFilePath,
			$moduleResponse->getTplVars()
		);
	}
}

/* EOF */