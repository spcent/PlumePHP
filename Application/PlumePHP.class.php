<?php
// 系统初始时间
$run_start_time = microtime(1);
// 数据库查询次数
$run_dbquery_count = 0;

// 系统常量定义
defined('PLUME_PHP_PATH') or define('PLUME_PHP_PATH', __DIR__.'/');
define('IS_CGI', substr(PHP_SAPI, 0,3)=='cgi' ? 1 : 0 );
define('IS_WIN', strstr(PHP_OS, 'WIN') ? 1 : 0 );
define('IS_CLI', PHP_SAPI=='cli'? 1   :   0);
define('SITE_DOMAIN', strip_tags($_SERVER['HTTP_HOST']));
define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);
define('IS_GET', REQUEST_METHOD =='GET' ? true : false);
define('IS_POST', REQUEST_METHOD =='POST' ? true : false);
define('IS_AJAX', (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ? true : false);

defined('ENVIRONMENT') or define('ENVIRONMENT', 'production');
switch (ENVIRONMENT) {
case 'development':
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
break;
case 'testing':
case 'production':
    error_reporting(-1);
    ini_set('display_errors', 0);
break;
default:
    header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
    exit('应用程序配置不正确.');
}

/**
 * 统计运行时间和DB查询
 */
function runInfo($output = true) {
    $opstring = 'Total time:' . (microtime(1) - $GLOBALS['run_start_time']) * 1000 . 'ms DB Query:' . $GLOBALS['run_dbquery_count'];
    if ($output) {
        echo $opstring;
    } else {
        return $opstring;
    }
}
/**
 * 获取和设置配置参数 支持批量定义
 * 如果$key是关联型数组，则会按K-V的形式写入配置
 * 如果$key是数字索引数组，则返回对应的配置数组
 * @param string|array $key 配置变量
 * @param array|null $value 配置值
 * @return array|null
 */
function C($key, $value = null) {
    static $_config = array();
    $args = func_num_args();
    if ($args == 1) {
        if (is_string($key)) {  //如果传入的key是字符串
            return isset($_config[$key]) ? $_config[$key] : null;
        }
        
        if (is_array($key)) {
            if (array_keys($key) !== range(0, count($key) - 1)) {  //如果传入的key是关联数组
                $_config = array_merge($_config, $key);
            } else {
                $ret = array();
                foreach ($key as $k) {
                    $ret[$k] = isset($_config[$k]) ? $_config[$k] : null;
                }
                return $ret;
            }
        }
    } else {
        if (is_string($key)) {
            $_config[$key] = $value;
        } else {
            halt('传入参数不正确');
        }
    }
    return null;
}

/**
 * 调用Widget
 * @param string $name widget名
 * @param array $data 传递给widget的变量列表，key为变量名，value为变量值
 * @return void
 */
function W($name, $data = array()) {
    $fullName = $name . 'Widget';
    if (!class_exists($fullName)) {
        halt('Widget ' . $name . '不存在');
    }
    $widget = new $fullName();
    $widget->invoke($data);
}

/**
 * 终止程序运行
 * @param string $str 终止原因
 * @param bool $display 是否显示调用栈，默认不显示
 * @return void
 */
function halt($str, $display = false) {
    Log::fatal($str);
    header("Content-Type:text/html; charset=utf-8");
    if ($display) {
        echo "<pre>";
        debug_print_backtrace();
        echo "</pre>";
    }
    echo $str;
    exit;
}

/**
 * 获取数据库实例
 * @return DB
 */
function M() {
    $dbConf = C(array('DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PWD', 'DB_NAME', 'DB_CHARSET'));
    return DB::getInstance($dbConf);
}

// 生成链接
function U($route, array $params = array()) {
    $url = '/index.php';
    $pathMod = C('PATH_MOD');
    $pathMod = empty($pathMod) ? 'NORMAL' : $pathMod;
    $route = explode('/', trim($route, '/'));
    $controller = 'index';
    if (isset($route[0]) && $route[0] != '') {
        $controller = $route[0];
    }

    $action = 'index';
    if (isset($route[1]) && $route[1] != '') {
        $action = $route[1];
    }
    
    if (strcmp(strtoupper($pathMod), 'NORMAL') === 0 || !isset($_SERVER['PATH_INFO'])) {
        $params['c'] = $controller;
        $params['a'] = $action;
        $url .= '?'.http_build_query($params, '', '&');
    } else {
        $url .= '/'.$controller.'/'.$action;
        if (!empty($params)) $url .= '?'.http_build_query($params, '', '&');
    }
    
    return $url;
}

/**
 * 如果文件存在就include进来
 * @param string $path 文件路径
 * @return void
 */
function includeIfExist($path) {
    if (file_exists($path)) {
        include $path;
    }
}

/**
 * 总控类
 */
class PlumePHP {
    /**
     * 控制器
     * @var string
     */
    private $c;
    /**
     * Action
     * @var string
     */
    private $a;
    /**
     * 单例
     * @var PlumePHP
     */
    private static $_instance;

    /**
     * 构造函数，初始化配置
     * @param array $conf
     */
    private function __construct($conf) {
        C($conf);
    }
    private function __clone(){}
    /**
     * 获取单例
     * @param array $conf
     * @return PlumePHP
     */
    public static function getInstance($conf) {
        if (!isset($conf['APP_PATH'])) {
            $conf['APP_PATH'] = 'Application';
            if (!file_exists(getcwd() . '/' . $conf['APP_PATH'])) {
                halt('应用目录(APP_PATH)不存在!');
            }
        }
        C('APP_FULL_PATH', getcwd() . '/' . $conf['APP_PATH']);
        C('PUBLIC_PATH', getcwd() . '/public');
        if (file_exists(C('APP_FULL_PATH') . '/Conf/config.php')) {
            $config = require C('APP_FULL_PATH') . '/Conf/config.php';
            if (file_exists(C('APP_FULL_PATH') . '/Conf/config-'.ENVIRONMENT.'.php')) {
                $envconf = require C('APP_FULL_PATH') . '/Conf/config-'.ENVIRONMENT.'.php';
                $conf = array_merge($config, $envconf, $conf);
            } else {
                $conf = array_merge($config, $conf);
            }
        }
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self($conf);
        }
        return self::$_instance;
    }
    /**
     * 运行应用实例
     * @access public
     * @return void
     */
    public function run() {
        if (C('USE_SESSION') == true) {
            session_start();
        }

        includeIfExist(C('APP_FULL_PATH') . '/common.php');
        $pathMod = C('PATH_MOD');
        $pathMod = empty($pathMod) ? 'NORMAL' : $pathMod;
        // 注册AUTOLOAD方法
        spl_autoload_register(array('PlumePHP', 'autoload'));
        // 设定错误和异常处理
        register_shutdown_function(array('PlumePHP', 'fatalError'));
        if (strcmp(strtoupper($pathMod), 'NORMAL') === 0 || !isset($_SERVER['PATH_INFO'])) {
            $this->c = isset($_GET['c']) ? $_GET['c'] : 'Index';
            $this->a = isset($_GET['a']) ? $_GET['a'] : 'Index';
        } else {
            $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
            $pathInfoArr = explode('/', trim($pathInfo, '/'));
            if (isset($pathInfoArr[0]) && $pathInfoArr[0] !== '') {
                $this->c = $pathInfoArr[0];
            } else {
                $this->c = 'Index';
            }
            
            if (isset($pathInfoArr[1])) {
                $this->a = $pathInfoArr[1];
            } else {
                $this->a = 'Index';
            }
        }

        // 保存controller和action到CONTROLLER，ACTION，用于全局访问
        C('CONTROLLER', ucfirst($this->c));
        C('ACTION', ucfirst($this->a));

        $this->c = ucfirst($this->c);
        if (!class_exists($this->c . 'Controller')) {
            halt('控制器' . $this->c . '不存在');
        }
        
        $controllerClass = $this->c . 'Controller';
        $controller = new $controllerClass();

        $this->a = ucfirst($this->a);
        if (!method_exists($controller, $this->a . 'Action')) {
            halt('方法' . $this->a . '不存在');
        }

        call_user_func(array($controller, $this->a . 'Action'));
    }
    /**
     * 自动加载函数
     * @param string $class 类名
     */
    public static function autoload($class) {
        if (substr($class, -10) == 'Controller') {
            includeIfExist(C('APP_FULL_PATH') . '/Controller/' . $class . '.class.php');
        } elseif (substr($class, -6) == 'Widget') {
            includeIfExist(C('APP_FULL_PATH') . '/Widget/' . $class . '.class.php');
        } else {
            if (!file_exists(C('APP_FULL_PATH') . '/Lib/' . $class . '.class.php')) {
                // 文件名称可能全部小写
                $class = strtolower($class);
            }
            includeIfExist(C('APP_FULL_PATH') . '/Lib/' . $class . '.class.php');
        }
    }
    // 致命错误捕获
    public static function fatalError() {
        Log::save();
        if ($e = error_get_last()) {
            switch ($e['type']) {
              case E_ERROR:
              case E_PARSE:
              case E_CORE_ERROR:
              case E_COMPILE_ERROR:
              case E_USER_ERROR:  
                ob_end_clean();
                halt(var_export($e, true));
                break;
            }
        }
    }
}

