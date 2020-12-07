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
    $args = func_num_args();
    if (1 === $args) {
        if (is_string($key)) {
            // Up to three layers
            $names = explode('.', $key, 3);
            $countNames = count($names);
            if (1 === $countNames) {
                return isset($_config[$key]) ? $_config[$key] : null;
            }
            if (2 === $countNames) {
                $key1 = $names[0];
                $key2 = $names[1];

                return isset($_config[$key1][$key2])
                    ? $_config[$key1][$key2] : null;
            }
            $key1 = $names[0];
            $key2 = $names[1];
            $key3 = $names[2];

            return isset($_config[$key1][$key2][$key3])
                ? $_config[$key1][$key2][$key3] : null;
        }

        if (is_array($key)) {
            if (array_keys($key) !== range(0, count($key) - 1)) {
                $_config = array_merge($_config, $key);
            } else {
                $ret = [];
                foreach ($key as $k) {
                    $ret[$k] = isset($_config[$k]) ? $_config[$k] : null;
                }

                return $ret;
            }
        }
    } else {
        if (is_string($key)) {
            $_config[$key] = $value;
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

/**
 * The PlumePHP class is a static representation of the framework.
 *
 * Core.
 *
 * @method static app()                                                             Gets the application object instance
 * @method static start()                                                           Starts the framework.
 * @method static path($path)                                                       Adds a path for autoloading classes.
 * @method static stop()                                                            Stops the framework and sends a response.
 * @method static halt($code = 200, $message = '')                                  Stop the framework with an optional status code and message.
 * @method static route($pattern, $callback)                                        Maps a URL pattern to a callback.
 * @method static render($file, [$data], [$key], [$layout])                         Renders a template file.
 * @method static error($exception)                                                 Sends an HTTP 500 response.
 * @method static notFound()                                                        Sends an HTTP 404 response.
 * @method static json($data, [$code], [$encode], [$charset], [$option])            Sends a JSON response.
 * @method static jsonp($data, [$param], [$code], [$encode], [$charset], [$option]) Sends a JSONP response.
 * @method static map($name, $callback)                                             Creates a custom framework method.
 * @method static register($name, $class, [$params], [$callback])                   Registers a class to a framework method.
 * @method static before($name, $callback)                                          Adds a filter before a framework method.
 * @method static after($name, $callback)                                           Adds a filter after a framework method.
 * @method static get($key)                                                         Gets a variable.
 * @method static set($key, $value)                                                 Sets a variable.
 * @method static has($key)                                                         Checks if a variable is set.
 * @method static clear([$key])                                                     Clears a variable.
 * @method static log($msg, array $context = [], $level = 'DEBUG', $wf = false)     logging.
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
     * @param string $name   Method name
     * @param array  $params Method parameters
     *
     * @throws \Exception
     *
     * @return mixed Callback results
     */
    public static function __callStatic(string $name, array $params)
    {
        return PlumeEvent::invokeMethod([self::app(), $name], $params);
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
}
/**
 * The Collection class allows you to access a set of data
 * using both array and object notation.
 */
class PlumeCollection implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable
{
    /**
     * Collection data.
     *
     * @var array
     */
    private $data;

    /**
     * Constructor.
     *
     * @param array $data Initial data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Gets an item.
     *
     * @param string $key Key
     *
     * @return mixed Value
     */
    public function __get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * Set an item.
     *
     * @param string $key   Key
     * @param mixed  $value Value
     */
    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Checks if an item exists.
     *
     * @param string $key Key
     *
     * @return bool Item status
     */
    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Removes an item.
     *
     * @param string $key Key
     */
    public function __unset($key)
    {
        unset($this->data[$key]);
    }

    /**
     * Gets an item at the offset.
     *
     * @param string $offset Offset
     *
     * @return mixed Value
     */
    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    /**
     * Sets an item at the offset.
     *
     * @param string $offset Offset
     * @param mixed  $value  Value
     */
    public function offsetSet($offset, $value)
    {
        if (null === $offset) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Checks if an item exists at the offset.
     *
     * @param string $offset Offset
     *
     * @return bool Item status
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * Removes an item at the offset.
     *
     * @param string $offset Offset
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    /**
     * Resets the collection.
     */
    public function rewind()
    {
        reset($this->data);
    }

    /**
     * Gets current collection item.
     *
     * @return mixed Value
     */
    public function current()
    {
        return current($this->data);
    }

    /**
     * Gets current collection key.
     *
     * @return mixed Value
     */
    public function key()
    {
        return key($this->data);
    }

    /**
     * Gets the next collection value.
     *
     * @return mixed Value
     */
    public function next()
    {
        return next($this->data);
    }

    /**
     * Checks if the current collection key is valid.
     *
     * @return bool Key status
     */
    public function valid(): bool
    {
        $key = key($this->data);

        return null !== $key && false !== $key;
    }

    /**
     * Gets the size of the collection.
     *
     * @return int Collection size
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Gets the item keys.
     *
     * @return array Collection keys
     */
    public function keys()
    {
        return array_keys($this->data);
    }

    /**
     * Gets the collection data.
     *
     * @return array Collection data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Sets the collection data.
     *
     * @param array $data New collection data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * Gets the collection data which can be serialized to JSON.
     *
     * @return array Collection data which can be serialized by <b>json_encode</b>
     */
    public function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * Removes all items from the collection.
     */
    public function clear()
    {
        $this->data = [];
    }

    /**
     * Transfers to the array.
     */
    public function toArray(): array
    {
        $collection = $this->data;
        foreach ($collection as $key => $item) {
            if (!$item instanceof PlumeCollection) {
                continue;
            }
            $collection[$key] = $item->toArray();
        }

        return $collection;
    }
}
/**
 * The PlumeLoader class is responsible for loading objects. It maintains
 * a list of reusable class instances and can generate a new class
 * instances with custom initialization parameters. It also performs
 * class autoloading.
 */
class PlumeLoader
{
    /**
     * Registered classes.
     *
     * @var array
     */
    protected $classes = [];

    /**
     * Class instances.
     *
     * @var array
     */
    protected $instances = [];

    /**
     * Autoload directories.
     *
     * @var array
     */
    protected static $dirs = [];

    public function __construct()
    {
        // composer autoload
        if (file_exists(VENDOR_PATH.DS.'autoload.php')) {
            $name = 'composer';
            $class = 'Composer';
            $this->instances[$name] = include VENDOR_PATH.DS.'autoload.php';
            $this->classes[$name] = [$class, [], null];
        }

        self::autoload(true);
    }

    /**
     * Registers a class.
     *
     * @param string          $name     Registry name
     * @param callable|string $class    Class name or function to instantiate class
     * @param array           $params   Class initialization parameters
     * @param callback        $callback Function to call after object instantiation
     */
    public function register(string $name, $class, array $params = [], callable $callback = null)
    {
        unset($this->instances[$name]);
        $this->classes[$name] = [$class, $params, $callback];
    }

    /**
     * Unregister a class.
     *
     * @param string $name Registry name
     */
    public function unregister(string $name)
    {
        unset($this->classes[$name]);
    }

    /**
     * Loads a registered class.
     *
     * @param string $name   Method name
     * @param bool   $shared Shared instance
     *
     * @throws \Exception
     *
     * @return object Class instance
     */
    public function load(string $name, bool $shared = true)
    {
        $obj = null;
        if (isset($this->classes[$name])) {
            list($class, $params, $callback) = $this->classes[$name];
            $exists = isset($this->instances[$name]);
            if ($shared) {
                $obj = ($exists) ? $this->getInstance($name) : $this->newInstance($class, $params);
                if (!$exists) {
                    $this->instances[$name] = $obj;
                }
            } else {
                $obj = $this->newInstance($class, $params);
            }

            if ($callback && (!$shared || !$exists)) {
                $ref = [&$obj];
                call_user_func_array($callback, $ref);
            }
        }

        return $obj;
    }

    /**
     * Gets a single instance of a class.
     *
     * @param string $name Instance name
     *
     * @return object Class instance
     */
    public function getInstance(string $name)
    {
        return isset($this->instances[$name]) ? $this->instances[$name] : null;
    }

    /**
     * Gets a new instance of a class.
     *
     * @param callable|string $class  Class name or callback function to instantiate class
     * @param array           $params Class initialization parameters
     *
     * @throws \Exception
     *
     * @return object Class instance
     */
    public function newInstance($class, array $params = [])
    {
        if (is_callable($class)) {
            return call_user_func_array($class, $params);
        }

        switch (count($params)) {
        case 0:
            return new $class();
        case 1:
            return new $class($params[0]);
        case 2:
            return new $class($params[0], $params[1]);
        case 3:
            return new $class($params[0], $params[1], $params[2]);
        case 4:
            return new $class($params[0], $params[1], $params[2], $params[3]);
        case 5:
            return new $class($params[0], $params[1], $params[2], $params[3], $params[4]);
        default:
            try {
                $refClass = new \ReflectionClass($class);

                return $refClass->newInstanceArgs($params);
            } catch (\ReflectionException $e) {
                throw new \Exception("Cannot instantiate {$class}", 0, $e);
            }
        }
    }

    /**
     * @param string $name Registry name
     *
     * @return mixed Class information or null if not registered
     */
    public function get(string $name)
    {
        return isset($this->classes[$name]) ? $this->classes[$name] : null;
    }

    /**
     * Resets the object to the initial state.
     */
    public function reset()
    {
        $this->classes = [];
        $this->instances = [];
    }

    // Autoloading Functions

    /**
     * Starts/stops autoloader.
     *
     * @param bool  $enabled Enable/disable autoloading
     * @param array $dirs    Autoload directories
     */
    public static function autoload(bool $enabled = true, $dirs = [])
    {
        if (!$enabled) {
            spl_autoload_unregister([__CLASS__, 'loadClass']);

            return;
        }

        spl_autoload_register([__CLASS__, 'loadClass']);
        if (!empty($dirs)) {
            self::addDirectory($dirs);
        }
    }

    /**
     * Autoloads classes.
     *
     * @param string $class Class name
     */
    public static function loadClass(string $class)
    {
        $classFile = str_replace(['\\', '_'], '/', $class).'.php';
        foreach (self::$dirs as $dir) {
            $file = $dir.'/'.$classFile;
            if (file_exists($file)) {
                require $file;

                return;
            }
        }
    }

    /**
     * Adds a directory for autoloading classes.
     *
     * @param mixed $dir Directory path
     */
    public static function addDirectory($dir)
    {
        if (is_array($dir) || is_object($dir)) {
            foreach ($dir as $value) {
                self::addDirectory($value);
            }
        } elseif (is_string($dir)) {
            if (!in_array($dir, self::$dirs, true)) {
                self::$dirs[] = $dir;
            }
        }
    }
}
/**
 * The PlumeRoute class is responsible for routing an HTTP request to
 * an assigned callback function. The PlumeRouter tries to match the
 * requested URL against a series of URL patterns.
 */
class PlumeRoute
{
    /**
     * @var string URL pattern
     */
    public $pattern;

    /**
     * @var mixed Callback function
     */
    public $callback;

    /**
     * @var array HTTP methods
     */
    public $methods = [];

    /**
     * @var array Route parameters
     */
    public $params = [];

    /**
     * @var string Matching regular expression
     */
    public $regex;

    /**
     * @var string URL splat content
     */
    public $splat = '';

    /**
     * @var bool Pass self in callback parameters
     */
    public $pass = false;

    /**
     * Constructor.
     *
     * @param string $pattern  URL pattern
     * @param mixed  $callback Callback function
     * @param array  $methods  HTTP methods
     * @param bool   $pass     Pass self in callback parameters
     */
    public function __construct(string $pattern, callable $callback, array $methods, bool $pass)
    {
        $this->pattern = $pattern;
        $this->callback = $callback;
        $this->methods = $methods;
        $this->pass = $pass;
    }

    /**
     * Checks if a URL matches the route pattern. Also parses named parameters in the URL.
     *
     * @param string $url            Requested URL
     * @param bool   $case_sensitive Case sensitive matching
     *
     * @return bool Match status
     */
    public function matchUrl(string $url, bool $case_sensitive = false): bool
    {
        // Wildcard or exact match
        if ('*' === $this->pattern || $this->pattern === $url) {
            return true;
        }

        $ids = [];
        $last_char = substr($this->pattern, -1);
        // Get splat
        if ('*' === $last_char) {
            $n = 0;
            $len = strlen($url);
            $count = substr_count($this->pattern, '/');
            for ($i = 0; $i < $len; $i++) {
                if ('/' === $url[$i]) {
                    $n++;
                }
                if ($n === $count) {
                    break;
                }
            }

            $this->splat = (string) substr($url, $i + 1);
        }

        // Build the regex for matching
        $regex = str_replace([')', '/*'], [')?', '(/?|/.*?)'], $this->pattern);
        $regex = preg_replace_callback(
            '#@([\w]+)(:([^/\(\)]*))?#',
            function ($matches) use (&$ids) {
                $ids[$matches[1]] = null;
                if (isset($matches[3])) {
                    return '(?P<'.$matches[1].'>'.$matches[3].')';
                }

                return '(?P<'.$matches[1].'>[^/\?]+)';
            },
            $regex
        );

        // Fix trailing slash
        if ('/' === $last_char) {
            $regex .= '?';
        } else {
            // Allow trailing slash
            $regex .= '/?';
        }

        // Attempt to match route and named parameters
        if (preg_match('#^'.$regex.'(?:\?.*)?$#'.(($case_sensitive) ? '' : 'i'), $url, $matches)) {
            foreach ($ids as $k => $v) {
                $this->params[$k] = (array_key_exists($k, $matches))
                    ? urldecode($matches[$k]) : null;
            }
            $this->regex = $regex;

            return true;
        }

        return false;
    }

    /**
     * Checks if an HTTP method matches the route methods.
     *
     * @param string $method HTTP method
     *
     * @return bool Match status
     */
    public function matchMethod(string $method): bool
    {
        return count(array_intersect([$method, '*'], $this->methods)) > 0;
    }
}
/**
 * The PlumeRouter class is responsible for routing an HTTP request to
 * an assigned callback function. The PlumeRouter tries to match the
 * requested URL against a series of URL patterns.
 */
class PlumeRouter
{
    /**
     * Case sensitive matching.
     *
     * @var bool
     */
    public $case_sensitive = false;
    /**
     * Mapped routes.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * Pointer to current route.
     *
     * @var int
     */
    protected $index = 0;

    /**
     * Gets mapped routes.
     *
     * @return array Array of routes
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Clears all routes in the router.
     */
    public function clear()
    {
        $this->routes = [];
    }

    /**
     * Maps a URL pattern to a callback function.
     *
     * @param string   $pattern    URL pattern to match
     * @param callable $callback   Callback function
     * @param bool     $pass_route Pass the matching route object to the callback
     */
    public function map(string $pattern, callable $callback, bool $pass_route = false)
    {
        $url = $pattern;
        $methods = ['*'];
        if (false !== strpos($pattern, ' ')) {
            list($method, $url) = explode(' ', trim($pattern), 2);
            $url = trim($url);
            $methods = explode('|', $method);
        }

        $this->routes[] = new PlumeRoute($url, $callback, $methods, $pass_route);
    }

    /**
     * Routes the current request.
     *
     * @param PlumeRequest $request PlumeRequest object
     *
     * @return bool|PlumeRoute Matching route or false if no match
     */
    public function route(PlumeRequest $request)
    {
        while ($route = $this->current()) {
            if (false !== $route && $route->matchMethod($request->method)
                && $route->matchUrl($request->url, $this->case_sensitive)) {
                return $route;
            }
            $this->next();
        }

        return false;
    }

    /**
     * Gets the current route.
     *
     * @return PlumeRoute
     */
    public function current()
    {
        return isset($this->routes[$this->index])
            ? $this->routes[$this->index]
            : false;
    }

    /**
     * Gets the next route.
     *
     * @return PlumeRoute
     */
    public function next()
    {
        $this->index++;
    }

    /**
     * Reset to the first route.
     */
    public function reset()
    {
        $this->index = 0;
    }
}
/**
 * The PlumeView class represents output to be displayed. It provides
 * methods for managing view data and inserts the data into view templates upon rendering.
 */
class PlumeView
{
    /**
     * Location of view templates.
     *
     * @var string
     */
    public $path;

    /**
     * File extension.
     *
     * @var string
     */
    public $extension = '.tpl.php';

    /**
     * Theme.
     *
     * @var string
     */
    public $theme = 'default';

    /**
     * View variables.
     *
     * @var array
     */
    protected $vars = [];

    /**
     * Template file.
     *
     * @var string
     */
    private $template;

    /**
     * Constructor.
     *
     * @param string $path Path to templates directory
     */
    public function __construct(string $path = '.')
    {
        $this->path = $path;
    }

    /**
     * Gets a template variable.
     *
     * @param string $key Key
     *
     * @return mixed Value
     */
    public function get(string $key)
    {
        return isset($this->vars[$key]) ? $this->vars[$key] : null;
    }

    /**
     * Sets a template variable.
     *
     * @param mixed $key   Key
     * @param mixed $value Value
     */
    public function set($key, $value = null)
    {
        if (is_array($key) || is_object($key)) {
            foreach ($key as $k => $v) {
                $this->vars[$k] = $v;
            }
        } else {
            $this->vars[$key] = $value;
        }
    }

    /**
     * Checks if a template variable is set.
     *
     * @param string $key Key
     *
     * @return bool If key exists
     */
    public function has(string $key): bool
    {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a template variable. If no key is passed in, clear all variables.
     *
     * @param string $key Key
     */
    public function clear($key = null)
    {
        if (null === $key) {
            $this->vars = [];
        } else {
            unset($this->vars[$key]);
        }
    }

    /**
     * Renders a template.
     *
     * @param string $file   Template file
     * @param array  $data   Template data
     * @param string $layout layout file
     *
     * @throws \Exception If template not found
     */
    public function render(string $file, array $data = [], string $layout = 'layout')
    {
        $this->content = $this->getTemplate($file);
        if (!file_exists($this->content)) {
            throw new \Exception("Template file not found: {$this->content}.");
        }

        if ($data) {
            $this->vars = array_merge($this->vars, $data);
        }

        extract($this->vars);
        if ('' === $layout) {
            include $this->content;
        } else {
            $layoutFile = $this->path.DS.$layout.$this->extension;
            if (!file_exists($layoutFile)) {
                throw new \Exception("Layout file not found: {$layoutFile}.");
            }

            include $layoutFile;
        }
    }

    /**
     * Gets the output of a template.
     *
     * @param string $file   Template file
     * @param array  $data   Template data
     * @param string $layout layout file, default false
     *
     * @return string Output of template
     */
    public function fetch(string $file, array $data = [], string $layout = ''): string
    {
        ob_start();

        $this->render($file, $data, $layout);
        $output = ob_get_clean();

        return $output;
    }

    /**
     * Checks if a template file exists.
     *
     * @param string $file Template file
     *
     * @return bool Template file exists
     */
    public function exists(string $file): bool
    {
        return file_exists($this->getTemplate($file));
    }

    /**
     * Gets the full path to a template file.
     * E.g.:
     * // in app settings files
     * PlumePHP::set('theme.path', '/home/myrootfolder/public/themes/current_theme').
     *
     * PlumePHP::render('theme.path::myview', $params);
     *
     * @param string $file Template file with prefix
     *
     * @return string Template file location
     */
    public function getTemplate(string $file): string
    {
        $ext = $this->extension;
        if (!empty($ext) && (substr($file, -1 * strlen($ext)) !== $ext)) {
            $file .= $ext;
        }

        $parts = explode('::', $file);
        if (2 === count($parts)) {
            $base_path_key = $parts[0];
            $file_path = $parts[1];

            return rtrim(PlumePHP::get($base_path_key), '/').'/'.$file_path;
        }

        if (('/' === substr($file, 0, 1))) {
            return $file;
        }

        return $this->path.DS.$file;
    }

    /**
     * Displays escaped output.
     *
     * @param string $str String to escape
     *
     * @return string Escaped string
     */
    public function e(string $str)
    {
        echo htmlentities($str);
    }
}
/**
 * The plume engine.
 */
class PlumeEngine
{
    /**
     * Stored variables.
     *
     * @var array
     */
    protected $vars;

    /**
     * Class loader.
     *
     * @var PlumeLoader
     */
    protected $loader;

    /**
     * Event dispatcher.
     *
     * @var PlumeEvent
     */
    protected $dispatcher;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->vars = [];
        $this->loader = new PlumeLoader();
        $this->dispatcher = new PlumeEvent();
        $this->init();
    }

    /**
     * Handles calls to class methods.
     *
     * @param string $name   Method name
     * @param array  $params Method parameters
     *
     * @throws \Exception
     *
     * @return mixed Callback results
     */
    public function __call(string $name, array $params)
    {
        $callback = $this->dispatcher->get($name);
        if ($callback && is_callable($callback)) {
            return $this->dispatcher->run($name, $params);
        }

        if (!$this->loader->get($name)) {
            throw new \Exception("{$name} must be a mapped method.");
        }

        $shared = (!empty($params)) ? (bool) $params[0] : true;

        return $this->loader->load($name, $shared);
    }

    // Core Methods

    /**
     * Initializes the framework.
     */
    public function init()
    {
        static $initialized = false;
        $self = $this;

        if ($initialized) {
            $this->vars = [];
            $this->loader->reset();
            $this->dispatcher->reset();
        }

        // Register default components
        $this->loader->register('request', 'PlumeRequest');
        $this->loader->register('response', 'PlumeResponse');
        $this->loader->register('view', 'PlumeView', [], function ($view) use ($self) {
            $view->path = $self->get('plumephp.views.path');
            $view->extension = $self->get('plumephp.views.extension');
        });
        $this->loader->register('router', 'PlumeRouter');
        $this->loader->register('logger', 'PlumeLogger');

        // Register framework methods
        $methods = [
            'start', 'stop', 'route', 'halt', 'error', 'notFound',
            'render', 'json', 'jsonp', 'log', 'biz',
        ];
        foreach ($methods as $name) {
            $this->dispatcher->set($name, [$this, '_'.$name]);
        }

        // Default configuration settings
        $this->set('plumephp.case_sensitive', false);
        $this->set('plumephp.handle_errors', true);
        $this->set('plumephp.log_errors', true);
        $this->set('plumephp.base_url', null);
        $this->set('plumephp.views.path', './views');
        $this->set('plumephp.views.extension', '.tpl.php');

        // Startup configuration
        $this->before('start', function () use ($self) {
            // Enable error handling
            if ($self->get('plumephp.handle_errors')) {
                set_error_handler([$self, 'handleError']);
                set_exception_handler([$self, 'handleException']);
            }
            // Set case-sensitivity
            $self->router()->case_sensitive = $self->get('plumephp.case_sensitive');
            if (IS_CLI) {
                // define STDIN, STDOUT and STDERR if the PHP SAPI did not define them
                // (e.g. creating console application in web env)
                // http://php.net/manual/en/features.commandline.io-streams.php
                defined('STDIN') or define('STDIN', fopen('php://stdin', 'rb'));
                defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'wb'));
                defined('STDERR') or define('STDERR', fopen('php://stderr', 'wb'));

                try {
                    PlumeCmdService::runQuickly(['path'=>PLUME_PHP_PATH]);
                    exit(0);
                } catch (Exception $e) {
                    $self->_halt(500, $e->getMessage());
                }
            }
        });

        if (!$initialized) {
            $this->boot();
        }

        $initialized = true;
    }

    /**
     * Custom error handler. Converts errors into exceptions.
     *
     * @param int    $errno   Error number
     * @param string $errstr  Error string
     * @param string $errfile Error file name
     * @param int    $errline Error file line number
     *
     * @throws \ErrorException
     */
    public function handleError($errno, $errstr, $errfile, $errline)
    {
        if ($errno & error_reporting()) {
            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        }
    }

    /**
     * Custom exception handler. Logs exceptions.
     *
     * @param \Exception $e Thrown exception
     */
    public function handleException($e)
    {
        if ($this->get('plumephp.log_errors')) {
            error_log($e->getMessage());
        }
        $this->_error($e);
    }

    /**
     * Maps a callback to a framework method.
     *
     * @param string   $name     Method name
     * @param callback $callback Callback function
     *
     * @throws \Exception If trying to map over a framework method
     */
    public function map(string $name, $callback)
    {
        if (method_exists($this, $name)) {
            throw new \Exception('Cannot override an existing framework method.');
        }
        $this->dispatcher->set($name, $callback);
    }

    /**
     * Registers a class to a framework method.
     *
     * @param string   $name     Method name
     * @param string   $class    Class name
     * @param array    $params   Class initialization parameters
     * @param callback $callback Function to call after object instantiation
     *
     * @throws \Exception If trying to map over a framework method
     */
    public function register(string $name, string $class, array $params = [], $callback = null)
    {
        if (method_exists($this, $name)) {
            throw new \Exception('Cannot override an existing framework method.');
        }

        $this->loader->register($name, $class, $params, $callback);
    }

    /**
     * Adds a pre-filter to a method.
     *
     * @param string   $name     Method name
     * @param callback $callback Callback function
     */
    public function before(string $name, $callback)
    {
        $this->dispatcher->hook($name, 'before', $callback);
    }

    /**
     * Adds a post-filter to a method.
     *
     * @param string   $name     Method name
     * @param callback $callback Callback function
     */
    public function after(string $name, $callback)
    {
        $this->dispatcher->hook($name, 'after', $callback);
    }

    /**
     * Gets a variable.
     *
     * @param string $key Key
     *
     * @return mixed
     */
    public function get(string $key = null)
    {
        if (null === $key) {
            return $this->vars;
        }

        return isset($this->vars[$key]) ? $this->vars[$key] : null;
    }

    /**
     * Sets a variable.
     *
     * @param mixed  $key   Key
     * @param string $value Value
     */
    public function set($key, $value = null)
    {
        if (is_array($key) || is_object($key)) {
            foreach ($key as $k => $v) {
                $this->vars[$k] = $v;
            }
        } else {
            $this->vars[$key] = $value;
        }
    }

    /**
     * Checks if a variable has been set.
     *
     * @param string $key Key
     *
     * @return bool Variable status
     */
    public function has(string $key): bool
    {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a variable. If no key is passed in, clear all variables.
     *
     * @param string $key Key
     */
    public function clear(string $key = null)
    {
        if (null === $key) {
            $this->vars = [];
        } else {
            unset($this->vars[$key]);
        }
    }

    /**
     * Adds a path for class autoloading.
     *
     * @param string $dir Directory path
     */
    public function path(string $dir)
    {
        $this->loader->addDirectory($dir);
    }

    // Extensible Methods

    /**
     * Starts the framework engine.
     *
     * @throws \Exception
     */
    public function _start()
    {
        $dispatched = false;
        $self = $this;

        // Allow filters to run
        $this->after('start', function () use ($self) {
            $self->stop();
        });

        $request = $this->request();
        $response = $this->response();
        $router = $this->router();
        // Flush any existing output
        if (ob_get_length() > 0) {
            $response->write(ob_get_clean());
        }

        // Enable output buffering
        ob_start();

        // Route the request
        while ($route = $router->route($request)) {
            $params = array_values($route->params);
            // Add route info to the parameter list
            if ($route->pass) {
                $params[] = $route;
            }

            // Call route handler
            $continue = $this->dispatcher->execute(
                $route->callback,
                $params
            );

            $dispatched = true;
            if (!$continue) {
                break;
            }

            $router->next();
            $dispatched = false;
        }

        if (!$dispatched) {
            $this->notFound();
        }
    }

    /**
     * Stops the framework and outputs the current response.
     *
     * @param int $code HTTP status code
     *
     * @throws \Exception
     */
    public function _stop(int $code = null)
    {
        $response = $this->response();
        if (!$response->sent()) {
            if (null !== $code) {
                $response->status($code);
            }

            $data = ob_get_clean();
            if (false !== $data) {
                $response->write($data);
            }

            $response->send();
        }
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string   $pattern    URL pattern to match
     * @param callback $callback   Callback function
     * @param bool     $pass_route Pass the matching route object to the callback
     */
    public function _route(string $pattern, callable $callback, bool $pass_route = false)
    {
        $this->router()->map($pattern, $callback, $pass_route);
    }

    /**
     * Stops processing and returns a given response.
     *
     * @param int    $code    HTTP status code
     * @param string $message Response message
     */
    public function _halt(int $code = 200, string $message = '')
    {
        if (IS_CLI) {
            echo '➤ ', date('H:i:s'), ', Msg:'.$message, PHP_EOL;
            exit(255);
        }

        $this->response()
            ->clear()
            ->status($code)
            ->write($message)
            ->send();
        exit();
    }

    /**
     * Sends an HTTP 500 response for any errors.
     *
     * @param \Exception|\Throwable $e Thrown exception
     */
    public function _error($e)
    {
        $this->_log('Msg: '.$e->getMessage().
            ', Code: '.$e->getCode().
            ', Trace: '.PHP_EOL.$e->getTraceAsString(), [], 'ERROR', true);

        if (IS_CLI) {
            return;
        }

        if ('production' === $this->get('plumephp.env')) {
            $msg = sprintf(
                '<h1>500 Internal Server Error</h1>'.
                '<h3>%s (%s)</h3>',
                $e->getMessage(),
                $e->getCode()
            );
        } else {
            $msg = sprintf(
                '<h1>500 Internal Server Error</h1>'.
                '<h3>%s (%s)</h3>'.
                '<pre>%s</pre>',
                $e->getMessage(),
                $e->getCode(),
                $e->getTraceAsString()
            );
        }

        try {
            $this->response()
                ->clear()
                ->status(500)
                ->write($msg)
                ->send();
        } catch (\Throwable $t) { // PHP 7.0+
            exit($msg);
        } catch (\Exception $e) { // PHP < 7
            exit($msg);
        }
    }

    /**
     * Sends an HTTP 404 response when a URL is not found.
     */
    public function _notFound()
    {
        $this->response()
            ->clear()
            ->status(404)
            ->write('<h1>404 Not Found</h1>'.
                    '<h3>The page you have requested could not be found.</h3>'.
                    str_repeat(' ', 512))
            ->send();
    }

    /**
     * Renders a template.
     *
     * @param string $file   Template file
     * @param array  $data   Template data
     * @param string $key    View variable name
     * @param string $layout layout file
     *
     * @throws \Exception
     */
    public function _render(string $file, array $data = null, string $key = null, string $layout = '')
    {
        if (null !== $key) {
            $this->view()->set($key, $this->view()->fetch($file, $data, $layout));
        } else {
            $this->view()->render($file, $data, false, $layout);
        }
    }

    /**
     * Sends a JSON response.
     *
     * @param mixed  $data    JSON data
     * @param int    $code    HTTP status code
     * @param bool   $encode  Whether to perform JSON encoding
     * @param string $charset Charset
     * @param int    $option  Bitmask Json constant such as JSON_HEX_QUOT
     *
     * @throws \Exception
     */
    public function _json(
        $data,
        int $code = 200,
        bool $encode = true,
        string $charset = 'utf-8',
        int $option = JSON_UNESCAPED_UNICODE
    ) {
        $json = ($encode) ? json_encode($data, $option) : $data;
        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/json; charset='.$charset)
            ->write($json)
            ->send();
    }

    /**
     * Sends a JSONP response.
     *
     * @param mixed  $data    JSON data
     * @param string $param   query parameter that specifies the callback name
     * @param int    $code    HTTP status code
     * @param bool   $encode  Whether to perform JSON encoding
     * @param string $charset Charset
     * @param int    $option  Bitmask Json constant such as JSON_HEX_QUOT
     *
     * @throws \Exception
     */
    public function _jsonp(
        $data,
        string $param = 'jsonp',
        int $code = 200,
        bool $encode = true,
        string $charset = 'utf-8',
        int $option = JSON_UNESCAPED_UNICODE
    ) {
        $json = ($encode) ? json_encode($data, $option) : $data;
        $callback = $this->request()->query[$param];
        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/javascript; charset='.$charset)
            ->write($callback.'('.$json.');')
            ->send();
    }

    /**
     * Business invocation.
     *
     * @return mixed
     */
    public function _biz(array $params = [])
    {
        $startTime = microtime(true);
        $ar = new PlumeParam($params);
        L('[biz]request: '.$ar);
        if (!$ar->has('path') || !$ar->path) {
            throw new \Exception('Wrong parameter format, missing path');
        }

        // Special character processing
        $bizPath = str_replace('..', '', $ar->path);
        $bizPath = str_replace('/', '', $bizPath);
        $bizPath = str_replace('\\', '', $bizPath);
        $names = explode('.', $bizPath, 20);
        $count = count($names);
        if ($count < 2) {
            throw new \Exception('Wrong parameter format, missing path or function name, biz path: '.$bizPath);
        }

        // The first is the module name
        $module = $names[0];
        if (!file_exists(APP_PATH.DS.$module)) {
            $module = PlumePHP::get('plumephp.default.module');
        } else {
            $module = array_shift($names);
        }

        // The last one is the function that needs to be called
        $func = array_pop($names);
        // Class file uses the .biz.php suffix
        $bizFile = $module.DS.'biz'.DS.implode(DS, $names).'.biz.php';
        $classFile = APP_PATH.DS.$bizFile;
        if (!file_exists($classFile)) {
            throw new \Exception('biz file not found '.$bizFile);
        }

        // Class name which uses biz_$module prefix
        $className = 'biz_'.$module.'_'.implode('_', $names);
        $ar->module = $module;
        $ar->class = $className;
        $ar->func = $func;
        $this->dispatcher->set('rpc', [$className, $func]);

        // Load the module boot file which uses the .boot.php suffix
        I(APP_PATH.DS.$module.DS.$module.'.boot.php', true);
        // Load the biz file
        require_once $classFile;

        L('[biz]class: '.$className.'::'.$func.' call start');
        $res = $this->dispatcher->run('rpc', [$ar]);
        $result = json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        L('[biz]class: '.$className.'::'.$func.' call success, cost time: '
                        .round(microtime(true) - $startTime, 3).'s'
                        .', result: '.substr($result, 0, 3000));

        // Returns the biz result
        return $res;
    }

    /**
     * Log output.
     *
     * @param string $msg     Log message
     * @param array  $context Replaces the placeholder in the record information with context
     *                        information, which is empty by default
     * @param string $level   Log level, the default is DEBUG
     * @param bool   $wf      The default is false to log in a separate wf log
     */
    public function _log(string $msg, array $context = [], string $level = 'DEBUG', bool $wf = false)
    {
        $this->logger()->write($msg, $context, $level, $wf);
    }

    /**
     * Default routing rule with unified format
     * https://your.domain.com[/module][/file][/k/v...].
     *
     * Remark：
     * 1. Module,file is the corresponding directory or file (without suffix)
     * 2. K is the parameter name, v is the parameter value, can be repeated,
     *    such as: id/2/dir/xy with 2 parameters: id=2 and dir=xy
     * 3. [] brackets indicate dispensable
     * 4. If no one matches then match with the next one
     * 5. If there is no file, the default is index.php. If there is file,
     *    the default is file.php
     */
    public function runAction()
    {
        $startTime = microtime(true);
        $requestUri = $_SERVER['REQUEST_URI'];
        $vdname = C('VDNAME');
        if (!empty($vdname)) {
            $vdname = '/'.$vdname;
            $urlPath = substr($requestUri, strlen($vdname));
        } else {
            $urlPath = $requestUri;
        }
        // Path alias
        $pathalias = C('PATH_ALIAS');
        if (is_array($pathalias)) {
            foreach ($pathalias as $k => $v) {
                if (false !== strpos($urlPath, $k)) {
                    $urlPath = str_replace($k, $v, $urlPath);

                    break;
                }
            }
        }

        // Request parameters
        $args = [];
        $pos = strpos($urlPath, '?');
        if (false !== $pos) {
            parse_str(substr($urlPath, $pos + 1), $args);
            $urlPath = substr($urlPath, 0, $pos);
        }

        $file = '';
        $module = '';
        // Prevents request addresses from being too long, up to 64
        $pathnames = explode('/', $urlPath, 64);
        // The default home page
        if (empty($pathnames) || empty($pathnames[1]) || 'index.php' === $pathnames[1]) {
            $module = $this->get('plumephp.default.module');
        } else {
            // The leftmost represents the module name
            $module = $pathnames[1];
        }

        $module = trim($module);
        $this->set('plumephp.module', $module);
        $this->set('plumephp.urlPath', $urlPath);
        // Loads the module boot file {$module}.boot.php, and each module has a startup file
        I(APP_PATH.DS.$module.DS.$module.'.boot.php');

        $filepath = APP_PATH;
        $namecount = count($pathnames);
        $index = 1;
        $preg = '/^([a-z]+)[a-z0-9_]*$/i';
        for ($index = 1; $index < $namecount; $index++) {
            $name = trim($pathnames[$index]);
            if (!empty($name) && (!preg_match($preg, $name) || strlen($name) > 15)) {
                $this->_halt(404, '!!! 404(invalid) !!! uri: '.$requestUri
                                        .', urlPath: '.$urlPath.', name: '.$name);
            }
            // default: index.php default home page
            if (1 === $index) {
                if (empty($name) || 'index.php' === $name) {
                    $file = 'index';

                    break;
                }
                $filepath .= DS.$name.DS.'actions';

                continue;
            }

            if (empty($name)) {
                if (!file_exists($filepath.DS.'index.action.php')) {
                    $this->_halt(404, '!!! 404(missing index) !!! uri: '.$requestUri
                                                .', urlPath: '.$urlPath);
                }
                $file .= DS.'index';

                break;
            }

            $sPath = $filepath.DS.$name;
            if (file_exists($sPath)) {
                $filepath .= DS.$name;
                $file .= DS.$name;

                continue;
            }

            $sPath .= '.action.php';
            if (file_exists($sPath)) {
                $file .= DS.$name;

                break;
            }
            $this->_halt(404, '!!! 404 !!! uri='.$requestUri
                                        .' parseto:'.substr($sPath, strlen(APP_PATH)));
        }

        $file = trim($file, DS);
        if (empty($file)) {
            $file = 'index';
        }

        // Loads the action file
        $actionFile = APP_PATH.DS.$module.DS.'actions'.DS.$file.'.action.php';
        if (!file_exists($actionFile)) {
            $this->_halt(404, '!!! 404(missing action file) !!! uri: '.$requestUri
                                .' action file: '.substr($actionFile, strlen(APP_PATH)));
        }

        require $actionFile;

        // Packaging residual arguments
        for ($i = $index + 1; $i < $namecount; $i += 2) {
            $k = $pathnames[$i];
            $v = null;
            if ($i + 1 < $namecount) {
                $v = $pathnames[$i + 1];
            }
            $args[$k] = $v;
        }

        $this->set('plumephp.file', $file);
        $this->set('plumephp.args', $args);
        $className = $module.'_'.str_replace(DS, '_', $file).'_action';

        L('[web]class name:'.$className
            .', args:'.json_encode($args, JSON_UNESCAPED_UNICODE)
            .', request: '.json_encode($_REQUEST, JSON_UNESCAPED_UNICODE));

        if (!class_exists($className)) {
            $this->_halt(404, '!!! 404 !!! uri='.$requestUri.' class not exist: '.$className);
        }

        $actionInstance = new $className();

        // Find the corresponding method according to the action
        if (!method_exists($actionInstance, 'run')) {
            $this->_halt(404, '!!! 404 !!! uri='.$requestUri.' no run method: '.$className);
        }

        $res = $actionInstance->run();
        L('[web]class name: {class} success, result: {result}, cost: {cost}s', [
            'class' => $className,
            'result'=> substr(json_encode($res, JSON_UNESCAPED_UNICODE), 0, 200),
            'cost'  => round(microtime(true) - $startTime, 3),
        ]);

        return $res;
    }

    /**
     * boot.
     */
    protected function boot()
    {
        // Tries to load .env file
        $envFile = PLUME_PHP_PATH.DS.'.env';
        if (!file_exists($envFile)) {
            $this->_halt(503, "The {$envFile} file is missing.");
        }

        $envVariables = parse_ini_file($envFile, false, INI_SCANNER_TYPED);
        if (isset($envVariables['PLUME_PHP_ENV'])) {
            $env = $envVariables['PLUME_PHP_ENV'];
        } else {
            $env = getenv('PLUME_PHP_ENV')
                ? getenv('PLUME_PHP_ENV')
                : get_cfg_var('plumephp.env');
        }

        switch ($env) {
        case 'development':
            error_reporting(-1);
            ini_set('display_errors', 'On');

            break;
        case 'testing':
        case 'production':
            ini_set('display_errors', 'Off');
            error_reporting(-1);

            break;
        default:
            $this->_halt(503, 'The application environment is not set correctly.');
        }

        $this->set('plumephp.env', $env);
        $this->set('plumephp.default.module', 'web');

        defined('APP_PATH') or define('APP_PATH', PLUME_PHP_PATH.DS.'application');
        if (!is_dir(APP_PATH)) {
            $this->_halt(503, 'Your application folder path does not appear to be set correctly.'
                .' Please open the following file and correct this: '
                .pathinfo(__FILE__, PATHINFO_BASENAME));
        }

        defined('CONFIG_PATH') or define('CONFIG_PATH', PLUME_PHP_PATH.DS.'config');
        defined('PUBLIC_PATH') or define('PUBLIC_PATH', PLUME_PHP_PATH.DS.'public');
        if (!IS_CLI) {
            defined('SITE_DOMAIN') or define(
                'SITE_DOMAIN',
                isset($_SERVER['HTTP_HOST']) ? strip_tags($_SERVER['HTTP_HOST']) : ''
            );
            defined('IS_GET') or define(
                'IS_GET',
                'GET' === $_SERVER['REQUEST_METHOD'] ? true : false
            );
            defined('IS_POST') or define(
                'IS_POST',
                'POST' === $_SERVER['REQUEST_METHOD'] ? true : false
            );
            defined('IS_AJAX') or define(
                'IS_AJAX',
                (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && 'xmlhttprequest' === strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])) ? true : false
            );
        }

        // Loads the global config file
        if (file_exists(CONFIG_PATH.DS.'config.php')) {
            $conf = require CONFIG_PATH.DS.'config.php';
            // Loads the environment file
            if (file_exists(CONFIG_PATH.DS.$env.'.php')) {
                $localConf = require CONFIG_PATH.DS.$env.'.php';
                $conf = array_merge($conf, $localConf);
            }

            C($conf);
            // Use to the .env file to override
            foreach ($envVariables as $key=>$val) {
                if (is_array($val)) {
                    C($val);
                } else {
                    C($key, $val);
                }
            }
        }

        // session management
        if (!IS_CLI && true === C('USE_SESSION') && PHP_SESSION_NONE === session_status()
            && !headers_sent($filename, $linenum)) {
            session_start();
        }

        // Sets timezone
        $timezone = C('TIME_ZONE');
        if (empty($timezone)) {
            $timezone = 'Asia/Shanghai';
        }
        date_default_timezone_set($timezone);

        // Loads common functions
        I(__DIR__.DS.'common.php');

        register_shutdown_function(function () {
            if ($e = error_get_last()) {
                $msg = $e['message'].' in '.$e['file'].' line '.$e['line'];
                if (IS_CLI) {
                    echo $msg, PHP_EOL;
                }
                L($msg, [], 'FATAL', true);
            }
        });
    }
}
/**
 * Secure Request object.
 */
class PlumeRequest
{
    /**
     * @var string URL being requested
     */
    public $url;

    /**
     * @var string Parent subdirectory of the URL
     */
    public $base;

    /**
     * @var string Request method (GET, POST, PUT, DELETE)
     */
    public $method;

    /**
     * @var string Referrer URL
     */
    public $referrer;

    /**
     * @var string IP address of the client
     */
    public $ip;

    /**
     * @var bool Whether the request is an AJAX request
     */
    public $ajax;

    /**
     * @var string Server protocol (http, https)
     */
    public $scheme;

    /**
     * @var string Browser information
     */
    public $user_agent;

    /**
     * @var string Content type
     */
    public $type;

    /**
     * @var int Content length
     */
    public $length;

    /**
     * @var PlumeCollection Query string parameters
     */
    public $query;

    /**
     * @var PlumeCollection Post parameters
     */
    public $data;

    /**
     * @var PlumeCollection Cookie parameters
     */
    public $cookies;

    /**
     * @var PlumeCollection Uploaded files
     */
    public $files;

    /**
     * @var bool Whether the connection is secure
     */
    public $secure;

    /**
     * @var string HTTP accept parameters
     */
    public $accept;

    /**
     * Constructor.
     *
     * @param array $config Request configuration
     */
    public function __construct(array $config = [])
    {
        // Default properties
        if (empty($config)) {
            $config = [
                'url'        => str_replace('@', '%40', self::getVar('REQUEST_URI', '/')),
                'base'       => str_replace(['\\', ' '], ['/', '%20'], dirname(self::getVar('SCRIPT_NAME'))),
                'method'     => self::getMethod(),
                'referrer'   => self::getVar('HTTP_REFERER'),
                'ip'         => self::getVar('REMOTE_ADDR'),
                'ajax'       => 'XMLHttpRequest' === self::getVar('HTTP_X_REQUESTED_WITH'),
                'scheme'     => self::getVar('SERVER_PROTOCOL', 'HTTP/1.1'),
                'user_agent' => self::getVar('HTTP_USER_AGENT'),
                'type'       => self::getVar('CONTENT_TYPE'),
                'length'     => self::getVar('CONTENT_LENGTH', 0),
                'query'      => new PlumeCollection($_GET),
                'data'       => new PlumeCollection($_POST),
                'cookies'    => new PlumeCollection($_COOKIE),
                'files'      => new PlumeCollection($_FILES),
                'secure'     => 'off' !== self::getVar('HTTPS', 'off'),
                'accept'     => self::getVar('HTTP_ACCEPT'),
            ];
        }

        $this->init($config);
    }

    /**
     * Initialize request properties.
     *
     * @param array $properties Array of request properties
     */
    public function init(array $properties = [])
    {
        // Set all the defined properties
        foreach ($properties as $name => $value) {
            $this->{$name} = $value;
        }

        // Get the requested URL without the base directory
        if ('/' !== $this->base && strlen($this->base) > 0 && 0 === strpos($this->url, $this->base)) {
            $this->url = substr($this->url, strlen($this->base));
        }

        // Default url
        if (empty($this->url)) {
            $this->url = '/';
        } else {
            // Merge URL query parameters with $_GET
            $_GET += self::parseQuery($this->url);
            $this->query->setData($_GET);
        }

        // Check for JSON input
        if (0 === strpos($this->type, 'application/json')) {
            $body = $this->getBody();
            if ('' !== $body) {
                $data = json_decode($body, true);
                if (null !== $data) {
                    $this->data->setData($data);
                }
            }
        }
    }

    /**
     * Gets the body of the request.
     *
     * @return string Raw HTTP request body
     */
    public static function getBody(): string
    {
        static $body;
        if (null !== $body) {
            return $body;
        }

        $method = self::getMethod();
        if ('POST' === $method || 'PUT' === $method || 'PATCH' === $method) {
            $body = file_get_contents('php://input') ?? '';
        }

        return $body ?? '';
    }

    /**
     * Gets the request method.
     */
    public static function getMethod(): string
    {
        $method = self::getVar('REQUEST_METHOD', 'GET');
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        } elseif (isset($_REQUEST['_method'])) {
            $method = $_REQUEST['_method'];
        }

        return strtoupper($method);
    }

    /**
     * Gets a variable from $_SERVER using $default if not provided.
     *
     * @param string $var     Variable name
     * @param string $default Default value to substitute
     *
     * @return string Server variable value
     */
    public static function getVar(string $var, $default = '')
    {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : $default;
    }

    /**
     * Parse query parameters from a URL.
     *
     * @param string $url URL string
     *
     * @return array Query parameters
     */
    public static function parseQuery(string $url): array
    {
        $params = [];
        $args = parse_url($url);
        if (isset($args['query'])) {
            parse_str($args['query'], $params);
        }

        return $params;
    }

    /**
     * 通关ua判断是否为手机.
     */
    public function isMobile(): bool
    {
        //正则表达式,批配不同手机浏览器UA关键词。
        $regex_match = '/(nokia|iphone|android|motorola|^mot\\-|softbank|foma|docomo|kddi|up\\.browser|up\\.link|';
        $regex_match .= 'htc|dopod|blazer|netfront|helio|hosin|huawei|novarra|CoolPad|webos|techfaith|palmsource|';
        $regex_match .= 'blackberry|alcatel|amoi|ktouch|nexian|samsung|^sam\\-|s[cg]h|^lge|ericsson|philips|sagem|wellcom|bunjalloo|maui|';
        $regex_match .= 'symbian|smartphone|midp|wap|phone|windows ce|iemobile|^spice|^bird|^zte\\-|longcos|pantech|gionee|^sie\\-|portalmmm|';
        $regex_match .= 'jig\\s browser|hiptop|^ucweb|^benq|haier|^lct|opera\\s*mobi|opera\\*mini|320×320|240×320|176×220';
        $regex_match .= '|mqqbrowser|juc|iuc|ios|ipad';
        $regex_match .= ')/i';

        return isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])
                        or preg_match($regex_match, strtolower($_SERVER['HTTP_USER_AGENT']));
    }
}
/**
 * The PlumeResponse class represents an HTTP response. The object
 * contains the response headers, HTTP status code, and response
 * body.
 */
