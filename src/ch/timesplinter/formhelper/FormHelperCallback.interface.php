<?php
namespace ch\timesplinter\formhelper;

/**
 * Description of FormHelperCallback
 *
 * @author Pascal Münst <entwicklung@metanet.ch>
 * @copyright (c) 2012, METANET AG
 * @version 1.0
 */
interface FormHelperCallback {
	public function execute($fieldInfo, $refererUri);
}

?>