/**
 * 控制器类
 */
class Controller {
    /**
     * 视图实例
     * @var View
     */
    private $_view;

    /**
     * 构造函数，初始化视图实例，调用hook
     */
    public function __construct() {
        $this->_view = new View();
        $this->_init();
    }
    /**
     * 前置hook
     */
    protected function _init(){}
    /**
     * 渲染模板并输出
     * @param null|string $tpl 模板文件路径
     * 参数为相对于App/View/文件的相对路径，不包含后缀名，例如index/index
     * 如果参数为空，则默认使用$controller/$action.php
     * 如果参数不包含"/"，则默认使用$controller/$tpl
     * @return void
     */
    protected function display($tpl = '') {
        if ($tpl === '') {
            $trace = debug_backtrace();
            $controller = substr($trace[1]['class'], 0, -10);
            $action = substr($trace[1]['function'], 0 , -6);
            $tpl = $controller . '/' . $action;
        } elseif(strpos($tpl, '/') === false) {
            $trace = debug_backtrace();
            $controller = substr($trace[1]['class'], 0, -10);
            $tpl = $controller . '/' . $tpl;
        }
        $this->_view->display($tpl);
    }
    /**
     * 为视图引擎设置一个模板变量
     * @param string $name 要在模板中使用的变量名
     * @param mixed $value 模板中该变量名对应的值
     * @return void
     */
    protected function assign($name, $value) {
        $this->_view->assign($name, $value);
    }
    /**
     * 将数据用json格式输出至浏览器，并停止执行代码
     * @param array $data 要输出的数据
     */
    protected function ajaxReturn($data) {
        echo json_encode($data);
        exit;
    }
}

