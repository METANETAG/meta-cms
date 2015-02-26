<?php

namespace ch\metanet\cms\common;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class CmsModuleResponse
{
	protected $tplFile;
	protected $tplVars;
	protected $httpResponseCode;

	/**
	 * @param $tplFile
	 * @param array $tplVars
	 */
	public function __construct($tplFile, array $tplVars = array(), $httpResponseCode = 200)
	{
		$this->tplFile = $tplFile;
		$this->tplVars = $tplVars;
		$this->httpResponseCode = $httpResponseCode;
	}

	/**
	 * @return string
	 */
	public function getTplFile()
	{
		return $this->tplFile;
	}
	
	/**
	 * @return array
	 */
	public function getTplVars()
	{
		return $this->tplVars;
	}

	/**
	 * @return int
	 */
	public function getHttpResponseCode()
	{
		return $this->httpResponseCode;
	}
}

/* EOF */