class PlumeResponse
{
    public $etag = false;

    /**
     * @var array HTTP status codes
     */
    public static $codes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',

        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',

        226 => 'IM Used',

        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',

        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',

        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',

        426 => 'Upgrade Required',

        428 => 'Precondition Required',
        429 => 'Too Many Requests',

        431 => 'Request Header Fields Too Large',

        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',

        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];
    /**
     * @var int HTTP status
     */
    protected $status = 200;

    /**
     * @var array HTTP headers
     */
    protected $headers = [];

    /**
     * @var string HTTP response body
     */
    protected $body;

    /**
     * @var bool HTTP response sent
     */
    protected $sent = false;

    /**
     * Sets the HTTP status of the response.
     *
     * @param int $code HTTP status code
     *
     * @throws \Exception If invalid status code
     *
     * @return int|object Self reference
     */
    public function status(int $code = null)
    {
        if (null === $code) {
            return $this->status;
        }

        if (array_key_exists($code, self::$codes)) {
            $this->status = $code;
        } else {
            throw new \Exception('Invalid status code.');
        }

        return $this;
    }

    /**
     * Adds a header to the response.
     *
     * @param array|string $name  Header name or array of names and values
     * @param string       $value Header value
     *
     * @return object Self reference
     */
    public function header($name, string $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->headers[$k] = $v;
            }
        } else {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * Returns the headers from the response.
     *
     * @return array
     */
    public function headers()
    {
        return $this->headers;
    }

    /**
     * Writes content to the response body.
     *
     * @param string $str Response content
     *
     * @return object Self reference
     */
    public function write(string $str)
    {
        $this->body .= $str;

        return $this;
    }

    /**
     * Clears the response.
     *
     * @return object Self reference
     */
    public function clear()
    {
        $this->status = 200;
        $this->headers = [];
        $this->body = '';

        return $this;
    }

    /**
     * Sets caching headers for the response.
     *
     * @param int|string $expires Expiration time
     *
     * @return object Self reference
     */
    public function cache($expires)
    {
        if (false === $expires) {
            $this->headers['Expires'] = 'Mon, 26 Jul 1997 05:00:00 GMT';
            $this->headers['Cache-Control'] = [
                'no-store, no-cache, must-revalidate',
                'post-check=0, pre-check=0',
                'max-age=0',
            ];
            $this->headers['Pragma'] = 'no-cache';
        } else {
            $expires = is_int($expires) ? $expires : strtotime($expires);
            $this->headers['Expires'] = gmdate('D, d M Y H:i:s', $expires).' GMT';
            $this->headers['Cache-Control'] = 'max-age='.($expires - time());
            if (isset($this->headers['Pragma']) && 'no-cache' === $this->headers['Pragma']) {
                unset($this->headers['Pragma']);
            }
        }

        return $this;
    }

    /**
     * Sends HTTP headers.
     *
     * @return object Self reference
     */
    public function sendHeaders()
    {
        // Send status code header
        if (false !== strpos(PHP_SAPI, 'cgi')) {
            header(sprintf('Status: %d %s', $this->status, self::$codes[$this->status]), true);
        } else {
            header(
                sprintf(
                    '%s %d %s',
                    (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1'),
                    $this->status,
                    self::$codes[$this->status]
                ),
                true,
                $this->status
            );
        }

        if ($this->etag) {
            header('ETag: "'.md5($this->body).'"');
        }

        // Send other headers
        foreach ($this->headers as $field => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    header($field.': '.$v, false);
                }
            } else {
                header($field.': '.$value);
            }
        }

        return $this;
    }

    /**
     * Gets the content length.
     *
     * @return string Content length
     */
    public function getContentLength()
    {
        return extension_loaded('mbstring')
            ? mb_strlen($this->body, 'latin1')
            : strlen($this->body);
    }

    /**
     * Gets whether response was sent.
     */
    public function sent()
    {
        return $this->sent;
    }

    /**
     * Sends a HTTP response.
     */
    public function send()
    {
        if ($this->sent) {
            return;
        }

        if (ob_get_length() > 0) {
            ob_end_clean();
        }

        if (!headers_sent($filename, $linenum)) {
            $this->sendHeaders();
            echo $this->body;
        }

        $this->sent = true;
    }
}
/**
 * Event.
 */