/**
 * 视图类
 */
class View {
    /**
     * 视图文件目录
     * @var string
     */
    private $_tplDir;
    /**
     * 视图文件路径
     * @var string
     */
    private $_viewPath;
    /**
     * 视图变量列表
     * @var array
     */
    private $_data = array();
    /**
     * 给tplInclude用的变量列表
     * @var array
     */
    private static $tmpData;

    /**
     * @param string $tplDir
     */
    public function __construct($tplDir = '') {
        if ($tplDir == '') {
            $this->_tplDir = C('APP_FULL_PATH') . '/View/';
        }else{
            $this->_tplDir = $tplDir;
        }
    }
    /**
     * 为视图引擎设置一个模板变量
     * @param string $key 要在模板中使用的变量名
     * @param mixed $value 模板中该变量名对应的值
     * @return void
     */
    public function assign($key, $value) {
        $this->_data[$key] = $value;
    }
    /**
     * 渲染模板并输出
     * @param null|string $tplFile 模板文件路径，相对于App/View/文件的相对路径，不包含后缀名，例如index/index
     * @return void
     */
    public function display($tplFile) {
        $this->_viewPath = $this->_tplDir . $tplFile . '.php';
        unset($tplFile);
        extract($this->_data);
        include $this->_viewPath;
    }
    /**
     * 用于在模板文件中包含其他模板
     * @param string $path 相对于View目录的路径
     * @param array $data 传递给子模板的变量列表，key为变量名，value为变量值
     * @return void
     */
    public static function tplInclude($path, $data=array()) {
        self::$tmpData = array(
            'path' => C('APP_FULL_PATH') . '/View/' . $path . '.php',
            'data' => $data,
        );
        unset($path);
        unset($data);
        extract(self::$tmpData['data']);
        include self::$tmpData['path'];
    }
}

