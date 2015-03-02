<?php

namespace ch\metanet\cms\common;

use timesplinter\tsfw\db\DB;
use ch\metanet\cms\common\CmsElement;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
interface CmsElementSortable
{
	public function reorderElements(DB $db, CmsElement $movedCmsElement, $dropzoneID, $elementOrder);
}