class PlumeEvent
{
    /**
     * Mapped events.
     *
     * @var array
     */
    protected $events = [];

    /**
     * Method filters.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * Dispatches an event.
     *
     * @param string $name   Event name
     * @param array  $params Callback parameters
     *
     * @throws \Exception
     *
     * @return string Output of callback
     */
    public function run(string $name, array $params = [])
    {
        $output = '';

        // Run pre-filters
        if (!empty($this->filters[$name]['before'])) {
            $this->filter($this->filters[$name]['before'], $params, $output);
        }

        // Run requested method
        $output = $this->execute($this->get($name), $params);

        // Run post-filters
        if (!empty($this->filters[$name]['after'])) {
            $this->filter($this->filters[$name]['after'], $params, $output);
        }

        return $output;
    }

    /**
     * Assigns a callback to an event.
     *
     * @param string   $name     Event name
     * @param callback $callback Callback function
     */
    public function set(string $name, $callback)
    {
        $this->events[$name] = $callback;
    }

    /**
     * Gets an assigned callback.
     *
     * @param string $name Event name
     *
     * @return callback $callback Callback function
     */
    public function get(string $name)
    {
        return isset($this->events[$name]) ? $this->events[$name] : null;
    }

    /**
     * Checks if an event has been set.
     *
     * @param string $name Event name
     *
     * @return bool Event status
     */
    public function has(string $name): bool
    {
        return isset($this->events[$name]);
    }