/**
 * Widget类
 * 使用时需继承此类，重写invoke方法，并在invoke方法中调用display
 */
class Widget {
    /**
     * 视图实例
     * @var View
     */
    protected $_view;
    /**
     * Widget名
     * @var string
     */
    protected $_widgetName;

    /**
     * 构造函数，初始化视图实例
     */
    public function __construct() {
        $this->_widgetName = get_class($this);
        $dir = C('APP_FULL_PATH') . '/Widget/Tpl/';
        $this->_view = new View($dir);
    }

    /**
     * 处理逻辑
     * @param mixed $data 参数
     */
    public function invoke($data) {}

    /**
     * 渲染模板
     * @param string $tpl 模板路径，如果为空则用类名作为模板名
     */
    protected function display($tpl = '') {
        if ($tpl == '') {
            $tpl = $this->_widgetName;
        }
        $this->_view->display($tpl);
    }

    /**
     * 为视图引擎设置一个模板变量
     * @param string $name 要在模板中使用的变量名
     * @param mixed $value 模板中该变量名对应的值
     * @return void
     */
    protected function assign($name, $value) {
        $this->_view->assign($name, $value);
    }
}

/**
 * 数据库操作类
 * 使用方法：
 * DB::getInstance($conf)->query('select * from table');
 * 其中$conf是一个关联数组，需要包含以下key：
 * DB_HOST DB_USER DB_PWD DB_NAME
 * 可以用DB_PORT和DB_CHARSET来指定端口和编码，默认3306和utf8
 */
class DB {
    /**
     * 数据库链接
     * @var resource
     */
    private $_db;
    /**
     * 保存最后一条sql
     * @var string
     */
    private $_lastSql;
    /**
     * 上次sql语句影响的行数
     * @var int
     */
    private $_rows;
    /**
     * 上次sql执行的错误
     * @var string
     */
    private $_error;
    /**
     * 实例数组
     * @var array
     */
    private static $_instance = array();

    /**
     * 构造函数
     * @param array $dbConf 配置数组
     */
    private function __construct($dbConf) {
        if (!isset($dbConf['DB_CHARSET'])) {
            $dbConf['DB_CHARSET'] = 'utf8';
        }
        $this->_db = @mysql_connect($dbConf['DB_HOST'] . ':' . $dbConf['DB_PORT'], $dbConf['DB_USER'], $dbConf['DB_PWD']);
        if ($this->_db === false) {
            halt(mysql_error());
        }
        $selectDb = mysql_select_db($dbConf['DB_NAME'], $this->_db);
        if ($selectDb === false) {
            halt(mysql_error());
        }
        mysql_set_charset($dbConf['DB_CHARSET']);
    }
    private function __clone() {}

