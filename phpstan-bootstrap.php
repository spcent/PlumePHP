<?php

/**
 * PHPStan bootstrap — defines framework constants that are normally set by
 * public/index.php at runtime so the static analyser can resolve them.
 *
 * Values are representative; PHPStan only needs the constants to exist and
 * have a compatible type — actual paths are irrelevant for analysis.
 */

defined('DS')               || define('DS', DIRECTORY_SEPARATOR);
defined('PLUME_VERSION')    || define('PLUME_VERSION', '1.3.1');
defined('PLUME_PHP_PATH')   || define('PLUME_PHP_PATH', __DIR__ . '/library');
defined('VENDOR_PATH')      || define('VENDOR_PATH',    __DIR__ . '/vendor');
defined('APP_PATH')         || define('APP_PATH',       __DIR__ . '/application');
defined('CONFIG_PATH')      || define('CONFIG_PATH',    __DIR__ . '/config');
defined('PUBLIC_PATH')      || define('PUBLIC_PATH',    __DIR__ . '/public');
defined('LOG_PATH')         || define('LOG_PATH',       __DIR__ . '/storage/log');
defined('IS_CLI')           || define('IS_CLI',         0);
defined('IS_GET')           || define('IS_GET',         1);
defined('IS_POST')          || define('IS_POST',        0);
defined('IS_AJAX')          || define('IS_AJAX',        0);
defined('SITE_DOMAIN')      || define('SITE_DOMAIN',    'http://localhost');
defined('PLUME_START_TIME') || define('PLUME_START_TIME', 0.0);
defined('PLUME_START_MEMORY') || define('PLUME_START_MEMORY', 0);