    /**
     * Clears an event. If no name is given,
     * all events are removed.
     *
     * @param string $name Event name
     */
    public function clear(string $name = null)
    {
        if (null !== $name) {
            unset($this->events[$name], $this->filters[$name]);
        } else {
            $this->events = [];
            $this->filters = [];
        }
    }

    /**
     * Hooks a callback to an event.
     *
     * @param string   $name     Event name
     * @param string   $type     Filter type
     * @param callback $callback Callback function
     */
    public function hook(string $name, string $type, $callback)
    {
        $this->filters[$name][$type][] = $callback;
    }

    /**
     * Executes a chain of method filters.
     *
     * @param array $filters Chain of filters
     * @param array $params  Method parameters
     * @param mixed $output  Method output
     *
     * @throws \Exception
     */
    public function filter(array $filters, array &$params, &$output)
    {
        $args = [&$params, &$output];
        foreach ($filters as $callback) {
            $continue = $this->execute($callback, $args);
            if (false === $continue) {
                break;
            }
        }
    }

    /**
     * Executes a callback function.
     *
     * @param callback $callback Callback function
     * @param array    $params   Function parameters
     *
     * @throws \Exception
     *
     * @return mixed Function results
     */
    public function execute($callback, array &$params = [])
    {
        if (is_array($callback) && is_string($callback[0]) && isset($callback[1])) {
            $classname = $callback[0];
            $method = $callback[1];
            if (class_exists($classname)) {
                $r_method = new ReflectionMethod("${classname}::${method}");
                if (!$r_method->isStatic()) {  //is not a static method
                    $callback[0] = new $callback[0]();
                } //instantiate object on the fly
            } else {
                throw new \Exception('The class '.$callback[0].' does not exists!');
            }
        }

        if (is_callable($callback)) {
            return is_array($callback) ?
                self::invokeMethod($callback, $params) : //here, $callback is a string or an object
                self::callFunction($callback, $params);
        }

        throw new \Exception('Invalid callback specified.');
    }

