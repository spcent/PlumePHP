<?php

declare(strict_types=1);

/*
 * 	This file is part of the your PlumePHP package.
 *
 * 	The PHP Application For Code Poem For You.
 * 	(c) 2015-2035 http://plumephp.com All rights reserved.
 *
 * 	For the full copyright and license information, please view the LICENSE
 * 	file that was distributed with this source code.
 */

define('PLUME_START_MEMORY', memory_get_usage());
define('PLUME_START_TIME', microtime(true));
define('PLUME_VERSION', '1.3.1');
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('PLUME_PHP_PATH') or define('PLUME_PHP_PATH', __DIR__);
defined('VENDOR_PATH') or define('VENDOR_PATH', PLUME_PHP_PATH.DS.'vendor');
defined('LOG_PATH') or define('LOG_PATH', PLUME_PHP_PATH.DS.'storage'.DS.'log');
defined('IS_CLI') or define('IS_CLI', PHP_SAPI === 'cli' ? 1 : 0);

if (!interface_exists('JsonSerializable')) {
    interface JsonSerializable
    {
        public function jsonSerialize();
    }
}

/**
 * Gets and sets configuration parameters to support bulk definition
 * If $key is an associative array, the configuration is written as k-v.
 * If $key is a numeric indexed array, the corresponding configuration
 * array is returned.
 *
 * @param array|string $key   The key
 * @param null|array   $value The value
 *
 * @return null|array
 */
function C($key, $value = null)
{
    static $_config = [];
    static $_snapshot = null;
    // Read-path cache: stores resolved values for dot-notation string keys.
    // Invalidated on any write so stale data is never served.
    static $_readCache = [];

    // Internal snapshot operations for worker-mode config isolation.
    // Using null-byte sentinel values to avoid collision with real config keys.
    if ($key === "\x00snapshot_take\x00") {
        $_snapshot  = $_config;
        $_readCache = [];
        return null;
    }
    if ($key === "\x00snapshot_restore\x00") {
        if ($_snapshot !== null) {
            $_config    = $_snapshot;
            $_readCache = [];
        }
        return null;
    }

    $args = func_num_args();
    if (1 === $args) {
        if (is_string($key)) {
            // Serve from read cache when available (hot path in Worker mode)
            if (isset($_readCache[$key])) {
                return $_readCache[$key];
            }

            // Up to three layers of dot-notation
            $names      = explode('.', $key, 3);
            $countNames = count($names);
            if (1 === $countNames) {
                $result = $_config[$key] ?? null;
            } elseif (2 === $countNames) {
                $result = $_config[$names[0]][$names[1]] ?? null;
            } else {
                $result = $_config[$names[0]][$names[1]][$names[2]] ?? null;
            }

            $_readCache[$key] = $result;
            return $result;
        }

        if (is_array($key)) {
            if (array_keys($key) !== range(0, count($key) - 1)) {
                // Associative array → bulk write; invalidate cache
                $_config    = array_merge($_config, $key);
                $_readCache = [];
            } else {
                // Numeric-indexed array → bulk read (not cached individually)
                $ret = [];
                foreach ($key as $k) {
                    $ret[$k] = $_config[$k] ?? null;
                }
                return $ret;
            }
        }
    } else {
        if (is_string($key)) {
            $_config[$key] = $value;
            // Invalidate any cached entry that starts with this key
            // (covers both the exact key and parent dot-paths)
            foreach (array_keys($_readCache) as $ck) {
                if ($ck === $key || str_starts_with($ck, $key . '.')) {
                    unset($_readCache[$ck]);
                }
            }
        }
    }

    return null;
}
/**
 * If the file exists, include it.
 *
 * @param string $path file path
 * @param bool   $once Whether to use include_once, the default is false
 *
 * @return
 */
function I(string $path, bool $once = false)
{
    if (file_exists($path)) {
        $once ? include_once $path : include $path;
    }
}
/**
 * Record log.
 *
 * @param string $msg     The record
 * @param array  $context Replaces the placeholder in the record information
 *                        with context information, which is empty by default
 * @param string $level   Log level, the default is DEBUG
 * @param bool   $wf      Whether to log in a separate wf log, the default is false
 */
