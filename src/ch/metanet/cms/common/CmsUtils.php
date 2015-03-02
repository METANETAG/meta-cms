<?php

namespace ch\metanet\cms\common;

use timesplinter\tsfw\db\DB;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class CmsUtils
{
	public static function getRightsFromDec($rights)
	{
		$rightsBinary = decbin($rights);
		$rightsArr = str_split($rightsBinary);
		$rightsArrCount  = count($rightsArr);

		$rightsArrStrs = array();

		for($i = 0; $i < $rightsArrCount; ++$i) {
			if($rightsArr[$i] == 0)
				continue;

			if($i === 0)
				$rightsArrStrs[] = 'read';
			elseif($i === 1)
				$rightsArrStrs[] = 'write';
		}

		return $rightsArrStrs;
	}

	public static function getRightsAsString($rights)
	{
		$rightsArrStrs = self::getRightsFromDec($rights);

		return implode(', ', $rightsArrStrs);
	}

	public static function getRightsAsDec($read, $write)
	{
		return bindec($read . $write);
	}

	public static function getErrorsAsHtml($errors)
	{
		return self::renderMessage($errors, 'error');
	}

	public static function renderMessage($msg, $type)
	{
		if($msg === null || (is_string($msg) && strlen($msg) === 0) || (is_array($msg) && count($msg) <= 0))
			return null;

		$msgStr = (is_array($msg) === true)?'<ul class="msg-' . $type . '"><li>' . implode('</li><li>', $msg) . '</li></ul>':'<div class="msg-' . $type . '">' . $msg . '</div>';

		return  $msgStr;
	}

	/**
	 * @param string $elementIdentifier
	 * @param array $settings
	 * @param DB $db
	 * @param array $modIDs Module IDs to load settings for
	 * @param array $pageIDs Page context IDs
	 * @param string $elementInstanceColumn
	 * @param string $pageInstanceColumn
	 * @return array
	 */
	public static function loadCmsElementSettings($elementIdentifier, $settings, DB $db, array $modIDs, array $pageIDs, $elementInstanceColumn = 'element_instance_IDFK', $pageInstanceColumn = 'page_IDFK')
	{
		$params = array_merge($modIDs, $pageIDs, $pageIDs);

		$settingsCols = array();

		foreach($settings as $col => $as)
			$settingsCols[] = $col . ' ' . $as;

		$stmntSettings = $db->prepare("
			SELECT " . $elementInstanceColumn . ", " . $pageInstanceColumn . ", " . implode(', ', $settingsCols) . "
			FROM " . $elementIdentifier . "
			WHERE " . $elementInstanceColumn . " IN (" . DB::createInQuery($modIDs) . ")
			AND " . $pageInstanceColumn . " IN (" . DB::createInQuery($pageIDs) . ")
			ORDER BY FIELD(" . $pageInstanceColumn . ", " . DB::createInQuery($pageIDs) . ")
		");

		$resSettings = $db->select($stmntSettings, $params);

		$settingsArr = array();

		foreach($resSettings as $res)
			$settingsArr[$res->{$elementInstanceColumn}][$res->{$pageInstanceColumn}] = $res;

		return $settingsArr;
	}
}

/* EOF */