    /**
     * Calls a function.
     *
     * @param string $func   Name of function to call
     * @param array  $params Function parameters
     *
     * @return mixed Function results
     */
    public static function callFunction($func, array &$params = [])
    {
        // Call static method
        if (is_string($func) && false !== strpos($func, '::')) {
            return call_user_func_array($func, $params);
        }

        switch (count($params)) {
        case 0:
            return $func();
        case 1:
            return $func($params[0]);
        case 2:
            return $func($params[0], $params[1]);
        case 3:
            return $func($params[0], $params[1], $params[2]);
        case 4:
            return $func($params[0], $params[1], $params[2], $params[3]);
        case 5:
            return $func($params[0], $params[1], $params[2], $params[3], $params[4]);
        default:
            return call_user_func_array($func, $params);
        }
    }

    /**
     * Invokes a method.
     *
     * @param mixed $func   Class method
     * @param array $params Class method parameters
     *
     * @return mixed Function results
     */
    public static function invokeMethod($func, array &$params = [])
    {
        list($class, $method) = $func;

        $instance = is_object($class);
        if (!$instance && method_exists($class, $method)) {
            $methodChecker = new \ReflectionMethod($class, $method);
            if (!$methodChecker->isStatic()) {
                $class = new $class();
                $instance = is_object($class);
            }
        }

        switch (count($params)) {
        case 0:
            return ($instance) ? $class->{$method}() : $class::$method();
        case 1:
            return ($instance) ? $class->{$method}($params[0]) : $class::$method($params[0]);
        case 2:
            return ($instance) ?
                $class->{$method}($params[0], $params[1]) :
                $class::$method($params[0], $params[1]);
        case 3:
            return ($instance) ?
                $class->{$method}($params[0], $params[1], $params[2]) :
                $class::$method($params[0], $params[1], $params[2]);
        case 4:
            return ($instance) ?
                $class->{$method}($params[0], $params[1], $params[2], $params[3]) :
                $class::$method($params[0], $params[1], $params[2], $params[3]);
        case 5:
            return ($instance) ?
                $class->{$method}($params[0], $params[1], $params[2], $params[3], $params[4]) :
                $class::$method($params[0], $params[1], $params[2], $params[3], $params[4]);
        default:
            return call_user_func_array($func, $params);
        }
    }

    /**
     * Resets the object to the initial state.
     */
    public function reset()
    {
        $this->events = [];
        $this->filters = [];
    }
}
// View object
class PlumeParam
{
    public $module;
    public $class;
    public $func;
    private $urlparam = [];