    /**
     * 获取DB类
     * @param array $dbConf 配置数组
     * @return DB
     */
    static public function getInstance($dbConf) {
        if (!isset($dbConf['DB_PORT'])) {
            $dbConf['DB_PORT'] = '3306';
        }
        $key = $dbConf['DB_HOST'] . ':' . $dbConf['DB_PORT'];
        if (!isset(self::$_instance[$key]) || !(self::$_instance[$key] instanceof self)) {
            self::$_instance[$key] = new self($dbConf);
        }
        return self::$_instance[$key];
    }
    /**
     * 转义字符串
     * @param string $str 要转义的字符串
     * @return string 转义后的字符串
     */
    public function escape($str) {
        return mysql_real_escape_string($str, $this->_db);
    }
    /**
     * 查询，用于select语句
     * @param string $sql 要查询的sql
     * @return bool|array 查询成功返回对应数组，失败返回false
     */
    public function query($sql) {
        $this->_rows = 0;
        $this->_error = '';
        $this->_lastSql = $sql;
        //$this->logSql();
        $res = mysql_query($sql, $this->_db);
        if ($res === false) {
            $this->_error = mysql_error($this->_db);
            $this->logError();
            return false;
        } else {
            $this->_rows = mysql_num_rows($res);
            $result = array();
            if ($this->_rows >0) {
                while ($row = mysql_fetch_array($res, MYSQL_ASSOC)) {
                    $result[] = $row;
                }
                mysql_data_seek($res, 0);
            }
            // 统计db查询
            $GLOBALS['run_dbquery_count'] = $GLOBALS['run_dbquery_count'] + 1;
            return $result;
        }
    }
    /**
     * 查询，用于insert/update/delete语句
     * @param string $sql 要查询的sql
     * @return bool|int 查询成功返回影响的记录数量，失败返回false
     */
    public function execute($sql) {
        $this->_rows = 0;
        $this->_error = '';
        $this->_lastSql = $sql;
        //$this->logSql();
        $result = mysql_query($sql, $this->_db) ;
        if (false === $result) {
            $this->_error = mysql_error($this->_db);
            $this->logError();
            return false;
        } else {
            $this->_rows = mysql_affected_rows($this->_db);
            return $this->_rows;
        }
    }
    /**
     * 获取上一次查询影响的记录数量
     * @return int 影响的记录数量
     */
    public function getRows() {
        return $this->_rows;
    }
    /**
     * 获取上一次insert后生成的自增id
     * @return int 自增ID
     */
    public function getInsertId() {
        return mysql_insert_id($this->_db);
    }
    /**
     * 获取上一次查询的sql
     * @return string sql
     */
    public function getLastSql() {
        return $this->_lastSql;
    }
    /**
     * 获取上一次查询的错误信息
     * @return string 错误信息
     */
    public function getError() {
        return $this->_error;
    }
    /**
     * 记录sql到文件
     */
    private function logSql() {
        Log::sql($this->_lastSql);
    }
    /**
     * 记录错误日志到文件
     */
    private function logError() {
        $str = '[SQL ERR]' . $this->_error . ' SQL:' . $this->_lastSql;
        Log::warn($str);
    }
}

/**
 * 日志类
 * 使用方法：Log::fatal('error msg');
 * 保存路径为 App/Log，按天存放
 * fatal和warning会记录在.log.wf文件中
 */
class Log {
    // 日志信息
    static protected $log = array();
    
    /**
     * 打日志，支持SAE环境
     * @param string $msg 日志内容
     * @param string $level 日志等级
     */
    public static function write($msg, $level = 'DEBUG', $wf = false) {
        if (function_exists('sae_debug')){ //如果是SAE，则使用sae_debug函数打日志
            $msg = "[{$level}]".$msg;
            sae_set_display_errors(false);
            sae_debug(trim($msg));
            sae_set_display_errors(true);
        } else {
            $log_message = date('[ Y-m-d H:i:s ]') . "[{$level}]" . $msg . "\r\n";
            if ($wf) {
                $logPath = C('APP_FULL_PATH') . '/Log/' . date('Ymd') . '-error.log';
                file_put_contents($logPath, $log_message, FILE_APPEND);
            } else {
                self::$log[] = $log_message;
            }  
        }
    }
    /**
     * 日志保存
     * @static
     * @access public
     * @return void
     */
    public static function save() {
        if (empty(self::$log)) return;
        $msg = implode('', self::$log);
        $logPath = C('APP_FULL_PATH') . '/Log/' . date('Ymd') . '.log';
        file_put_contents($logPath, $msg, FILE_APPEND);
        // 保存后清空日志缓存
        self::$log = array();
    }    
    /**
     * 打印fatal日志
     * @param string $msg 日志信息
     */
    public static function fatal($msg) {
        self::write($msg, 'FATAL', true);
    }
    /**
     * 打印warning日志
     * @param string $msg 日志信息
     */
    public static function warn($msg) {
        self::write($msg, 'WARN', true);
    }
    /**
     * 打印notice日志
     * @param string $msg 日志信息
     */
    public static function notice($msg) {
        self::write($msg, 'NOTICE');
    }
    /**
     * 打印debug日志
     * @param string $msg 日志信息
     */
    public static function debug($msg) {
        self::write($msg, 'DEBUG');
    }
    /**
     * 打印sql日志
     * @param string $msg 日志信息
     */
    public static function sql($msg) {
        self::write($msg, 'SQL');
    }
}