<?php

namespace ch\metanet\cms\common;

use timesplinter\tsfw\db\DB;
use ch\metanet\cms\common\CmsElement;

/**
 * Enables a CMS element to make its child elements sortable
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 */
interface CmsElementSortable
{
	/**
	 * The reorder activities to take place after moved an element to another place
	 * 
	 * @param DB $db The database instance
	 * @param CmsElement $movedCmsElement The CMS element which actually got moved
	 * @param string $dropZoneID The name of the drop zone which the reordering affects
	 * @param array $elementOrder A list of the order. Position as key and the element ID and page ID as value
	 * separated by a dash 
	 */
	public function reorderElements(DB $db, CmsElement $movedCmsElement, $dropZoneID, array $elementOrder);
}