    public function __construct(array $params = [])
    {
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $this->urlparam);
        }

        if (!empty($_POST)) {
            $this->urlparam = array_merge($this->urlparam, $_POST);
        }

        // Check for JSON input
        if (isset($_SERVER['CONTENT_TYPE'])
            && 0 === strpos($_SERVER['CONTENT_TYPE'], 'application/json')) {
            $body = file_get_contents('php://input');
            if ($body) {
                $data = json_decode($body, true);
                if ($data) {
                    $this->urlparam = array_merge($this->urlparam, $data);
                }
            }
        }

        if ($params) {
            $this->urlparam = array_merge($this->urlparam, $params);
        }
    }

    public function __get(string $pn)
    {
        $v = $this->getValue($pn);
        if (is_string($v)) {
            $v = htmlentities($v, ENT_QUOTES);
        }

        return $v;
    }

    public function __set(string $pn, string $val)
    {
        if (!$pn) {
            return;
        }
        $this->urlparam[$pn] = $val;
    }

    public function __toString()
    {
        return json_encode($this->urlparam, JSON_UNESCAPED_UNICODE);
    }

    public function getValue(string $pn, string $default = '')
    {
        if (isset($this->urlparam[$pn])) {
            return $this->urlparam[$pn];
        }

        return $default;
    }

    public function has(string $pn): bool
    {
        if (isset($this->urlparam[$pn])) {
            return true;
        }

        return false;
    }

    public function updateParams(array $arr)
    {
        if (!$arr) {
            return $this;
        }

        $this->urlparam = array_merge($this->urlparam, $arr);

        return $this;
    }

    public function toArray()
    {
        return $this->urlparam;
    }
}
/**
 * Log class
 * The save path is storage/log, store by day
 * fatal, error and warning will record in .log.wf file
 * sql records will save in .log.sql file.
 */
class PlumeLogger
{
    // logs
    protected $log = [];
    // log id
    protected $logId = '';
    // log path
    protected $logPath = '';

    public function __construct(string $logId = '', string $logPath = '')
    {
        $this->logId = $logId;
        if ($logPath) {
            $this->logPath = $logPath;
        } else {
            $this->logPath = C('PLUME_LOG_PATH') ?: LOG_PATH;
        }
    }

    public function __destruct()
    {
        $this->save();
    }

    /**
     * Write log, sae support.
     *
     * @param string $msg     log message
     * @param array  $context Replaces the placeholder in the record information
     *                        with context information, which is empty by default
     * @param string $level   Log level
     * @param bool   $wf      Whether to record in the separate wf file
     */
    public function write(string $msg, array $context = [], string $level = 'DEBUG', bool $wf = false)
    {
        if (empty($msg)) {
            return;
        }

        if (is_array($msg)) {
            $msg = implode("\n", $msg);
        }

        if ($context) {
            // Builds a replacement array of key names contained in curly braces
            $replace = [];
            foreach ($context as $key => $val) {
                $replace['{'.$key.'}'] = $val;
            }

            // Replace the placeholder in the record information and finally
            // return the modified record information.
            $msg = strtr($msg, $replace);
        }

        if (empty($this->logId)) {
            $this->logId = sprintf('%x', ((int) (microtime(true) * 10000) % 864000000) * 10000 + mt_rand(0, 9999));
        }

        $log_message = date('[Y-m-d H:i:s]').'['.$this->logId.']'."[{$level}]".$msg.PHP_EOL;
        if ('SQL' === strtoupper($level)) {
            $logPath = $this->logPath.DS.date('Ymd').'.log.sql';
            file_put_contents($logPath, $log_message, FILE_APPEND | LOCK_EX);

            return;
        }

        if ($wf) {
            $logPath = $this->logPath.DS.date('Ymd').'.log.wf';
            file_put_contents($logPath, $log_message, FILE_APPEND | LOCK_EX);
        } else {
            $this->log[] = $log_message;
        }
    }

    /**
     * Save logs.
     *
     * @static
     *
     * @return void
     */
    public function save()
    {
        if (empty($this->log)) {
            return;
        }

        $msg = implode('', $this->log);
        $logPath = $this->logPath.DS.date('Ymd').'.log';
        file_put_contents($logPath, $msg, FILE_APPEND | LOCK_EX);
        // Clear the logs
        $this->log = [];
    }

    /**
     * Fatal log.
     *
     * @param string $msg     Log message
     * @param array  $context Replaces the placeholder in the record information
     *                        with context information, which is empty by default
     */
    public function fatal(string $msg, array $context = [])
    {
        $this->write($msg, $context, 'FATAL', true);
    }

    /**
     * Error log.
     *
     * @param string $msg     Log message
     * @param array  $context Replaces the placeholder in the record information
     *                        with context information, which is empty by default
     */
    public function error(string $msg, array $context = [])
    {
        $this->write($msg, $context, 'ERROR', true);
    }

    /**
     * Warning log.
     *
     * @param string $msg     Log message
     * @param array  $context Replaces the placeholder in the record information
     *                        with context information, which is empty by default
     */
    public function warn(string $msg, array $context = [])
    {
        $this->write($msg, $context, 'WARN', true);
    }

    /**
     * Notice log.
     *
     * @param string $msg     Log message
     * @param array  $context Replaces the placeholder in the record information
     *                        with context information, which is empty by default
     */
    public function notice(string $msg, array $context = [])
    {
        $this->write($msg, $context, 'NOTICE');
    }

    /**
     * Info log.
     *
     * @param string $msg     Log message
     * @param array  $context Replaces the placeholder in the record information
     *                        with context information, which is empty by default
     */
    public function info(string $msg, array $context = [])
    {
        $this->write($msg, $context, 'INFO');
    }

    /**
     * Debug log.
     *
     * @param string $msg     Log message
     * @param array  $context Replaces the placeholder in the record information
     *                        with context information, which is empty by default
     */
    public function debug(string $msg, array $context = [])
    {
        $this->write($msg, $context, 'DEBUG');
    }

    /**
     * Sql log.
     *
     * @param string $msg     Log message
     * @param array  $context Replaces the placeholder in the record information
     *                        with context information, which is empty by default
     */
    public function sql(string $msg, array $context = [])
    {
        $this->write($msg, $context, 'SQL');
    }
}
/**
 * The base schema class of the PlumePHP framework.
 */
class PlumeSchema implements \JsonSerializable
{
    /**
     * Array or ArrayObject that gets filled with
     * data from $json or PlumeParam
     * @var array
     */
    protected $filledFields;

    public function __construct(PlumeParam $param = null)
    {
        if (null !== $param) {
            $mapper = new PlumeJsonMapper();
            $mapper->bEnforceMapType = false;
    
            return $mapper->map($param->toArray(), $this);
        }
    }

    /**
     * Create schema from the PlumeParam.
     */
    public static function createFromPlumeParam(PlumeParam $param)
    {
        return new static($param);
    }

    /**
     * json serialize.
     */
    public function jsonSerialize()
    {
        $reflectObj = new \ReflectionClass($this);
        $res = [];
        foreach ($reflectObj->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $name = $this->camelCaseToSnake($property->getName());
                $res[$name] = $property->getValue($this);
            }
        }

        return $res;
    }

    /**
     * Converts the camel case the snake case.
     */
    protected function camelCaseToSnake(string $input): string
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match === strtoupper($match) ? strtolower($match) : lcfirst($match);
        }

        return implode('_', $ret);
    }
}
/**
 * Automatically map JSON structures into objects.
 * Continuously mapping your JSON responses to your own objects becomes
 * tedious and is error prone.
 * Not mentioning the tests that needs to be written for said mapping.
 * JsonMapper has been build with the most common usages in mind.
 * In order to allow for those edge cases which are not supported by default,
 * it can easily be extended as its core has been designed using middleware.
 *
 * @category Netresearch
 * @package  JsonMapper
 * @author   Christian Weiske <cweiske@cweiske.de>
 * @license  OSL-3.0 http://opensource.org/licenses/osl-3.0
 * @link     http://cweiske.de/
 */
class PlumeJsonMapper
{
    /**
     * Throw an exception when JSON data contain a property
     * that is not defined in the PHP class
     *
     * @var boolean
     */
    public $bExceptionOnUndefinedProperty = false;

    /**
     * Throw an exception if the JSON data miss a property
     * that is marked with @required in the PHP class
     *
     * @var boolean
     */
    public $bExceptionOnMissingData = false;

    /**
     * If the types of map() parameters shall be checked.
     *
     * You have to disable it if you're using the json_decode "assoc" parameter.
     *
     *     json_decode($str, false)
     *
     * @var boolean
     */
    public $bEnforceMapType = true;

    /**
     * Throw an exception when an object is expected but the JSON contains
     * a non-object type.
     *
     * @var boolean
     */
    public $bStrictObjectTypeChecking = false;

    /**
     * Throw an exception, if null value is found
     * but the type of attribute does not allow nulls.
     *
     * @var bool
     */
    public $bStrictNullTypes = true;

    /**
     * Allow mapping of private and proteted properties.
     *
     * @var boolean
     */
    public $bIgnoreVisibility = false;

    /**
     * Remove attributes that were not passed in JSON,
     * to avoid confusion between them and NULL values.
     *
     * @var boolean
     */
    public $bRemoveUndefinedAttributes = false;

    /**
     * Override class names that JsonMapper uses to create objects.
     * Useful when your setter methods accept abstract classes or interfaces.
     *
     * @var array
     */
    public $classMap = [];

    /**
     * Callback used when an undefined property is found.
     *
     * Works only when $bExceptionOnUndefinedProperty is disabled.
     *
     * Parameters to this function are:
     * 1. Object that is being filled
     * 2. Name of the unknown JSON property
     * 3. JSON value of the property
     *
     * @var callable
     */
    public $undefinedPropertyHandler;

    /**
     * Method to call on each object after deserialization is done.
     *
     * Is only called if it exists on the object.
     *
     * @var null|string
     */
    public $postMappingMethod;

    /**
     * Runtime cache for inspected classes. This is particularly effective if
     * mapArray() is called with a large number of objects
     *
     * @var array property inspection result cache
     */
    protected $arInspectedClasses = [];