function L(string $msg, array $context = [], string $level = 'DEBUG', bool $wf = false)
{
    PlumePHP::app()->log($msg, $context, $level, $wf);
}
/**
 * Gets the exception stack.
 *
 * @param mixed $e
 * @param mixed $offset
 */
function T($e, $offset = 9)
{
    $removeThisCall = false;
    if (empty($e) || !is_a($e, 'Exception')) {
        $e = new Exception();
        $removeThisCall = true;
    }

    $trace = explode("\n", $e->getTraceAsString());
    // reverse array to make steps line up chronologically
    $trace = array_reverse($trace);
    $trace = array_slice($trace, $offset);
    if ($removeThisCall) {
        array_pop($trace); // remove call to this method
    }

    $length = count($trace);
    $result = [];
    for ($i = 0; $i < $length; $i++) {
        // replace '#someNum' with '$i)', set the right ordering
        $result[] = ($i + 1).')'.substr($trace[$i], strpos($trace[$i], ' '));
    }

    return "\t".implode("\n\t", $result);
}
/**
 * Error log output.
 *
 * @param string     $prefix The prefix of message
 * @param \Exception $e      The exception object
 */
function E(string $prefix, Exception $e)
{
    L($prefix.$e->getMessage().PHP_EOL.T($e, 9), [], 'ERROR', true);
}

// ---------------------------------------------------------------------------
// Class files — require in dependency order so autoloading is not needed.
// Each file contains exactly the class(es) named after the file.
// ---------------------------------------------------------------------------
require_once __DIR__ . '/Plume/Engine/Collection.php';
require_once __DIR__ . '/Plume/Engine/Loader.php';
require_once __DIR__ . '/Plume/Http/Route.php';
require_once __DIR__ . '/Plume/Http/Request.php';
require_once __DIR__ . '/Plume/Http/Response.php';
require_once __DIR__ . '/Plume/Http/Router.php';
require_once __DIR__ . '/Plume/Support/View.php';
require_once __DIR__ . '/Plume/Support/DotEnv.php';
require_once __DIR__ . '/Plume/Engine/Container.php';
require_once __DIR__ . '/Plume/Engine/Event.php';
require_once __DIR__ . '/Plume/Support/Param.php';
require_once __DIR__ . '/Plume/Support/Logger.php';
require_once __DIR__ . '/Plume/Support/LogHandlers.php';
require_once __DIR__ . '/Plume/Support/Schema.php';
require_once __DIR__ . '/Plume/Support/JsonMapper.php';
require_once __DIR__ . '/Plume/Engine/ActionException.php';
require_once __DIR__ . '/Plume/Engine/ActionResolver.php';
require_once __DIR__ . '/Plume/Engine/ActionLocator.php';
require_once __DIR__ . '/Plume/Engine/ActionNaming.php';
require_once __DIR__ . '/Plume/Engine/ActionInvoker.php';
require_once __DIR__ . '/Plume/Engine/Engine.php';
require_once __DIR__ . '/Plume/Support/CmdService.php';
require_once __DIR__ . '/Plume/Support/DocGenerator.php';
require_once __DIR__ . '/PlumeHelper.php';

