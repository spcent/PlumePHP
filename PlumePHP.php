<?php
/**
 * PlumePHP是一款开源免费、轻量级的PHP框架。具有低耦合、轻量级、基于VBD模型等特点，
 * 加速高性能现代WEB网站及WebApp应用的开发。
 */
/**
 * index.php参考代码
 * 
// 加载框架文件
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

// api首页展示
$app = PlumePHP::app();
$app->route('GET /api', function() {
    header('Content-Type: text/html;charset=utf-8');
    echo json_encode(['code'=>0, 'data'=>'api', 'msg'=>'success'], JSON_UNESCAPED_UNICODE);
});

// 通用的路由逻辑，如果只是写接口，可以不用框架自带MVC架构
$app->route('*', function() {
    PlumePHP::app()->run();
});

// 启动
$app->start();
 */
define('PLUME_START_MEMORY',  memory_get_usage());
define('PLUME_START_TIME', microtime(true));
define('PLUME_CURRENT_TIME', time());
define('PLUME_VERSION', '1.1.6');
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('PLUME_PHP_PATH') OR define('PLUME_PHP_PATH', __DIR__);
defined('VENDOR_PATH') OR define('VENDOR_PATH', PLUME_PHP_PATH.DS.'vendor'); // vendor第三方目录
defined('LOG_PATH') OR define('LOG_PATH', PLUME_PHP_PATH.DS.'storage'.DS.'log'); // log日志目录

////////////////////////////////////////////////////////////////////////////////////////
// 下面的常有函数和类
////////////////////////////////////////////////////////////////////////////////////////
/**
 * 获取和设置配置参数 支持批量定义
 * 如果$key是关联型数组，则会按K-V的形式写入配置
 * 如果$key是数字索引数组，则返回对应的配置数组
 * @param string|array $key 配置变量
 * @param array|null $value 配置值
 * @return array|null
 */