    /**
     * Map data all data in $json into the given $object instance.
     *
     * @param array|object $json   JSON object structure from json_decode()
     * @param object       $object Object to map $json data into
     * @return mixed Mapped object is returned.
     * @see    mapArray()
     */
    public function map($json, $object)
    {
        if ($this->bEnforceMapType && !is_object($json)) {
            throw new InvalidArgumentException(
                'PlumeJsonMapper::map() requires first argument to be an object'
                . ', ' . gettype($json) . ' given.'
            );
        }

        if (!is_object($object)) {
            throw new InvalidArgumentException(
                'PlumeJsonMapper::map() requires second argument to be an object'
                . ', ' . gettype($object) . ' given.'
            );
        }

        $strClassName = get_class($object);
        $rc = new ReflectionClass($object);
        $strNs = $rc->getNamespaceName();
        $providedProperties = [];
        foreach ($json as $key => $jvalue) {
            $key = $this->getSafeName($key);
            $providedProperties[$key] = true;

            // Store the property inspection results so we don't have to do it
            // again for subsequent objects of the same type
            if (!isset($this->arInspectedClasses[$strClassName][$key])) {
                $this->arInspectedClasses[$strClassName][$key]
                    = $this->inspectProperty($rc, $key);
            }

            list($hasProperty, $accessor, $type, $isNullable)
                = $this->arInspectedClasses[$strClassName][$key];

            if (!$hasProperty) {
                if ($this->bExceptionOnUndefinedProperty) {
                    throw new Exception(
                        'JSON property "' . $key . '" does not exist'
                        . ' in object of type ' . $strClassName
                    );
                }
                if (null !== $this->undefinedPropertyHandler) {
                    call_user_func(
                        $this->undefinedPropertyHandler,
                        $object,
                        $key,
                        $jvalue
                    );
                }

                continue;
            }

            if (null === $accessor) {
                if ($this->bExceptionOnUndefinedProperty) {
                    throw new Exception(
                        'JSON property "' . $key . '" has no public setter method'
                        . ' in object of type ' . $strClassName
                    );
                }

                L('Property {property} has no public setter method in {class}', [
                    'property' => $key, 'class' => $strClassName
                ]);

                continue;
            }

            if ($isNullable || !$this->bStrictNullTypes) {
                if (null === $jvalue) {
                    $this->setProperty($object, $accessor, null);

                    continue;
                }
                $type = $this->removeNullable($type);
            } elseif (null === $jvalue) {
                throw new Exception(
                    'JSON property "' . $key . '" in class "'
                    . $strClassName . '" must not be NULL'
                );
            }

            $type = $this->getFullNamespace($type, $strNs);
            $type = $this->getMappedType($type, $jvalue);

            if (null === $type || 'mixed' === $type) {
                //no given type - simply set the json data
                $this->setProperty($object, $accessor, $jvalue);

                continue;
            }
            if ($this->isObjectOfSameType($type, $jvalue)) {
                $this->setProperty($object, $accessor, $jvalue);

                continue;
            }
            if ($this->isSimpleType($type)) {
                if ('string' === $type && is_object($jvalue)) {
                    throw new Exception(
                        'JSON property "' . $key . '" in class "'
                        . $strClassName . '" is an object and'
                        . ' cannot be converted to a string'
                    );
                }
                settype($jvalue, $type);
                $this->setProperty($object, $accessor, $jvalue);

                continue;
            }

            //FIXME: check if type exists, give detailed error message if not
            if ('' === $type) {
                throw new Exception(
                    'Empty type at property "'
                    . $strClassName . '::$' . $key . '"'
                );
            }

            $array = null;
            $subtype = null;
            if ($this->isArrayOfType($type)) {
                //array
                $array = [];
                $subtype = substr($type, 0, -2);
            } elseif (']' === substr($type, -1)) {
                list($proptype, $subtype) = explode('[', substr($type, 0, -1));
                if ('array' === $proptype) {
                    $array = [];
                } else {
                    $array = $this->createInstance($proptype, false, $jvalue);
                }
            } else {
                if (is_a($type, 'ArrayObject', true)) {
                    $array = $this->createInstance($type, false, $jvalue);
                }
            }

            if (null !== $array) {
                if (!is_array($jvalue) && $this->isFlatType(gettype($jvalue))) {
                    throw new Exception(
                        'JSON property "' . $key . '" must be an array, '
                        . gettype($jvalue) . ' given'
                    );
                }

                $cleanSubtype = $this->removeNullable($subtype);
                $subtype = $this->getFullNamespace($cleanSubtype, $strNs);
                $child = $this->mapArray($jvalue, $array, $subtype, $key);
            } elseif ($this->isFlatType(gettype($jvalue))) {
                //use constructor parameter if we have a class
                // but only a flat type (i.e. string, int)
                if ($this->bStrictObjectTypeChecking) {
                    throw new Exception(
                        'JSON property "' . $key . '" must be an object, '
                        . gettype($jvalue) . ' given'
                    );
                }
                $child = $this->createInstance($type, true, $jvalue);
            } else {
                $child = $this->createInstance($type, false, $jvalue);
                $this->map($jvalue, $child);
            }
            $this->setProperty($object, $accessor, $child);
        }

        if ($this->bExceptionOnMissingData) {
            $this->checkMissingData($providedProperties, $rc);
        }

        if ($this->bRemoveUndefinedAttributes) {
            $this->removeUndefinedAttributes($object, $providedProperties);
        }

        if (null !== $this->postMappingMethod
            && $rc->hasMethod($this->postMappingMethod)
        ) {
            $refDeserializePostMethod = $rc->getMethod(
                $this->postMappingMethod
            );
            $refDeserializePostMethod->setAccessible(true);
            $refDeserializePostMethod->invoke($object);
        }

        return $object;
    }

    /**
     * Map an array
     *
     * @param array  $json       JSON array structure from json_decode()
     * @param mixed  $array      Array or ArrayObject that gets filled with
     *                           data from $json
     * @param string $class      Class name for children objects.
     *                           All children will get mapped onto this type.
     *                           Supports class names and simple types
     *                           like "string" and nullability "string|null".
     *                           Pass "null" to not convert any values
     * @param string $parentKey Defines the key this array belongs to
     *                           in order to aid debugging.
     * @return mixed Mapped $array is returned
     */
    public function mapArray($json, $array, $class = null, $parentKey = '')
    {
        $originalClass = $class;
        foreach ($json as $key => $jvalue) {
            $class = $this->getMappedType($originalClass, $jvalue);
            if (null === $class) {
                $array[$key] = $jvalue;
            } elseif ($this->isArrayOfType($class)) {
                $array[$key] = $this->mapArray(
                    $jvalue,
                    [],
                    substr($class, 0, -2)
                );
            } elseif ($this->isFlatType(gettype($jvalue))) {
                //use constructor parameter if we have a class
                // but only a flat type (i.e. string, int)
                if (null === $jvalue) {
                    $array[$key] = null;
                } else {
                    if ($this->isSimpleType($class)) {
                        settype($jvalue, $class);
                        $array[$key] = $jvalue;
                    } else {
                        $array[$key] = $this->createInstance(
                            $class,
                            true,
                            $jvalue
                        );
                    }
                }
            } elseif ($this->isFlatType($class)) {
                throw new Exception(
                    'JSON property "' . ($parentKey ? $parentKey : '?') . '"'
                    . ' is an array of type "' . $class . '"'
                    . ' but contained a value of type'
                    . ' "' . gettype($jvalue) . '"'
                );
            } elseif (is_a($class, 'ArrayObject', true)) {
                $array[$key] = $this->mapArray(
                    $jvalue,
                    $this->createInstance($class)
                );
            } else {
                $array[$key] = $this->map(
                    $jvalue,
                    $this->createInstance($class, false, $jvalue)
                );
            }
        }

        return $array;
    }

    /**
     * Convert a type name to a fully namespaced type name.
     *
     * @param string $type  Type name (simple type or class name)
     * @param string $strNs Base namespace that gets prepended to the type name
     * @return string Fully-qualified type name with namespace
     */
    protected function getFullNamespace($type, $strNs)
    {
        if (null === $type || '' === $type || '\\' === $type[0] || '' === $strNs) {
            return $type;
        }
        list($first) = explode('[', $type, 2);
        if ('mixed' === $first || $this->isSimpleType($first)) {
            return $type;
        }

        //create a full qualified namespace
        return '\\' . $strNs . '\\' . $type;
    }

    /**
     * Check required properties exist in json
     *
     * @param array  $providedProperties array with json properties
     * @param object $rc                 Reflection class to check
     * @throws Exception
     * @return void
     */
    protected function checkMissingData($providedProperties, ReflectionClass $rc)
    {
        foreach ($rc->getProperties() as $property) {
            $rprop = $rc->getProperty($property->name);
            $docblock = $rprop->getDocComment();
            $annotations = static::parseAnnotations($docblock);
            if (isset($annotations['required'])
                && !isset($providedProperties[$property->name])
            ) {
                throw new Exception(
                    'Required property "' . $property->name . '" of class '
                    . $rc->getName()
                    . ' is missing in JSON data'
                );
            }
        }
    }

    /**
     * Remove attributes from object that were not passed in JSON data.
     *
     * This is to avoid confusion between those that were actually passed
     * as NULL, and those that weren't provided at all.
     *
     * @param object $object             Object to remove properties from
     * @param array  $providedProperties Array with JSON properties
     * @return void
     */
    protected function removeUndefinedAttributes($object, $providedProperties)
    {
        foreach (get_object_vars($object) as $propertyName => $dummy) {
            if (!isset($providedProperties[$propertyName])) {
                unset($object->{$propertyName});
            }
        }
    }

    /**
     * Try to find out if a property exists in a given class.
     * Checks property first, falls back to setter method.
     *
     * @param ReflectionClass $rc   Reflection class to check
     * @param string          $name Property name
     * @return array First value: if the property exists
     *               Second value: the accessor to use (
     *                 ReflectionMethod or ReflectionProperty, or null)
     *               Third value: type of the property
     *               Fourth value: if the property is nullable
     */
    protected function inspectProperty(ReflectionClass $rc, $name)
    {
        //try setter method first
        $setter = 'set' . $this->getCamelCaseName($name);
        if ($rc->hasMethod($setter)) {
            $rmeth = $rc->getMethod($setter);
            if ($rmeth->isPublic() || $this->bIgnoreVisibility) {
                $isNullable = false;
                $rparams = $rmeth->getParameters();
                if (count($rparams) > 0) {
                    $isNullable = $rparams[0]->allowsNull();
                    $ptype = $rparams[0]->getType();
                    if (null !== $ptype) {
                        if ($ptype instanceof ReflectionNamedType) {
                            $typeName = $ptype->getName();
                        }
                        if ($ptype instanceof ReflectionUnionType
                            || !$ptype->isBuiltin()
                        ) {
                            $typeName = '\\' . $typeName;
                        }
                        //allow overriding an "array" type hint
                        // with a more specific class in the docblock
                        if ('array' !== $typeName) {
                            return [
                                true, $rmeth,
                                $typeName,
                                $isNullable,
                            ];
                        }
                    }
                }

                $docblock = $rmeth->getDocComment();
                $annotations = static::parseAnnotations($docblock);

                if (!isset($annotations['param'][0])) {
                    return [true, $rmeth, null, $isNullable];
                }
                list($type) = explode(' ', trim($annotations['param'][0]));

                return [true, $rmeth, $type, $this->isNullable($type)];
            }
        }

        //now try to set the property directly
        //we have to look it up in the class hierarchy
        $class = $rc;
        $rprop = null;
        do {
            if ($class->hasProperty($name)) {
                $rprop = $class->getProperty($name);
            }
        } while (null === $rprop && $class = $class->getParentClass());

        if (null === $rprop) {
            //case-insensitive property matching
            foreach ($rc->getProperties() as $p) {
                if ((0 === strcasecmp($p->name, $name))) {
                    $rprop = $p;

                    break;
                }
            }
        }

        if (null === $rprop) {
            //no setter, no property
            return [false, null, null, false];
        }

        if (!$rprop->isPublic() && !$this->bIgnoreVisibility) {
            //no setter, private property
            return [true, null, null, false];
        }

        $docblock = $rprop->getDocComment();
        $annotations = static::parseAnnotations($docblock);
        if (!isset($annotations['var'][0])) {
            // If there is no annotations (higher priority) inspect
            // if there's a scalar type being defined
            if (PHP_VERSION_ID >= 70400 && $rprop->hasType()) {
                $rPropType = $rprop->getType();
                $propTypeName = $rPropType->getName();
                if ($this->isSimpleType($propTypeName)) {
                    return [
                        true,
                        $rprop,
                        $propTypeName,
                        $rPropType->allowsNull()
                    ];
                }

                return [
                    true,
                    $rprop,
                    '\\'.$propTypeName,
                    $rPropType->allowsNull()
                ];
            }

            return [true, $rprop, null, false];
        }
        //support "@var type description"
        list($type) = explode(' ', $annotations['var'][0]);

        return [true, $rprop, $type, $this->isNullable($type)];
    }

    /**
     * Removes - and _ and makes the next letter uppercase
     *
     * @param string $name Property name
     * @return string CamelCasedVariableName
     */
    protected function getCamelCaseName($name)
    {
        return str_replace(
            ' ',
            '',
            ucwords(str_replace(['_', '-'], ' ', $name))
        );
    }

    /**
     * Since hyphens cannot be used in variables we have to uppercase them.
     * Technically you may use them, but they are awkward to access.
     *
     * @param string $name Property name
     * @return string Name without hyphen
     */
    protected function getSafeName($name)
    {
        if (false !== strpos($name, '-')) {
            $name = $this->getCamelCaseName($name);
        }

        return $name;
    }

    /**
     * Set a property on a given object to a given value.
     *
     * Checks if the setter or the property are public are made before
     * calling this method.
     *
     * @param object $object   Object to set property on
     * @param object $accessor ReflectionMethod or ReflectionProperty
     * @param mixed  $value    Value of property
     * @return void
     */
    protected function setProperty(
        $object,
        $accessor,
        $value
    ) {
        if (!$accessor->isPublic() && $this->bIgnoreVisibility) {
            $accessor->setAccessible(true);
        }
        if ($accessor instanceof ReflectionProperty) {
            $accessor->setValue($object, $value);
        } else {
            //setter method
            $accessor->invoke($object, $value);
        }
    }

