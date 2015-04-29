<?php

namespace ch\metanet\cms\common;

/**
 * If this interface gets implemented then the implementing {@see BackendController} can define unprotected methods 
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2015, METANET AG
 */
interface BackendControllerUnprotected
{
	/**
	 * @return string[] A list of method names which should not be protected
	 */
	public static function getUnprotectedMethods();
}

/* EOF */