function C($key, $value = null)
{
    static $_config = [];
    $args = func_num_args();
    if ($args == 1) {
        if (is_string($key)) {  //如果传入的key是字符串
            // 最多3层吧
            $names = explode('.', $key, 3);
            $countNames = count($names);
            if ($countNames == 1) {
                return isset($_config[$key]) ? $_config[$key] : null;
            } elseif ($countNames == 2) {
                $key1 = $names[0];
                $key2 = $names[1];
                return isset($_config[$key1][$key2]) ? $_config[$key1][$key2] : null;
            } else {
                $key1 = $names[0];
                $key2 = $names[1];
                $key3 = $names[2];
                return isset($_config[$key1][$key2][$key3]) ? $_config[$key1][$key2][$key3] : null;
            }
        }

        if (is_array($key)) {
            if (array_keys($key) !== range(0, count($key) - 1)) {  //如果传入的key是关联数组
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
 * 如果文件存在就include进来
 * @param string $path 文件路径
 * @param bool $once 是否使用include_once，默认是false
 * @return
 */
function I($path, $once = false)
{
    if (file_exists($path)) {
        $once ? include_once $path : include $path;
    }
}
/**
 * 日志输出
 * @param string $msg 日志内容
 * @param array $context 用上下文信息替换记录信息中的占位符，默认为空
 * @param string $level 日志等级，默认是DEBUG
 * @param bool $wf 是否记录到单独的wf日志中，默认是false
 */
function L($msg, array $context = array(), $level = 'DEBUG', $wf = false) {
    PlumePHP::app()->log($msg, $context, $level, $wf);
}
/**
 * The PlumePHP class is a static representation of the framework.
 *
 * Core.
 * @method  static app() Gets the application object instance
 * @method  static start() Starts the framework.
 * @method  static path($path) Adds a path for autoloading classes.
 * @method  static stop() Stops the framework and sends a response.
 * @method  static halt($code = 200, $message = '') Stop the framework with an optional status code and message.
 * @method  static route($pattern, $callback) Maps a URL pattern to a callback.
 * @method  static render($file, [$data], [$key], [$layout]) Renders a template file.
 * @method  static error($exception) Sends an HTTP 500 response.
 * @method  static notFound() Sends an HTTP 404 response.
 * @method  static json($data, [$code], [$encode], [$charset], [$option]) Sends a JSON response.
 * @method  static jsonp($data, [$param], [$code], [$encode], [$charset], [$option]) Sends a JSONP response.
 * @method  static map($name, $callback) Creates a custom framework method.
 * @method  static register($name, $class, [$params], [$callback]) Registers a class to a framework method.
 * @method  static before($name, $callback) Adds a filter before a framework method.
 * @method  static after($name, $callback) Adds a filter after a framework method.
 * @method  static get($key) Gets a variable.
 * @method  static set($key, $value) Sets a variable.
 * @method  static has($key) Checks if a variable is set.
 * @method  static clear([$key]) Clears a variable.
 * @method  static log($msg, array $context = array(), $level = 'DEBUG', $wf = false) logging.
 */
class PlumePHP
{
    /**
     * Framework engine.
     * @var PlumeEngine
     */
    private static $engine;

    // Don't allow object instantiation
    private function __construct() {}
    private function __destruct() {}
    private function __clone() {}

    /**
     * Handles calls to static methods.
     * @param string $name Method name
     * @param array $params Method parameters
     * @return mixed Callback results
     * @throws \Exception
     */
    public static function __callStatic($name, $params)
    {
        return PlumeEvent::invokeMethod([self::app(), $name], $params);
    }

    /**
     * @return PlumeEngine Application instance
     */
    public static function app()
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
class PlumeCollection implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * Collection data.
     * @var array
     */
    private $data;

    /**
     * Constructor.
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
     * @return mixed Value
     */
    public function __get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * Set an item.
     *
     * @param string $key Key
     * @param mixed $value Value
     */
    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Checks if an item exists.
     *
     * @param string $key Key
     * @return bool Item status
     */
    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Removes an item.
     * @param string $key Key
     */
    public function __unset($key)
    {
        unset($this->data[$key]);
    }

    /**
     * Gets an item at the offset.
     * @param string $offset Offset
     * @return mixed Value
     */
    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    /**
     * Sets an item at the offset.
     * @param string $offset Offset
     * @param mixed $value Value
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Checks if an item exists at the offset.
     * @param string $offset Offset
     * @return bool Item status
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * Removes an item at the offset.
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
     * @return mixed Value
     */ 
    public function current()
    {
        return current($this->data);
    }
 
    /**
     * Gets current collection key.
     * @return mixed Value
     */ 
    public function key()
    {
        return key($this->data);
    }
 
    /**
     * Gets the next collection value.
     * @return mixed Value
     */ 
    public function next() 
    {
        return next($this->data);
    }
 
    /**
     * Checks if the current collection key is valid.
     * @return bool Key status
     */ 
    public function valid()
    {
        $key = key($this->data);
        return ($key !== NULL && $key !== FALSE);
    }

    /**
     * Gets the size of the collection.
     * @return int Collection size
     */
    public function count()
    {
        return sizeof($this->data);
    }

    /**
     * Gets the item keys.
     * @return array Collection keys
     */
    public function keys()
    {
        return array_keys($this->data);
    }

    /**
     * Gets the collection data.
     * @return array Collection data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Sets the collection data.
     * @param array $data New collection data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * Removes all items from the collection.
     */
    public function clear()
    {
        $this->data = [];
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
     * @var array
     */
    protected $classes = [];

    /**
     * Class instances.
     * @var array
     */
    protected $instances = [];

    /**
     * Autoload directories.
     * @var array
     */
    protected static $dirs = [];

    public function __construct()
    {
        // composer autoload
        if (file_exists(VENDOR_PATH.DS.'autoload.php')) {
            $name = 'composer';
            $class = 'Composer';
            $this->instances[$name] = include(VENDOR_PATH.DS.'autoload.php');
            $this->classes[$name] = [$class, [], null];
        }

        self::autoload(true);
    }

    /**
     * Registers a class.
     * @param string $name Registry name
     * @param string|callable $class Class name or function to instantiate class
     * @param array $params Class initialization parameters
     * @param callback $callback Function to call after object instantiation
     */
    public function register($name, $class, array $params = [], $callback = null)
    {
        unset($this->instances[$name]);
        $this->classes[$name] = [$class, $params, $callback];
    }

    /**
     * Unregisters a class.
     * @param string $name Registry name
     */
    public function unregister($name)
    {
        unset($this->classes[$name]);
    }

    /**
     * Loads a registered class.
     * @param string $name Method name
     * @param bool $shared Shared instance
     * @return object Class instance
     * @throws \Exception
     */
    public function load($name, $shared = true)
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
                $ref = array(&$obj);
                call_user_func_array($callback, $ref);
            }
        }

        return $obj;
    }

    /**
     * Gets a single instance of a class.
     *
     * @param string $name Instance name
     * @return object Class instance
     */
    public function getInstance($name)
    {
        return isset($this->instances[$name]) ? $this->instances[$name] : null;
    }

    /**
     * Gets a new instance of a class.
     * @param string|callable $class Class name or callback function to instantiate class
     * @param array $params Class initialization parameters
     * @return object Class instance
     * @throws \Exception
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
     * @return mixed Class information or null if not registered
     */
    public function get($name)
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

    /*** Autoloading Functions ***/

    /**
     * Starts/stops autoloader.
     * @param bool $enabled Enable/disable autoloading
     * @param array $dirs Autoload directories
     */
    public static function autoload($enabled = true, $dirs = [])
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
     * @param string $class Class name
     */
    public static function loadClass($class)
    {
        $class_file = str_replace(['\\', '_'], '/', $class).'.php';
        foreach (self::$dirs as $dir) {
            $file = $dir.'/'.$class_file;
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }

    /**
     * Adds a directory for autoloading classes.
     * @param mixed $dir Directory path
     */
    public static function addDirectory($dir)
    {
        if (is_array($dir) || is_object($dir)) {
            foreach ($dir as $value) {
                self::addDirectory($value);
            }
        } else if (is_string($dir)) {
            if (!in_array($dir, self::$dirs)) self::$dirs[] = $dir;
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
     * @var boolean Pass self in callback parameters
     */
    public $pass = false;

    /**
     * Constructor.
     * @param string $pattern URL pattern
     * @param mixed $callback Callback function
     * @param array $methods HTTP methods
     * @param boolean $pass Pass self in callback parameters
     */
    public function __construct($pattern, $callback, $methods, $pass)
    {
        $this->pattern = $pattern;
        $this->callback = $callback;
        $this->methods = $methods;
        $this->pass = $pass;
    }

    /**
     * Checks if a URL matches the route pattern. Also parses named parameters in the URL.
     * @param string $url Requested URL
     * @param boolean $case_sensitive Case sensitive matching
     * @return boolean Match status
     */
    public function matchUrl($url, $case_sensitive = false)
    {
        // Wildcard or exact match
        if ($this->pattern === '*' || $this->pattern === $url) {
            return true;
        }

        $ids = [];
        $last_char = substr($this->pattern, -1);
        // Get splat
        if ($last_char === '*') {
            $n = 0;
            $len = strlen($url);
            $count = substr_count($this->pattern, '/');
            for ($i = 0; $i < $len; $i++) {
                if ($url[$i] == '/') $n++;
                if ($n == $count) break;
            }

            $this->splat = (string)substr($url, $i+1);
        }

        // Build the regex for matching
        $regex = str_replace([')','/*'], [')?','(/?|/.*?)'], $this->pattern);
        $regex = preg_replace_callback(
            '#@([\w]+)(:([^/\(\)]*))?#',
            function($matches) use (&$ids) {
                $ids[$matches[1]] = null;
                if (isset($matches[3])) {
                    return '(?P<'.$matches[1].'>'.$matches[3].')';
                }
                return '(?P<'.$matches[1].'>[^/\?]+)';
            },
            $regex
        );

        // Fix trailing slash
        if ($last_char === '/') {
            $regex .= '?';
        } else {
            // Allow trailing slash
            $regex .= '/?';
        }

        // Attempt to match route and named parameters
        if (preg_match('#^'.$regex.'(?:\?.*)?$#'.(($case_sensitive) ? '' : 'i'), $url, $matches)) {
            foreach ($ids as $k => $v) {
                $this->params[$k] = (array_key_exists($k, $matches)) ? urldecode($matches[$k]) : null;
            }

            $this->regex = $regex;
            return true;
        }

        return false;
    }

    /**
     * Checks if an HTTP method matches the route methods.
     * @param string $method HTTP method
     * @return bool Match status
     */
    public function matchMethod($method)
    {
        return count(array_intersect(array($method, '*'), $this->methods)) > 0;
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
     * Mapped routes.
     * @var array
     */
    protected $routes = [];

    /**
     * Pointer to current route.
     * @var int
     */
    protected $index = 0;

    /**
     * Case sensitive matching.
     *
     * @var boolean
     */
    public $case_sensitive = false;

    /**
     * Gets mapped routes.
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
     * @param string $pattern URL pattern to match
     * @param callback $callback Callback function
     * @param boolean $pass_route Pass the matching route object to the callback
     */
    public function map($pattern, $callback, $pass_route = false)
    {
        $url = $pattern;
        $methods = ['*'];
        if (strpos($pattern, ' ') !== false) {
            list($method, $url) = explode(' ', trim($pattern), 2);
            $methods = explode('|', $method);
        }

        $this->routes[] = new PlumeRoute($url, $callback, $methods, $pass_route);
    }

    /**
     * Routes the current request.
     * @param PlumeRequest $request PlumeRequest object
     * @return PlumeRoute|bool Matching route or false if no match
     */
    public function route(PlumeRequest $request)
    {
        while ($route = $this->current()) {
            if ($route !== false && $route->matchMethod($request->method)
                && $route->matchUrl($request->url, $this->case_sensitive)) {
                return $route;
            }
            $this->next();
        }

        return false;
    }

    /**
     * Gets the current route.
     * @return PlumeRoute
     */
    public function current()
    {
        return isset($this->routes[$this->index]) ? $this->routes[$this->index] : false;
    }

    /**
     * Gets the next route.
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
     * @var string
     */
    public $path;

    /**
     * File extension.
     * @var string
     */
    public $extension = '.tpl.php';

    /**
     * Theme
     * @var string
     */
    public $theme = 'default';

    /**
     * View variables.
     * @var array
     */
    protected $vars = [];

    /**
     * Template file.
     * @var string
     */
    private $template;

    /**
     * Constructor.
     * @param string $path Path to templates directory
     */
    public function __construct($path = '.')
    {
        $this->path = $path;
    }

    /**
     * Gets a template variable.
     * @param string $key Key
     * @return mixed Value
     */
    public function get($key)
    {
        return isset($this->vars[$key]) ? $this->vars[$key] : null;
    }

    /**
     * Sets a template variable.
     * @param mixed $key Key
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
     * Checks if a template variable is set.
     * @param string $key Key
     * @return boolean If key exists
     */
    public function has($key)
    {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a template variable. If no key is passed in, clear all variables.
     * @param string $key Key
     */
    public function clear($key = null)
    {
        if (is_null($key)) {
            $this->vars = [];
        } else {
            unset($this->vars[$key]);
        }
    }

    /**
     * Renders a template.
     * @param string $file Template file
     * @param array $data Template data
     * @param string|false $layout layout file
     * @throws \Exception If template not found
     */
    public function render($file, $data = null, $layout = 'layout')
    {
        $this->content = $this->getTemplate($file);
        if (!file_exists($this->content)) {
            throw new \Exception("Template file not found: {$this->content}.");
        }

        if (is_array($data)) {
            $this->vars = array_merge($this->vars, $data);
        }

        extract($this->vars);
        // layout为false，表示不使用布局文件
        if ($layout === false) {
            include $this->content;
        } else {
            // 默认使用'.tpl.php'后缀
            $layoutFile = $this->path . DS . $layout . $this->extension;
            if (!file_exists($layoutFile)) {
                throw new \Exception("Layout file not found: {$layoutFile}.");
            }

            include $layoutFile;
        }
    }

    /**
     * Gets the output of a template.
     * @param string $file Template file
     * @param array $data Template data
     * @param string|false $layout layout file, default false
     * @return string Output of template
     */
    public function fetch($file, $data = null, $layout = false)
    {
        ob_start();

        $this->render($file, $data, $layout);
        $output = ob_get_clean();
        return $output;
    }

    /**
     * Checks if a template file exists.
     * @param string $file Template file
     * @return bool Template file exists
     */
    public function exists($file)
    {
        return file_exists($this->getTemplate($file));
    }

    /**
     * Gets the full path to a template file.
     * @param string $file Template file
     * @return string Template file location
     */
    public function getTemplate($file)
    {
        $ext = $this->extension;
        if (!empty($ext) && (substr($file, -1 * strlen($ext)) != $ext)) {
            $file .= $ext;
        }

        if ((substr($file, 0, 1) == '/')) {
            return $file;
        }

        return $this->path.DS.$file;
    }

    /**
     * Displays escaped output.
     * @param string $str String to escape
     * @return string Escaped string
     */
    public function e($str)
    {
        echo htmlentities($str);
    }

    /**
     * assets管理
     * @param $asset_str string 资源地址
     * @param $prefix string 目录前缀
     * @param $output bool 是否输出
     * @return string
     */
    public function asset($asset_str = '', $prefix = '/assets', $output = false)
    {
        // 相对web根目录
        $asset_name = '';
        if (strpos($asset_str, '/') === 0) {
            $asset_name = $prefix . rtrim($asset_str, '/');
        } else {
            // 所有的静态资源限定在public目录下
            $asset_name = '/' . ltrim($prefix, '/') . trim($asset_str, '/');
        }

        $assetVersion = C('ASSETS_VERSION');
        if ($assetVersion) {
            $asset_name .= strrpos($asset_name, '?') > 0 ? '&_v='.$assetVersion : '?_v='.$assetVersion;
        }

        if ($output === true) {
            if (strrpos($asset_name, '.js') > 0) {
                return "<script src='{$asset_name}'></script>";
            } else if (strrpos($asset_name, '.css') > 0) {
                return "<link rel='stylesheet' href='{$asset_name}' type='text/css'>";
            }
        }
        return $asset_name;
    }
}
/**
 * 总控类
 */
class PlumeEngine
{
    /**
     * Stored variables.
     * @var array
     */
    protected $vars;

    /**
     * Class loader.
     * @var PlumeLoader
     */
    protected $loader;

    /**
     * Event dispatcher.
     * @var PlumeEvent
     */
    protected $dispatcher;

    protected $module = '';
    protected $file = '';

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
     * @param string $name Method name
     * @param array $params Method parameters
     * @return mixed Callback results
     * @throws \Exception
     */
    public function __call($name, $params)
    {
        $callback = $this->dispatcher->get($name);
        if (is_callable($callback)) {
            return $this->dispatcher->run($name, $params);
        }

        if (!$this->loader->get($name)) {
            throw new \Exception("{$name} must be a mapped method.");
        }

        $shared = (!empty($params)) ? (bool)$params[0] : true;
        return $this->loader->load($name, $shared);
    }

    /*** Core Methods ***/

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
        $this->loader->register('router', 'PlumeRouter');
        $this->loader->register('logger', 'PlumeLogger');
        $this->loader->register('view', 'PlumeView', [], function($view) use ($self) {
            $view->path = $self->get('plumephp.views.path');
            $view->extension = $self->get('plumephp.views.extension');
        });

        // Register framework methods
        $methods = [
            'start', 'stop', 'route', 'halt', 'error', 'notFound', 'biz',
            'render', 'json', 'jsonp', 'log'
        ];
        foreach ($methods as $name) {
            $this->dispatcher->set($name, [$this, '_'.$name]);
        }

        // Default configuration settings
        $this->set('plumephp.base_url', null);
        $this->set('plumephp.case_sensitive', false);
        $this->set('plumephp.handle_errors', true);
        $this->set('plumephp.log_errors', true);
        $this->set('plumephp.views.path', './views');
        $this->set('plumephp.views.extension', '.tpl.php');

        // Startup configurationconfiguration
        $this->before('start', function() use ($self) {
            // Enable error handling
            if ($self->get('plumephp.handle_errors')) {
                set_error_handler(array($self, 'handleError'));
                set_exception_handler(array($self, 'handleException'));
            }
            // Set case-sensitivitysensitivity
            $self->router()->case_sensitive = $self->get('plumephp.case_sensitive');
        });

        if (!$initialized) {
            $this->boot();
        }

        $initialized = true;
    }

    /**
     * Custom error handler. Converts errors into exceptions.
     * @param int $errno Error number
     * @param int $errstr Error string
     * @param int $errfile Error file name
     * @param int $errline Error file line number
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
     * @param string $name Method name
     * @param callback $callback Callback function
     * @throws \Exception If trying to map over a framework method
     */
    public function map($name, $callback)
    {
        if (method_exists($this, $name)) {
            throw new \Exception('Cannot override an existing framework method.');
        }
        $this->dispatcher->set($name, $callback);
    }

    /**
     * Registers a class to a framework method.
     * @param string $name Method name
     * @param string $class Class name
     * @param array $params Class initialization parameters
     * @param callback $callback Function to call after object instantiation
     * @throws \Exception If trying to map over a framework method
     */
    public function register($name, $class, array $params = [], $callback = null)
    {
        if (method_exists($this, $name)) {
            throw new \Exception('Cannot override an existing framework method.');
        }

        $this->loader->register($name, $class, $params, $callback);
    }

    /**
     * Adds a pre-filter to a method.
     * @param string $name Method name
     * @param callback $callback Callback function
     */
    public function before($name, $callback)
    {
        $this->dispatcher->hook($name, 'before', $callback);
    }

    /**
     * Adds a post-filter to a method.
     * @param string $name Method name
     * @param callback $callback Callback function
     */
    public function after($name, $callback)
    {
        $this->dispatcher->hook($name, 'after', $callback);
    }

    /**
     * Gets a variable.
     * @param string $key Key
     * @return mixed
     */
    public function get($key = null)
    {
        if ($key === null) return $this->vars;
        return isset($this->vars[$key]) ? $this->vars[$key] : null;
    }

    /**
     * Sets a variable.
     * @param mixed $key Key
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
     * @param string $key Key
     * @return bool Variable status
     */
    public function has($key)
    {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a variable. If no key is passed in, clear all variables.
     * @param string $key Key
     */
    public function clear($key = null)
    {
        if (is_null($key)) {
            $this->vars = [];
        } else {
            unset($this->vars[$key]);
        }
    }

    /**
     * Adds a path for class autoloading.
     * @param string $dir Directory path
     */
    public function path($dir)
    {
        $this->loader->addDirectory($dir);
    }

    /*** Extensible Methods ***/

    /**
     * Starts the framework.
     * @throws \Exception
     */
    public function _start()
    {
        $dispatched = false;
        $self = $this;
        $request = $this->request();
        $response = $this->response();
        $router = $this->router();

        // Allow filters to run
        $this->after('start', function() use ($self) {
            $self->stop();
        });

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
            if (!$continue) break;

            $router->next();
            $dispatched = false;
        }

        if (!$dispatched) {
            $this->notFound();
        }
    }

    /**
     * Stops the framework and outputs the current response.
     * @param int $code HTTP status code
     * @throws \Exception
     */
    public function _stop($code = null)
    {
        $response = $this->response();
        if (!$response->sent()) {
            if ($code !== null) {
                $response->status($code);
            }

            $response->write(ob_get_clean());
            $response->send();
        }
    }

    /**
     * Routes a URL to a callback function.
     * @param string $pattern URL pattern to match
     * @param callback $callback Callback function
     * @param boolean $pass_route Pass the matching route object to the callback
     */
    public function _route($pattern, $callback, $pass_route = false)
    {
        $this->router()->map($pattern, $callback, $pass_route);
    }

    /**
     * Stops processing and returns a given response.
     * @param int $code HTTP status code
     * @param string $message Response message
     */
    public function _halt($code = 200, $message = '')
    {
        if (PHP_SAPI == 'cli') {
            echo date('H:i:s'), ', Msg:' . $message, PHP_EOL;
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
     * @param \Exception|\Throwable $e Thrown exception
     */
    public function _error($e)
    {
        $this->_log('Msg: '.$e->getMessage().
            ', Code: '.$e->getCode().
            ', Trace: \n'.$e->getTraceAsString(), [], 'ERROR', true);

        if ($this->get('plumephp.env') == 'production') {
            $msg = sprintf('<h1>500 Internal Server Error</h1>'.
                '<h3>%s (%s)</h3>',
                $e->getMessage(),
                $e->getCode()
            );
        } else {
            $msg = sprintf('<h1>500 Internal Server Error</h1>'.
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
        } catch(\Exception $e) { // PHP < 7
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
            ->write(
                '<h1>404 Not Found</h1>'.
                '<h3>The page you have requested could not be found.</h3>'.
                str_repeat(' ', 512)
            )
            ->send();
    }

    /**
     * Renders a template.
     * @param string $file Template file
     * @param array $data Template data
     * @param string $key View variable name
     * @param string|false $layout layout file, default false
     * @throws \Exception
     */
    public function _render($file, $data = null, $key = null, $layout = false)
    {
        if ($key !== null) {
            $this->view()->set($key, $this->view()->fetch($file, $data, $layout));
        } else {
            $this->view()->render($file, $data, false, $layout);
        }
    }

    /**
     * Sends a JSON response.
     * @param mixed $data JSON data
     * @param int $code HTTP status code
     * @param bool $encode Whether to perform JSON encoding
     * @param string $charset Charset
     * @param int $option Bitmask Json constant such as JSON_HEX_QUOT
     * @throws \Exception
     */
    public function _json(
        $data,
        $code = 200,
        $encode = true,
        $charset = 'utf-8',
        $option = JSON_UNESCAPED_UNICODE
    )
    {
        $json = ($encode) ? json_encode($data, $option) : $data;
        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/json; charset='.$charset)
            ->write($json)
            ->send();
    }
	
    /**
     * Sends a JSONP response.
     * @param mixed $data JSON data
     * @param string $param Query parameter that specifies the callback name.
     * @param int $code HTTP status code
     * @param bool $encode Whether to perform JSON encoding
     * @param string $charset Charset
     * @param int $option Bitmask Json constant such as JSON_HEX_QUOT
     * @throws \Exception
     */
    public function _jsonp(
        $data,
        $param = 'jsonp',
        $code = 200,
        $encode = true,
        $charset = 'utf-8',
        $option = JSON_UNESCAPED_UNICODE
    )
    {
        $json = ($encode) ? json_encode($data, $option) : $data;
        $callback = $this->request()->query[$param];
        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/javascript; charset='.$charset)
            ->write($callback.'('.$json.');')
            ->send();
    }

    /**
     * 业务调用接口
     * @param string $bizpath 调用地址
     * @param PlumeViewObject $ar 请求参数
     * @return mixed
     */
    public function _biz($bizpath, PlumeViewObject $ar)
    {
        // bizpath的特殊字符处理
        $bizpath = str_replace("..", "", $bizpath);
        $bizpath = str_replace("/", "", $bizpath);
        $bizpath = str_replace("\\", "", $bizpath);
        $names = explode('.', $bizpath, 20);
        $count = count($names);
        if ($count < 2) {
            throw new \Exception("参数格式不对，缺少路径或函数名，bizpath: ".$bizpath);
        }

        // 第一个是模块名称
        $module = $names[0];
        if (!file_exists(APP_PATH.DS.$module)) {
            $module = PlumePHP::get('plumephp.default.module');
        } else {
            $module = array_shift($names);
        }

        // 最后一个是需要调用的函数
        $func = array_pop($names);
        $classname = $names;
        // class file使用.biz.php后缀
        $class_file = APP_PATH.DS.$module.DS.'biz'.DS.implode(DS, $classname).'.biz.php';
        if (!file_exists($class_file)) {
            throw new \Exception('biz class not found:'.implode('.', $classname)."::".$func);
        }

        // 加载模块文件，使用.boot.php后缀
        I(APP_PATH.DS.$module.DS.$module.'.boot.php', true);

        // 加载业务文件
        require_once($class_file);

        // 类名，使用biz_$module名称前缀
        $className = 'biz_'.$module.'_'.implode('_', $classname);
        if (method_exists($className, 'beforeBiz')) {
            call_user_func([$className, 'beforeBiz'], $ar);
        }

        if (!method_exists($className, $func)) {
            throw new \Exception($className."::".$func.' is not exist');
        }

        $user_func_data = call_user_func([$className, $func], $ar);
        if (method_exists($className, 'afterBiz')) {
            $user_func_data = call_user_func([$className, 'afterBiz'], $ar, $user_func_data);
        }

        // 返回请求数据
        return $user_func_data;
    }

    /**
     * 日志输出
     * @param string $msg 日志内容
     * @param array $context 用上下文信息替换记录信息中的占位符，默认为空
     * @param string $level 日志等级，默认是DEBUG
     * @param bool $wf 是否记录到单独的wf日志中，默认是false
     */
    public function _log($msg, array $context = array(), $level = 'DEBUG', $wf = false) {
        $this->logger()->write($msg, $context, $level, $wf);
    }

    /**
     * boot
     */
    protected function boot()
    {
        $env = get_cfg_var("plumephp.env") ? get_cfg_var("plumephp.env") : 'development';
        switch ($env) {
        case 'development':
            error_reporting(-1);
            ini_set('display_errors', 1);
            break;
        case 'testing':
        case 'production':
            ini_set('display_errors', 0);
            error_reporting(-1);
            break;
        default:
            $this->_halt(503, 'The application environment is not set correctly.');
        }

        $this->set('plumephp.env', $env);
        $this->set('plumephp.default.module', 'web');
        defined('APP_PATH') or define('APP_PATH', PLUME_PHP_PATH.DS.'application'); // application目录
        if (!is_dir(APP_PATH)) {
            $this->_halt(503, 'Your application folder path does not appear to be set correctly. Please open the following file and correct this: '
                .pathinfo(__FILE__, PATHINFO_BASENAME));
        }

        defined('CONFIG_PATH') OR define('CONFIG_PATH', PLUME_PHP_PATH.DS.'config'); // config配置目录
        defined('PUBLIC_PATH') OR define('PUBLIC_PATH', PLUME_PHP_PATH.DS.'public'); // public对外访问的目录
        defined('IS_CLI') OR define('IS_CLI', PHP_SAPI=='cli' ? 1 : 0);
        if (!IS_CLI) {
            defined('SITE_DOMAIN') OR define('SITE_DOMAIN', isset($_SERVER['HTTP_HOST']) ? strip_tags($_SERVER['HTTP_HOST']) : '');
            defined('IS_GET') OR define('IS_GET', $_SERVER['REQUEST_METHOD'] =='GET' ? true : false);
            defined('IS_POST') OR define('IS_POST', $_SERVER['REQUEST_METHOD'] =='POST' ? true : false);
            defined('IS_AJAX') OR define('IS_AJAX', (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ? true : false);
        }

        // 加载全局配置文件
        if (file_exists(CONFIG_PATH . DS . 'config.php')) {
            $conf = require CONFIG_PATH . DS . 'config.php';
            // 加载环境配置文件
            if (file_exists(CONFIG_PATH . DS . $env. '.php')) {
                $localConf = require CONFIG_PATH . DS . $env . '.php';
                $conf = array_merge($conf, $localConf);
            }

            C($conf);
        }

        // session管理
        if (C('USE_SESSION') == true && session_status() == PHP_SESSION_NONE
            && !headers_sent($filename, $linenum)) {
            session_start();
        }

        // 设置时区
        $timezone = C('TIME_ZONE');
        if (empty($timezone)) {
            $timezone = 'Asia/Shanghai';
        }
        date_default_timezone_set($timezone);

        // 加载公共函数
        I(PLUME_PHP_PATH . DS . 'common.php');

        // 可以多次调用 register_shutdown_function() ，这些被注册的回调会按照他们注册时的顺序被依次调用。 
        // 如果你在注册的方法内部调用 exit()， 那么所有处理会被中止，并且其他注册的中止回调也不会再被调用。
        register_shutdown_function(function() {
            if ($e = error_get_last()) {
                $msg =  $e['message']. " in " . $e['file'] .' line ' . $e['line'];
                if (IS_CLI) {
                    echo $msg, PHP_EOL;
                }
                L($msg, [], 'FATAL', true);
            }
        });
    }

    // 应用程序入口函数
    public function run()
    {
        return IS_CLI ? $this->runCli() : $this->runWeb();
    }

    // run cli
    protected function runCli()
    {
        global $argv;
        $args = $this->arguments($argv);
        $module = $this->get('plumephp.default.module');
        if (!empty($args['commands']['module'])) {
            $module = trim($args['commands']['module']);
        }

        // 加载模块文件
        I(APP_PATH.DS.$module.DS.$module.'.boot.php', true);

        $this->set('plumephp.module', $module);
        $this->set('plumephp.args', $args);
        // 判断是否存在默认自定义的entry
        if (defined('PLUME_CUSTOM_ENTRY') && PLUME_CUSTOM_ENTRY) {
            return;
        }

        $file = 'index';
        if (!empty($args['commands']['file'])) {
            $file = str_replace(['\\', '/'], DS, $args['commands']['file']);
            $file = trim($file, DS);
        }

        $filename = $file.'.cmd.php';
        $filename = APP_PATH.DS.$module.DS.'console'.DS.$filename;
        if (!file_exists($filename)) {
            $this->_halt(404, '!!! 404 !!! file '.$filename.' not exist');
        }

        $this->set('plumephp.file', $file);
        // 加载执行文件
        require($filename);

        $className = $module.'_'.str_replace(['\\', '/'], '_', $file).'_cmd';
        if (!class_exists($className)) {
            $this->_halt(404, '!!! 404 !!! class not exist: '.$className);
        }

        $actionInstance = new $className();
        if (!method_exists($actionInstance, 'run')) {
            $this->_halt(404, '!!! 404 !!! no run method: '.$className);
        }

        return $actionInstance->run($args);
    }

    // cli的参数处理
    protected function arguments($args)
    {
        array_shift($args);
        $args = join($args, ' ');
        preg_match_all('/ (--\w+(?:[^-]+[^\s-])? ) | (-\w+) /x', $args, $match);
        $args = array_shift($match);
        $ret = [
            'commands' => [],
            'flags'    => []
        ];

        foreach ($args as $arg) {
            // Is it a command? (prefixed with --)
            if (substr($arg, 0, 2) === '--') {
                $value = preg_split('/\s?=\s?/', $arg, 2);
                $com   = substr(array_shift($value), 2);
                $value = join($value);
                $ret['commands'][$com] = !empty($value) ? $value : true;
            } else if (substr($arg, 0, 1) === '-') {
                // Is it a flag? (prefixed with -)
                $flag = substr($arg, 1);
                $ret['flags'][] = $flag;
                // 对于版本命令的特殊处理
                if ($flag == 'version' || $flag == 'v') {
                    $this->_halt(200, 'Plume version: '.PLUME_VERSION);
                }

                if ($flag == 'h' || $flag == 'help') {
                    $str = <<<EOF
Example:
plume --module=user --file=index --dest=/var/ -result1 -result2 --option mew arf moo -z

args:
Array(
    [commands] => Array(
        [module] => user
        [file] => index
        [dest] => /var/
        [option] => mew arf moo
    )
    [flags] => Array(
        [0] => result1
        [1] => result2
        [2] => z
    )
)
EOF;
                    $this->_halt(200, $str);
                }
            }
        }
        return $ret;
    }

    // 缺省路由规则
    /*** 统一格式
    http://your.domain.com[/module][/file][/k/v...]
    说明：
    1. module,file是对应的目录或文件（不带后缀）
    2. k为参数名，v为参数值，可重复，如：id/2/dir/xy 表示带2个参数： id=2 并且 dir=xy
    3. []中括号表示可有可无
    4. 没有对应的则匹配下一个
    5. 没有file时缺省对应index.php，有则对应file.php
    */
    protected function runWeb()
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        $vdname = C('VDNAME');
        if (!empty($vdname)) {
            $vdname = '/' . $vdname;
            $urlPath = substr($requestUri, strlen($vdname));
        } else {
            $urlPath = $requestUri;
        }
        // 路径别名
        $pathalias = C("PATH_ALIAS");
        if (is_array($pathalias)) {
            foreach($pathalias as $k => $v) {
                if (strpos($urlPath, $k) !== false) {
                    $urlPath = str_replace($k, $v, $urlPath);
                    break;
                }
            }
        }

        // 请求参数
        $args = [];
        $pos = strpos($urlPath, '?');
        if ($pos !== false) {
            parse_str(substr($urlPath, $pos+1), $args);
            $urlPath = substr($urlPath, 0, $pos);
        }

        $file = '';
        $module = '';
        // 防止请求地址超长，最多64个
        $pathnames = explode('/', $urlPath, 64);
        // 默认首页
        if (empty($pathnames) || empty($pathnames[1]) || $pathnames[1] == 'index.php') {
            $module = $this->get('plumephp.default.module');
        } else {
            // 最左边的表示模块名称
            $module = $pathnames[1];
        }

        $module = trim($module);
        $this->set('plumephp.module', $module);
        $this->set('plumephp.urlPath', $urlPath);
        // 加载模块文件{$module}.boot.php，每个模块都有一个启动文件
        I(APP_PATH.DS.$module.DS.$module.'.boot.php');
        // 判断是否存在默认自定义的entry
        if (defined('PLUME_CUSTOM_ENTRY') && PLUME_CUSTOM_ENTRY) {
            return;
        }

        $filepath= APP_PATH;
        $namecount = count($pathnames);
        $index = 0;
        $preg = "/^([a-z]+)[a-z0-9_]*$/i";
        for ($index=1; $index<$namecount; $index++) {
            $name = $pathnames[$index];
            if (!empty($name) && (!preg_match($preg, $name) || strlen($name) > 15)) {
                $this->_halt(404, '!!! 404(invalid) !!! uri: '.$requestUri.', urlPath: '.$urlPath.', name: '.$name);
            }
            // default: index.php，默认首页
            if ($index == 1) {
                if (empty($name) || $name == 'index.php') {
                    $file = 'index';
                    break;
                } else {
                    $filepath .= DS.$name.DS.'actions';
                    continue;
                }
            }
            // 默认取当前目录下的index.action.php
            if (empty($name)) {
                if (!file_exists($filepath.DS.'index.action.php')) {
                    $this->_halt(404, '!!! 404(missing index) !!! uri: '.$requestUri.', urlPath: '.$urlPath);
                }
                $file .= DS.'index';
                break;
            }
            // 目录存在，则继续
            $sPath = $filepath.DS.$name;
            if (file_exists($sPath)) {
                $filepath .= DS.$name;
                $file .= DS.$name;
                continue;
            }
            // 查找对应的文件，默认取'.action.php'后缀
            $sPath .= '.action.php';
            if (file_exists($sPath)) {
                $file .= DS.$name;
                break;
            } else {
                $this->_halt(404, '!!! 404 !!! uri='.$requestUri.' parseto:'.$sPath);
            }
        }

        $file = trim($file, DS);
        // 加载执行文件
        $actionFile = APP_PATH.DS.$module.DS.'actions'.DS.$file.'.action.php';
        if (!file_exists($actionFile)) {
            $this->_halt(404, '!!! 404(missing action file) !!! uri: '.$requestUri.' action file: '.$actionFile);
        }

        require($actionFile);

        // 打包剩余参数
        for ($i=$index+1; $i<$namecount; $i+=2) {
            $k = $pathnames[$i];
            $v = null;
            if ($i+1 < $namecount) {
                $v = $pathnames[$i+1];
            }
            $args[$k] = $v;
        }

        $this->set('plumephp.file', $file);
        $this->set('plumephp.args', $args);
        $className = $module.'_'.str_replace(DS, '_', $file).'_action';
        if (!class_exists($className)) {
            $this->_halt(404, '!!! 404 !!! uri='.$requestUri.'class not exist: '.$className);
        }

        $actionInstance = new $className();
        // 根据动作去找对应的方法
        if (!method_exists($actionInstance, 'run')) {
            $this->_halt(404, '!!! 404 !!! uri='.$requestUri.' no run method: '.$className);
        }

        return $actionInstance->run();
    }
}
/**
 * 安全Request对象
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
     * @param array $config Request configuration
     */
    public function __construct($config = [])
    {
        // Default properties
        if (empty($config)) {
            $config = array(
                'url' => str_replace('@', '%40', self::getVar('REQUEST_URI', '/')),
                'base' => str_replace(array('\\',' '), array('/','%20'), dirname(self::getVar('SCRIPT_NAME'))),
                'method' => self::getMethod(),
                'referrer' => self::getVar('HTTP_REFERER'),
                'ip' => self::getVar('REMOTE_ADDR'),
                'ajax' => self::getVar('HTTP_X_REQUESTED_WITH') == 'XMLHttpRequest',
                'scheme' => self::getVar('SERVER_PROTOCOL', 'HTTP/1.1'),
                'user_agent' => self::getVar('HTTP_USER_AGENT'),
                'type' => self::getVar('CONTENT_TYPE'),
                'length' => self::getVar('CONTENT_LENGTH', 0),
                'query' => new PlumeCollection($_GET),
                'data' => new PlumeCollection($_POST),
                'cookies' => new PlumeCollection($_COOKIE),
                'files' => new PlumeCollection($_FILES),
                'secure' => self::getVar('HTTPS', 'off') != 'off',
                'accept' => self::getVar('HTTP_ACCEPT')
            );
        }

        $this->init($config);
    }

    /**
     * Initialize request properties.
     * @param array $properties Array of request properties
     */
    public function init($properties = [])
    {
        // Set all the defined properties
        foreach ($properties as $name => $value) {
            $this->$name = $value;
        }

        // Get the requested URL without the base directory
        if ($this->base != '/' && strlen($this->base) > 0 && strpos($this->url, $this->base) === 0) {
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
        if (strpos($this->type, 'application/json') === 0) {
            $body = $this->getBody();
            if ($body != '') {
                $data = json_decode($body, true);
                if ($data != null) {
                    $this->data->setData($data);
                }
            }
        }
    }

    /**
     * Gets the body of the request.
     * @return string Raw HTTP request body
     */
    public static function getBody()
    {
        static $body;
        if (!is_null($body)) {
            return $body;
        }

        $method = self::getMethod();
        if ($method == 'POST' || $method == 'PUT' || $method == 'PATCH') {
            $body = file_get_contents('php://input');
        }

        return $body;
    }

    /**
     * Gets the request method.
     * @return string
     */
    public static function getMethod()
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
     * @param string $var Variable name
     * @param string $default Default value to substitute
     * @return string Server variable value
     */
    public static function getVar($var, $default = '')
    {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : $default;
    }

    /**
     * Parse query parameters from a URL.
     * @param string $url URL string
     * @return array Query parameters
     */
    public static function parseQuery($url)
    {
        $params = [];
        $args = parse_url($url);
        if (isset($args['query'])) {
            parse_str($args['query'], $params);
        }

        return $params;
    }

    /**
     * 通关ua判断是否为手机
     * @return bool
     */
    public function isMobile()
    {
        //正则表达式,批配不同手机浏览器UA关键词。
        $regex_match = "/(nokia|iphone|android|motorola|^mot\-|softbank|foma|docomo|kddi|up\.browser|up\.link|";
        $regex_match .= "htc|dopod|blazer|netfront|helio|hosin|huawei|novarra|CoolPad|webos|techfaith|palmsource|";
        $regex_match .= "blackberry|alcatel|amoi|ktouch|nexian|samsung|^sam\-|s[cg]h|^lge|ericsson|philips|sagem|wellcom|bunjalloo|maui|";
        $regex_match .= "symbian|smartphone|midp|wap|phone|windows ce|iemobile|^spice|^bird|^zte\-|longcos|pantech|gionee|^sie\-|portalmmm|";
        $regex_match .= "jig\s browser|hiptop|^ucweb|^benq|haier|^lct|opera\s*mobi|opera\*mini|320×320|240×320|176×220";
        $regex_match .= "|mqqbrowser|juc|iuc|ios|ipad";
        $regex_match .= ")/i";

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

    public $etag    = false;

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
        511 => 'Network Authentication Required'
    ];

    /**
     * Sets the HTTP status of the response.
     * @param int $code HTTP status code.
     * @return object|int Self reference
     * @throws \Exception If invalid status code
     */
    public function status($code = null)
    {
        if ($code === null) {
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
     * @param string|array $name Header name or array of names and values
     * @param string $value Header value
     * @return object Self reference
     */
    public function header($name, $value = null)
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
     * Returns the headers from the response
     * @return array
     */
    public function headers()
    {
        return $this->headers;
    }

    /**
     * Writes content to the response body.
     * @param string $str Response content
     * @return object Self reference
     */
    public function write($str)
    {
        $this->body .= $str;
        return $this;
    }

    /**
     * Clears the response.
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
     * @param int|string $expires Expiration time
     * @return object Self reference
     */
    public function cache($expires)
    {
        if ($expires === false) {
            $this->headers['Expires'] = 'Mon, 26 Jul 1997 05:00:00 GMT';
            $this->headers['Cache-Control'] = array(
                'no-store, no-cache, must-revalidate',
                'post-check=0, pre-check=0',
                'max-age=0'
            );
            $this->headers['Pragma'] = 'no-cache';
        } else {
            $expires = is_int($expires) ? $expires : strtotime($expires);
            $this->headers['Expires'] = gmdate('D, d M Y H:i:s', $expires) . ' GMT';
            $this->headers['Cache-Control'] = 'max-age='.($expires - time());
            if (isset($this->headers['Pragma']) && $this->headers['Pragma'] == 'no-cache'){
                unset($this->headers['Pragma']);
            }
        }
        return $this;
    }

    /**
     * Sends HTTP headers.
     * @return object Self reference
     */
    public function sendHeaders()
    {
        // Send status code header
        if (strpos(php_sapi_name(), 'cgi') !== false) {
            header(sprintf('Status: %d %s', $this->status, self::$codes[$this->status]), true);
        } else {
            header(
                sprintf(
                    '%s %d %s',
                    (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1'),
                    $this->status,
                    self::$codes[$this->status]),
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

        // Send content length
        $length = $this->getContentLength();
        if ($length > 0) {
            header('Content-Length: '.$length);
        }

        return $this;
    }

    /**
     * Gets the content length.
     * @return string Content length
     */
    public function getContentLength()
    {
        return extension_loaded('mbstring') ? mb_strlen($this->body, 'latin1') : strlen($this->body);
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
 * 事件绑定处理逻辑
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
     * @param string $name Event name
     * @param array $params Callback parameters
     * @return string Output of callback
     * @throws \Exception
     */
    public function run($name, array $params = [])
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
     * @param string $name Event name
     * @param callback $callback Callback function
     */
    public function set($name, $callback)
    {
        $this->events[$name] = $callback;
    }

    /**
     * Gets an assigned callback.
     * @param string $name Event name
     * @return callback $callback Callback function
     */
    public function get($name)
    {
        return isset($this->events[$name]) ? $this->events[$name] : null;
    }

    /**
     * Checks if an event has been set.
     * @param string $name Event name
     * @return bool Event status
     */
    public function has($name)
    {
        return isset($this->events[$name]);
    }

    /**
     * Clears an event. If no name is given,
     * all events are removed.
     * @param string $name Event name
     */
    public function clear($name = null)
    {
        if ($name !== null) {
            unset($this->events[$name]);
            unset($this->filters[$name]);
        } else {
            $this->events = [];
            $this->filters = [];
        }
    }

    /**
     * Hooks a callback to an event.
     * @param string $name Event name
     * @param string $type Filter type
     * @param callback $callback Callback function
     */
    public function hook($name, $type, $callback)
    {
        $this->filters[$name][$type][] = $callback;
    }

    /**
     * Executes a chain of method filters.
     * @param array $filters Chain of filters
     * @param array $params Method parameters
     * @param mixed $output Method output
     * @throws \Exception
     */
    public function filter($filters, &$params, &$output)
    {
        $args = array(&$params, &$output);
        foreach ($filters as $callback) {
            $continue = $this->execute($callback, $args);
            if ($continue === false) break;
        }
    }

    /**
     * Executes a callback function.
     * @param callback $callback Callback function
     * @param array $params Function parameters
     * @return mixed Function results
     * @throws \Exception
     */
    public function execute($callback, array &$params = [])
    {
        if (is_callable($callback)) {
            return is_array($callback) ?
                self::invokeMethod($callback, $params) :
                self::callFunction($callback, $params);
        } else {
            throw new \Exception('Invalid callback specified.');
        }
    }

    /**
     * Calls a function.
     * @param string $func Name of function to call
     * @param array $params Function parameters
     * @return mixed Function results
     */
    public static function callFunction($func, array &$params = [])
    {
        // Call static method
        if (is_string($func) && strpos($func, '::') !== false) {
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
     * @param mixed $func Class method
     * @param array $params Class method parameters
     * @return mixed Function results
     */
    public static function invokeMethod($func, array &$params = [])
    {
        list($class, $method) = $func;

        $instance = is_object($class);
        switch (count($params)) {
        case 0:
            return ($instance) ?
                $class->$method() :
                $class::$method();
        case 1:
            return ($instance) ?
                $class->$method($params[0]) :
                $class::$method($params[0]);
        case 2:
            return ($instance) ?
                $class->$method($params[0], $params[1]) :
                $class::$method($params[0], $params[1]);
        case 3:
            return ($instance) ?
                $class->$method($params[0], $params[1], $params[2]) :
                $class::$method($params[0], $params[1], $params[2]);
        case 4:
            return ($instance) ?
                $class->$method($params[0], $params[1], $params[2], $params[3]) :
                $class::$method($params[0], $params[1], $params[2], $params[3]);
        case 5:
            return ($instance) ?
                $class->$method($params[0], $params[1], $params[2], $params[3], $params[4]) :
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
// 来自前端的视图对象
class PlumeViewObject
{
    private $urlparam;
    private $inited = false;
    private function init() {
        if ($this->inited) return;
        parse_str($_SERVER['QUERY_STRING'], $this->urlparam);
        $this->inited = true;
    }

    public function __get($pn) {
        $v = $this->getValue($pn);
        if (is_string($v)) {
            $v = htmlentities($v, ENT_QUOTES);
        }
        return $v;
    }

    public function __set($pn, $val) {
        setcookie($pn, $val);
    }

    public function __toString() {
        return json_encode($this->urlparam, JSON_UNESCAPED_UNICODE);
    }

    public function getValue($pn) {
        if (isset($_POST[$pn])) return $_POST[$pn];
        if (isset($_GET[$pn])) return $_GET[$pn];
        $this->init();
        if (isset($this->urlparam[$pn])) return $this->urlparam[$pn];
        if (isset($_COOKIE[$pn])) return $_COOKIE[$pn];
        return "";
    }

    public function has($pn) {
        if (isset($_POST[$pn])) return true;
        if (isset($_GET[$pn])) return true;
        $this->init();
        if (isset($this->urlparam[$pn])) return true;
        if (isset($_COOKIE[$pn])) return true;
        return false;
    }

    public function updateRouteArg($arr) {
        if (is_array($arr) && count($arr) > 0) {
            $this->init();
            $this->urlparam = array_merge($this->urlparam, $arr);
        }
    }
}
/**
 * 日志类
 * 保存路径为 storage/log，按天存放
 * fatal,error和warning会记录在.log.wf文件中
 */
class PlumeLogger
{
    // 日志信息
    protected $log = [];
    // 日志id
    protected $logId = '';
    // 日志目录
    protected $logPath = '';

    public function __construct($logId = '', $logPath = '')
    {
        $this->logId = $logId;
        $this->logPath = $logPath;
    }

    public function __destruct()
    {
        $this->save();
    }

    /**
     * 打日志，支持SAE环境
     * @param string $msg 日志内容
     * @param array $context 用上下文信息替换记录信息中的占位符
     * @param string $level 日志等级
     * @param bool $wf 是否记录到单独的wf日志中
     */
    public function write($msg, array $context = array(), $level = 'DEBUG', $wf = false)
    {
        if (empty($msg)) {
            return;
        }

        if (is_array($msg)) {
            $msg = join("\n", $msg);
        }

        if ($context) {
            // 构建一个花括号包含的键名的替换数组
            $replace = array();
            foreach ($context as $key => $val) {
                $replace['{' . $key . '}'] = $val;
            }

            // 替换记录信息中的占位符，最后返回修改后的记录信息。
            $msg = strtr($msg, $replace);
        }

        if (empty($this->logId)) {
            $this->logId = sprintf('%x', (intval(microtime(true) * 10000) % 864000000) * 10000 + mt_rand(0, 9999));
        }

        $log_message = date('[ Y-m-d H:i:s ]') . '['.$this->logId.']' . "[{$level}]" . $msg . PHP_EOL;
        if ($wf) {
            $logPath = $this->logPath ? $this->logPath : LOG_PATH . '/' . date('Ymd') . '.log.wf';
            file_put_contents($logPath, $log_message, FILE_APPEND | LOCK_EX);
        } else {
            $this->log[] = $log_message;
        }
    }

    /**
     * 日志保存
     * @static
     * @access public
     * @return void
     */
    public function save()
    {
        if (empty($this->log)) return;

        $msg = implode('', $this->log);
        $logPath = $this->logPath ? $this->logPath : LOG_PATH . '/' . date('Ymd') . '.log';
        file_put_contents($logPath, $msg, FILE_APPEND | LOCK_EX);
        // 保存后清空日志缓存
        $this->log = [];
    }

    /**
     * 打印fatal日志
     * @param string $msg 日志信息
     * @param array $context 用上下文信息替换记录信息中的占位符
     */
    public function fatal($msg, array $context = array())
    {
        $this->write($msg, $context, 'FATAL', true);
    }

    /**
     * 打印error日志
     * @param string $msg 日志信息
     * @param array $context 用上下文信息替换记录信息中的占位符
     */
    public function error($msg, array $context = array())
    {
        $this->write($msg, $context, 'ERROR', true);
    }

    /**
     * 打印warning日志
     * @param string $msg 日志信息
     * @param array $context 用上下文信息替换记录信息中的占位符
     */
    public function warn($msg, array $context = array())
    {
        $this->write($msg, $context, 'WARN', true);
    }

    /**
     * 打印notice日志
     * @param string $msg 日志信息
     * @param array $context 用上下文信息替换记录信息中的占位符
     */
    public function notice($msg, array $context = array()) 
    {
        $this->write($msg, $context, 'NOTICE');
    }

    /**
     * 打印info日志
     * @param string $msg 日志信息
     * @param array $context 用上下文信息替换记录信息中的占位符
     */
    public function info($msg, array $context = array())
    {
        $this->write($msg, $context, 'INFO');
    }

    /**
     * 打印debug日志
     * @param string $msg 日志信息
     * @param array $context 用上下文信息替换记录信息中的占位符
     */
    public function debug($msg, array $context = array())
    {
        $this->write($msg, $context, 'DEBUG');
    }

    /**
     * 打印sql日志
     * @param string $msg 日志信息
     * @param array $context 用上下文信息替换记录信息中的占位符
     */
    public function sql($msg, array $context = array())
    {
        $this->write($msg, $context, 'SQL');
    }
}