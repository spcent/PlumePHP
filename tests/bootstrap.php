<?php

declare(strict_types=1);

// Pre-define path constants so PlumeEngine::boot() finds the correct directories.
// PLUME_PHP_PATH stays at library/ (where .env lives); everything else points to the project root.
$projectRoot = dirname(__DIR__);
$libraryPath = $projectRoot . DIRECTORY_SEPARATOR . 'library';

defined('DS')              || define('DS', DIRECTORY_SEPARATOR);
defined('PLUME_PHP_PATH')  || define('PLUME_PHP_PATH', $libraryPath);
defined('APP_PATH')        || define('APP_PATH', $projectRoot . DS . 'application');
defined('CONFIG_PATH')     || define('CONFIG_PATH', $projectRoot . DS . 'config');
defined('PUBLIC_PATH')     || define('PUBLIC_PATH', $projectRoot . DS . 'public');
defined('LOG_PATH')        || define('LOG_PATH', $projectRoot . DS . 'storage' . DS . 'log');

require_once $libraryPath . DS . 'PlumePHP.php';
require_once $libraryPath . DS . 'core' . DS . 'Plume' . DS . 'Libs' . DS . 'Action.php';

unset($projectRoot, $libraryPath);