    /**
     * Create a new object of the given type.
     *
     * This method exists to be overwritten in child classes,
     * so you can do dependency injection or so.
     *
     * @param string  $class        Class name to instantiate
     * @param boolean $useParameter Pass $parameter to the constructor or not
     * @param mixed   $jvalue       Constructor parameter (the json value)
     * @return object Freshly created object
     */
    protected function createInstance(
        $class,
        $useParameter = false,
        $jvalue = null
    ) {
        if ($useParameter) {
            return new $class($jvalue);
        }
        $reflectClass = new ReflectionClass($class);
        $constructor = $reflectClass->getConstructor();
        if (null === $constructor
                || $constructor->getNumberOfRequiredParameters() > 0
            ) {
            return $reflectClass->newInstanceWithoutConstructor();
        }

        return $reflectClass->newInstance();
    }

    /**
     * Get the mapped class/type name for this class.
     * Returns the incoming classname if not mapped.
     *
     * @param string $type   Type name to map
     * @param mixed  $jvalue Constructor parameter (the json value)
     * @return string The mapped type/class name
     */
    protected function getMappedType(string $type, $jvalue = null)
    {
        if (isset($this->classMap[$type])) {
            $target = $this->classMap[$type];
        } elseif (is_string($type) && '' !== $type && '\\' === $type[0]
            && isset($this->classMap[substr($type, 1)])
        ) {
            $target = $this->classMap[substr($type, 1)];
        } else {
            $target = null;
        }

        if ($target) {
            if (is_callable($target)) {
                $type = $target($type, $jvalue);
            } else {
                $type = $target;
            }
        }

        return $type;
    }

    /**
     * Checks if the given type is a "simple type"
     *
     * @param string $type type name from gettype()
     * @return boolean True if it is a simple PHP type
     * @see isFlatType()
     */
    protected function isSimpleType($type)
    {
        return 'string' === $type
            || 'boolean' === $type || 'bool' === $type
            || 'integer' === $type || 'int' === $type
            || 'double' === $type || 'float' === $type
            || 'array' === $type || 'object' === $type;
    }

    /**
     * Checks if the object is of this type or has this type as one of its parents
     *
     * @param string $type  class name of type being required
     * @param mixed  $value Some PHP value to be tested
     * @return boolean True if $object has type of $type
     */
    protected function isObjectOfSameType($type, $value): bool
    {
        if (false === is_object($value)) {
            return false;
        }

        return is_a($value, $type);
    }

    /**
     * Checks if the given type is a type that is not nested
     * (simple type except array and object)
     *
     * @param string $type type name from gettype()
     * @return boolean True if it is a non-nested PHP type
     * @see isSimpleType()
     */
    protected function isFlatType($type): bool
    {
        return 'NULL' === $type
            || 'string' === $type
            || 'boolean' === $type || 'bool' === $type
            || 'integer' === $type || 'int' === $type
            || 'double' === $type || 'float' === $type;
    }

    /**
     * Returns true if type is an array of elements
     * (bracket notation)
     *
     * @param string $strType type to be matched
     */
    protected function isArrayOfType($strType): bool
    {
        return '[]' === substr($strType, -2);
    }

    /**
     * Checks if the given type is nullable
     *
     * @param string $type type name from the phpdoc param
     * @return boolean True if it is nullable
     */
    protected function isNullable($type): bool
    {
        return false !== stripos('|' . $type . '|', '|null|');
    }

    /**
     * Remove the 'null' section of a type
     *
     * @param string $type type name from the phpdoc param
     * @return string The new type value
     */
    protected function removeNullable($type): ?string
    {
        if (null === $type) {
            return null;
        }

        return substr(
            str_ireplace('|null|', '|', '|' . $type . '|'),
            1,
            -1
        );
    }

    /**
     * Copied from PHPUnit 3.7.29, Util/Test.php
     *
     * @param string $docblock Full method docblock
     * @return array Array of arrays.
     *               Key is the "@"-name like "param",
     *               each value is an array of the rest of the @-lines
     */
    protected static function parseAnnotations($docblock)
    {
        $annotations = [];
        // Strip away the docblock header and footer
        // to ease parsing of one line annotations
        $docblock = substr($docblock, 3, -2);
        $re = '/@(?P<name>[A-Za-z_-]+)(?:[ \t]+(?P<value>.*?))?[ \t]*\r?$/m';
        if (preg_match_all($re, $docblock, $matches)) {
            $numMatches = count($matches[0]);

            for ($i = 0; $i < $numMatches; $i++) {
                $annotations[$matches['name'][$i]][] = $matches['value'][$i];
            }
        }

        return $annotations;
    }
}
/**
 * The PlumePHP HTTP Server.
 */
class PlumeCmdService
{
    public $options = [
        'host'          => '127.0.0.1',
        'port'          => '8080',
        'path'          => '',
        'path_document' => 'public',
    ];

    public $pid = 0;

    protected $cliOptions = [
        'help' => [
            'short' => 'h',
            'desc'  => 'show this help;',
        ],
        'version' => [
            'short' => 'v',
            'desc'  => 'show the version;',
        ],
        'module' => [
            'short'    => 'm',
            'desc'     => 'set the module',
            'required' => true,
        ],
        'cmd' => [
            'short'    => 'c',
            'desc'     => 'set the command',
            'required' => true,
        ],
        'http' => [
            'short' => 'S',
            'desc'  => 'run the http server;',
        ],
        'host' => [
            'short'    => 'H',
            'desc'     => 'set server host,default is 127.0.0.1',
            'required' => true,
        ],
        'port' => [
            'short'    => 'P',
            'desc'     => 'set server port,default is 8080',
            'required' => true,
        ],
        'inner-server' => [
            'short' => 'i',
            'desc'  => 'use inner server',
        ],
        'docroot' => [
            'short'    => 't',
            'desc'     => 'document root',
            'required' => true,
        ],
        'file' => [
            'short'    => 'f',
            'desc'     => 'index file',
            'required' => true,
        ],
        'dry' => [
            'desc' => 'dry mode, just show cmd',
        ],
        'background' => [
            'short' => 'b',
            'desc'  => 'run background',
        ],
    ];

    protected $cliOptionsEx = [];
    protected $args = [];
    protected $docroot = '';

    protected $host;
    protected $port;
    protected $isInited = false;

    protected static $_instances = [];

    public function __construct()
    {
    }

    // embed
    public static function instance($object = null)
    {
        if (defined('__SINGLETONEX_REPALACER')) {
            $callback = __SINGLETONEX_REPALACER;

            return ($callback)(static::class, $object);
        }

        if ($object) {
            self::$_instances[static::class] = $object;

            return $object;
        }

        $me = self::$_instances[static::class] ?? null;
        if (null === $me) {
            $me = new static();
            self::$_instances[static::class] = $me;
        }

        return $me;
    }

    public static function runQuickly(array $options)
    {
        return static::instance()->init($options)->run();
    }

    public function init(array $options, object $context = null)
    {
        $this->options = array_replace_recursive($this->options, $options);
        $this->host = $this->options['host'];
        $this->port = $this->options['port'];
        $this->args = $this->parseCaptures($this->cliOptions);

        $this->docroot = rtrim($this->options['path'] ?? '', '/').'/'.$this->options['path_document'];

        $this->host = $this->args['host'] ?? $this->host;
        $this->port = $this->args['port'] ?? $this->port;
        $this->docroot = $this->args['docroot'] ?? $this->docroot;

        return $this;
    }

    /**
     * Whether inited or not.
     */
    public function isInited(): bool
    {
        return $this->isInited;
    }

    /**
     * Runs the HTTP server.
     */
    public function run()
    {
        if (isset($this->args['version'])) {
            echo '➤ PlumePHP version: ', PLUME_VERSION, PHP_EOL;

            return;
        }

        $this->showWelcome();
        if (isset($this->args['help'])) {
            return $this->showHelp();
        }

        if (isset($this->args['http'])) {
            return $this->runHTTPServer();
        }

        if (empty($this->args['module'])) {
            return $this->showHelp();
        }

        $module = $this->args['module'];
        // Loads the boot file.
        I(APP_PATH.DS.$module.DS.$module.'.boot.php', true);

        $file = $this->args['cmd'] ?? 'index';
        if ($file) {
            $file = str_replace(['\\', '/'], DS, $file);
            $file = trim($file, DS);
        }

        $filename = $file.'.cmd.php';
        $filename = APP_PATH.DS.$module.DS.'console'.DS.$filename;
        if (!file_exists($filename)) {
            return $this->error('!!! 404 !!! file '.$filename.' not exist');
        }

        $className = $module.'_'.str_replace(['\\', '/'], '_', $file).'_cmd';

        // Loads the file.
        require $filename;

        L('[cli]class name: '.$className.', args: '.json_encode($this->args));

        if (!class_exists($className)) {
            return $this->error('!!! 404 !!! class not exist: '.$className);
        }

        $actionInstance = new $className();
        if (!method_exists($actionInstance, 'run')) {
            return $this->error('!!! 404 !!! no run method: '.$className);
        }

        return $actionInstance->run();
    }

    /**
     * Gets the pid of the server process.
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * Close the server.
     */
    public function close()
    {
        if (!$this->pid) {
            return false;
        }
        posix_kill($this->pid, 9);
    }

    protected function error(string $msg)
    {
        echo '➤ ', $msg, PHP_EOL;
    }

    /**
     * Gets the arguments.
     *
     * @param mixed $options
     * @param mixed $optind
     */
    protected function getopt($options, array $longopts, &$optind)
    {
        return getopt($options, $longopts, $optind); // @codeCoverageIgnore
    }

    protected function parseCaptures(array $cliOptions)
    {
        $shorts_map = [];
        $shorts = [];
        $longopts = [];
        foreach ($cliOptions as $k => $v) {
            $required = $v['required'] ?? false;
            $optional = $v['optional'] ?? false;
            $longopts[] = $k.($required ? ':' : '').($optional ? '::' : '');
            if (isset($v['short'])) {
                $shorts[] = $v['short'].($required ? ':' : '').($optional ? '::' : '');
                $shorts_map[$v['short']] = $k;
            }
        }
        $optind = null;
        $args = $this->getopt(implode('', ($shorts)), $longopts, $optind);
        $args = $args ?: [];

        $pos_args = array_slice($_SERVER['argv'], $optind);
        foreach ($shorts_map as $k => $v) {
            if (isset($args[$k]) && !isset($args[$v])) {
                $args[$v] = $args[$k];
            }
        }
        $args = array_merge($args, $pos_args);

        return $args;
    }

    /**
     * Shows the welcome message.
     */
    protected function showWelcome()
    {
        echo "➤ PlumePHP ".PLUME_VERSION.": Wellcome, for more info , use --help \n";
    }

    /**
     * Shows the help message.
     */
    protected function showHelp()
    {
        echo "➤ Usage :\n\n";
        foreach ($this->cliOptions as $k => $v) {
            $long = $k;
            $t = $v['short'] ?? '';
            $t = $t ? '-'.$t : '';
            if ($v['optional'] ?? false) {
                $long .= ' ['.$k.']';
                $t .= ' ['.$k.']';
            }
            if ($v['required'] ?? false) {
                $long .= ' <'.$k.'>';
                $t .= ' <'.$k.'>';
            }
            echo " --{$long}\t{$t}\n\t".$v['desc']."\n";
        }
        echo "\n\nCurrent args :\n";
        var_export($this->args);
        echo "\n";
    }

    /**
     * Runs the HTTP Server.
     */
    protected function runHTTPServer()
    {
        $PHP = escapeshellcmd(PHP_BINARY);
        $host = escapeshellarg((string) $this->host);
        $port = escapeshellarg((string) $this->port);
        $document_root = escapeshellarg($this->docroot);
        if (isset($this->args['background'])) {
            $this->options['background'] = true;
        }

        if ($this->options['background'] ?? false) {
            echo "➤ PlumePHP: RunServer by PHP inner http server {$this->host}:{$this->port}\n";
        }

        $cmd = "${PHP} -S ${host}:${port} -t ${document_root} ";
        if (isset($this->args['dry'])) {
            echo $cmd;
            echo "\n";

            return;
        }

        if ($this->options['background'] ?? false) {
            $cmd .= ' > /dev/null 2>&1 & echo $!; ';
            $pid = exec($cmd);
            $this->pid = (int) $pid;

            return $pid;
        }

        echo "➤ PlumePHP running at : http://{$this->host}:{$this->port}/ \n"; // @codeCoverageIgnore

        return system($cmd); // @codeCoverageIgnore
    }
}