/**
 * The PlumePHP class is a static representation of the framework.
 *
 * Core.
 *
 * @method static PlumeEngine  app()                                                             Gets the application object instance.
 * @method static PlumeRequest  request()                                                         Gets the current HTTP request object.
 * @method static PlumeResponse response()                                                        Gets the current HTTP response object.
 * @method static PlumeView     view()                                                            Gets the view/template renderer.
 * @method static PlumeRouter   router()                                                          Gets the URL router.
 * @method static PlumeLogger   logger()                                                          Gets the logger.
 * @method static void          start()                                                           Starts the framework.
 * @method static void          stop(int $code = null)                                            Stops the framework and sends a response.
 * @method static void          halt(int $code = 200, string $message = '')                       Stop the framework with a status code and message.
 * @method static void          route(string $pattern, callable $callback)                        Maps a URL pattern to a callback.
 * @method static void          group(string $prefix, callable $callback, array $middlewares = []) Groups routes under a common prefix with optional middleware.
 * @method static void          render(string $file, array $data = [], string $key = null, string $layout = '') Renders a template file.
 * @method static void          error(\Throwable $e)                                              Sends an HTTP 500 response.
 * @method static void          notFound()                                                        Sends an HTTP 404 response.
 * @method static void          json(mixed $data, int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0) Sends a JSON response.
 * @method static void          jsonp(mixed $data, string $param = 'jsonp', int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0) Sends a JSONP response.
 * @method static void          map(string $name, callable $callback)                             Creates a custom framework method.
 * @method static void          register(string $name, string $class, array $params = [], callable $callback = null) Registers a class to a framework method.
 * @method static void          before(string $name, callable $callback)                          Adds a before-filter on a framework method.
 * @method static void          after(string $name, callable $callback)                           Adds an after-filter on a framework method.
 * @method static mixed         get(string $key)                                                  Gets an engine variable.
 * @method static void          set(string $key, mixed $value)                                    Sets an engine variable.
 * @method static bool          has(string $key)                                                  Checks if an engine variable is set.
 * @method static void          clear(string $key = null)                                         Clears an engine variable (or all variables).
 * @method static void          path(string $path)                                                Adds a path for class autoloading.
 * @method static void          log(string $msg, array $context = [], string $level = 'DEBUG', bool $wf = false) Write a log entry.
 */
class PlumePHP
{
    /**
     * Framework engine.
     *
     * @var PlumeEngine
     */
    private static $engine;

    // Don't allow object instantiation
    private function __construct()
    {
    }

    private function __destruct()
    {
    }

    private function __clone()
    {
    }

    /**
     * Handles calls to static methods.
     *
     * Fast path: if the method exists directly on PlumeEngine and has no
     * before/after filters registered, call it without going through the
     * event dispatcher overhead.
     *
     * @param string $name   Method name
     * @param array  $params Method parameters
     *
     * @throws \Exception
     *
     * @return mixed Callback results
     */
    public static function __callStatic(string $name, array $params)
    {
        $engine = self::app();
        if (method_exists($engine, $name) && !$engine->getDispatcher()->hasFilters($name)) {
            return empty($params) ? $engine->{$name}() : $engine->{$name}(...$params);
        }
        return PlumeEvent::invokeMethod([$engine, $name], $params);
    }

    /**
     * @return PlumeEngine Application instance
     */
    public static function app(): PlumeEngine
    {
        static $initialized = false;
        if (!$initialized) {
            self::$engine = new PlumeEngine();
            $initialized = true;
        }

        return self::$engine;
    }

    /**
     * Resets per-request state for persistent worker processes
     * (FrankenPHP worker mode, RoadRunner, etc.).
     *
     * Clears the router, loader instances, dispatcher filters, and engine vars,
     * then re-applies framework defaults — without re-running boot(), so config,
     * timezone, and environment are preserved across requests.
     *
     * Usage in a FrankenPHP worker entry point:
     *
     *   while (frankenphp_handle_request(function () {
     *       PlumePHP::resetForWorker();
     *       PlumePHP::route('*', fn() => PlumePHP::app()->runAction());
     *       PlumePHP::start();
     *   }));
     */
    public static function resetForWorker(): void
    {
        $engine = self::app();

        // Preserve vars set by boot() that init() does not restore.
        $preserved = [
            'plumephp.env'            => $engine->get('plumephp.env'),
            'plumephp.default.module' => $engine->get('plumephp.default.module'),
        ];

        // Re-run init(): resets vars, loader instances, dispatcher, and
        // re-registers default components/methods. boot() is skipped because
        // Engine::init() guards it with a static $initialized flag.
        $engine->init();

        // Restore boot-time vars that were cleared by init()'s vars reset.
        foreach ($preserved as $key => $value) {
            $engine->set($key, $value);
        }

        // Restore C() config to boot-time snapshot, discarding any per-request
        // mutations written by application code during the previous request.
        C("\x00snapshot_restore\x00");
    }
}
