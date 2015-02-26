<?php

use ch\timesplinter\autoloader\Autoloader;
use ch\timesplinter\core\FrameworkAutoloader;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 *
 * Call it like this: php /var/www/metanet.ch/metanet/site/ch/metanet/cms/module/mod_search/common/rebuildindex.cron.php dev
 */

echo "
==============================
 METAcms / mod_search indexer
==============================
This script should only be ran as a cronjob! It will rebuild the Zend_Search indexes!

";

if(!isset($argv[1]))
	die('Please run this script with an environement as param 1' . "\n");

$env = $argv[1];

date_default_timezone_set('Europe/Berlin');
/*$_SERVER['SERVER_NAME'] = 'metanet.ch.metdev.ch';
$_SERVER['SERVER_PORT'] = 80;
$_SERVER['QUERY_STRING'] = null;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';*/

// Framework specific constants
//define('REQUEST_TIME', $_SERVER['REQUEST_TIME']+microtime());
define('FW_DIR', DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'tsFramework' . DIRECTORY_SEPARATOR . 'tsfw_src' . DIRECTORY_SEPARATOR);
define('MODULES_DIR', 'modules' . DIRECTORY_SEPARATOR);

// Site specific constants
define('SITE_ROOT', dirname(__FILE__) . '/../../../../../..' . DIRECTORY_SEPARATOR);
define('CACHE_DIR' , SITE_ROOT . 'cache' . DIRECTORY_SEPARATOR);
define('SETTINGS_DIR' , SITE_ROOT . 'settings' . DIRECTORY_SEPARATOR);

// Initialize Autoloader
require_once FW_DIR . 'ch/timesplinter/autoloader/Autoloader.class.php';
require_once FW_DIR . 'ch/timesplinter/core/FrameworkAutoloader.class.php';

$autoLoader = new FrameworkAutoloader(CACHE_DIR . 'cache.autoload');
$autoLoader->addPath('fw-logic', array(
	'path' => FW_DIR,
	'mode' => Autoloader::MODE_NAMESPACE,
	'class_suffix' => array('.class.php', '.interface.php')
));


$autoLoader->register();

$settings = new \ch\timesplinter\core\Settings(SETTINGS_DIR);
$autoLoader->addPathsFromSettings($settings->autoloader);

\ch\timesplinter\core\FrameworkLoggerFactory::setEnvironment($env);

if(!isset($settings->db->{$env}))
	die('No database connection for environment: ' . $env . "\n");

$dbSettings = $settings->db->{$env};

$db = \ch\timesplinter\db\DBFactory::getNewInstance($dbSettings->type, new \ch\timesplinter\db\DBConnect(
	$dbSettings->host,
	$dbSettings->database,
	$dbSettings->user,
	$dbSettings->password
));

$indexer = new \ch\metanet\cms\module\mod_search\common\Indexer($db);
$indexer->start();

/* EOF */