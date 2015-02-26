<?php

namespace ch\timesplinter\plugins\xhprof;

use \ch\timesplinter\core\FrameworkPlugin;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */

class XhprofPlugin extends FrameworkPlugin {
	public function beforeRequestBuilt() {
		//xhprof_enable();
		require '/tmp/xhprof/external/header.php';
	}

	public function afterResponseSent() {
		/*$data = xhprof_disable();

		$XHPROF_ROOT = '/tmp/xhprof-master';
		include_once $XHPROF_ROOT . '/xhprof_lib/utils/xhprof_lib.php';
		include_once $XHPROF_ROOT . '/xhprof_lib/utils/xhprof_runs.php';

		$xhprof_runs = new \XHProfRuns_Default();

		// Save the run under a namespace "xhprof".
		$run_id = $xhprof_runs->save_run($data, 'xhprof');*/
		require '/tmp/xhprof/external/footer.php';
	}
}

/* EOF */