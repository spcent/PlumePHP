<?php

declare(strict_types=1);

/**
 * The plume engine.
 *
 * @method PlumeRequest  request(bool $shared = true)
 * @method PlumeResponse response(bool $shared = true)
 * @method PlumeView     view(bool $shared = true)
 * @method PlumeRouter   router(bool $shared = true)
 * @method PlumeLogger   logger(bool $shared = true)
 * @method void          start()
 * @method void          stop(?int $code = null)
 * @method void          route(string $pattern, callable $callback, bool $pass_route = false)
 * @method never         halt(int $code = 200, string $message = '')
 * @method void          error(\Throwable $e)
 * @method void          notFound()
 * @method void          render(string $file, ?array $data = null, ?string $key = null, string $layout = '')
 * @method void          json(mixed $data, int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = JSON_UNESCAPED_UNICODE)
 * @method void          jsonp(mixed $data, string $param = 'jsonp', int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = JSON_UNESCAPED_UNICODE)
 * @method void          log(string $msg, array $context = [], string $level = 'DEBUG', bool $wf = false)
 * @method mixed         biz(array $params = [])
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

    /** @var PlumeMiddlewareInterface[] */
    protected array $middlewares = [];

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

    /**
     * Exposes the internal event dispatcher (used by PlumePHP::__callStatic hot path).
     */
    public function getDispatcher(): PlumeEvent
    {
        return $this->dispatcher;
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
            $this->middlewares = [];
        }

        // Register default components
        $this->loader->register('request', 'PlumeRequest');
        $this->loader->register('response', 'PlumeResponse');
        $this->loader->register('view', 'PlumeView', [], function ($view) use ($self) {
            $view->path = $self->get('plumephp.views.path');
            $view->extension = $self->get('plumephp.views.extension');
            $view->variableResolver = fn(string $k): mixed => $self->get($k);
        });
        $this->loader->register('router', 'PlumeRouter', [
            fn() => $self->request(),
            fn() => $self->response(),
        ]);
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
                set_exception_handler(function (\Throwable $e) use ($self): void {
                    $self->handleException($e);
                });
            }
            // Set case-sensitivity
            $self->router()->case_sensitive = $self->get('plumephp.case_sensitive');

            // Session management is done here rather than in boot() so that
            // persistent worker processes (FrankenPHP worker mode) restart the
            // session for every request after calling resetForWorker().
            if (!IS_CLI && true === C('USE_SESSION') && PHP_SESSION_NONE === session_status()
                && !headers_sent()) {
                session_start();
            }

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
     * @param \Throwable $e Thrown exception
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
     * @param callable $callback Callback function
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
     * @param string        $name     Method name
     * @param string        $class    Class name
     * @param array         $params   Class initialization parameters
     * @param callable|null $callback Function to call after object instantiation
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
     * @param callable $callback Callback function
     */
    public function before(string $name, $callback)
    {
        $this->dispatcher->hook($name, 'before', $callback);
    }

    /**
     * Adds a post-filter to a method.
     *
     * @param string   $name     Method name
     * @param callable $callback Callback function
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
    public function get(?string $key = null): mixed
    {
        if (null === $key) {
            return $this->vars;
        }

        return $this->vars[$key] ?? null;
    }

    /**
     * Sets a variable.
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
    public function clear(?string $key = null): void
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

    /**
     * Registers a PSR-15-style middleware to run before route dispatch.
     */
    /** @return PlumeMiddlewareInterface[] */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function addMiddleware(PlumeMiddlewareInterface $middleware): static
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * Returns a PSR-11 container wrapping the service registry.
     */
    public function container(): PlumeContainer
    {
        return new PlumeContainer($this->loader);
    }

    /**
     * Enables compiled route-regex caching to the given file path.
     * Call after all routes are registered, before start().
     */
    public function enableRouteCache(string $path): void
    {
        $this->router()->enableCache($path);
    }

    // Extensible Methods

    /**
     * Starts the framework engine.
     *
     * @throws \Exception
     */
    public function _start()
    {
        $self = $this;

        // Allow filters to run
        $this->after('start', function () use ($self) {
            $self->stop();
        });

        $request  = $this->request();
        $response = $this->response();
        $router   = $this->router();

        // Flush any existing output
        if (ob_get_length() > 0) {
            $response->write(ob_get_clean());
        }

        // Enable output buffering
        ob_start();

        if (!empty($this->middlewares)) {
            $pipeline = new PlumeMiddlewarePipeline();
            foreach ($this->middlewares as $m) {
                $pipeline->pipe($m);
            }
            $engine = $this;
            $pipeline->setFinalHandler(new class($engine, $router) implements PlumeRequestHandlerInterface {
                public function __construct(
                    private readonly PlumeEngine $engine,
                    private readonly PlumeRouter $router
                ) {}
                public function handle(PlumeRequest $request): PlumeResponse
                {
                    return $this->engine->_runRouteLoop($request, $this->router);
                }
            });
            $pipeline->handle($request);
        } else {
            $this->_runRouteLoop($request, $router);
        }
    }

    /**
     * Runs the route dispatch loop. Used by _start() and the middleware pipeline.
     */
    public function _runRouteLoop(PlumeRequest $request, PlumeRouter $router): PlumeResponse
    {
        $dispatched = false;
        while ($route = $router->route($request)) {
            $params = array_values($route->params);
            if ($route->pass) {
                $params[] = $route;
            }
            $continue = $this->dispatcher->execute($route->callback, $params);
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
        return $this->response();
    }

    /**
     * Stops the framework and outputs the current response.
     *
     * @param int $code HTTP status code
     *
     * @throws \Exception
     */
    public function _stop(?int $code = null)
    {
        $response = $this->response();
        if (!$response->sent()) {
            if (null !== $code) {
                $response->status($code);
            }

            // Release session file lock before flushing — prevents blocking all
            // subsequent requests when using PHP's built-in server (single-process).
            if (!IS_CLI && PHP_SESSION_ACTIVE === session_status()) {
                session_write_close();
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
     * @param callable $callback   Callback function
     * @param bool     $pass_route Pass the matching route object to the callback
     */
    public function _route(string $pattern, callable $callback, bool $pass_route = false)
    {
        $this->router()->map($pattern, $callback, $pass_route);
    }

    /**
     * Registers a group of routes sharing a URL prefix and optional middleware.
     *
     * @param string   $prefix      URL prefix for all routes in the group
     * @param callable $callback    Closure that receives the engine; registers routes via PlumePHP::route()
     * @param array    $middlewares PSR-15 middleware class names applied to every route in the group
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $this->router()->group($prefix, function (PlumeRouter $router) use ($callback) {
            $callback($this);
        }, $middlewares);
    }

    /**
     * Stops processing and returns a given response.
     *
     * @param int    $code    HTTP status code
     * @param string $message Response message
     * @return never
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
        } catch (\Throwable $t) {
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
    public function _render(string $file, ?array $data = null, ?string $key = null, string $layout = ''): void
    {
        if (null !== $key) {
            $this->view()->set($key, $this->view()->fetch($file, $data ?? [], $layout));
        } else {
            $this->view()->render($file, $data ?? [], $layout);
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
        $callback = $this->request()->query[$param] ?? '';
        if (!preg_match('/^[\w.]{1,64}$/', $callback)) {
            $this->response()->status(400)->write('Invalid callback parameter')->send();
            return;
        }
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
        $bizPathRaw = $ar->getValue('path');
        if (!$ar->has('path') || !$bizPathRaw) {
            throw new \Exception('Wrong parameter format, missing path');
        }

        // Special character processing
        $bizPath = str_replace('..', '', $bizPathRaw);
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
     * Delegates to ActionResolver (URL parsing), ActionLocator (filesystem),
     * ActionNaming (class name), and ActionInvoker (instantiation + run).
     */
    public function runAction()
    {
        $startTime  = microtime(true);
        $requestUri = $_SERVER['REQUEST_URI'];

        // 1. Parse URL → module, pathnames, initial GET args
        $parsed = ActionResolver::parse(
            $requestUri,
            (string) (C('VDNAME') ?? ''),
            C('PATH_ALIAS')
        );

        $pathnames = $parsed['pathnames'];
        $args      = $parsed['args'];
        $urlPath   = $parsed['urlPath'];

        $defaultModule = $this->get('plumephp.default.module') ?? 'web';
        $module        = ActionResolver::extractModule($pathnames, $defaultModule);
        $module        = trim($module);

        $this->set('plumephp.module', $module);
        $this->set('plumephp.urlPath', $urlPath);

        // 2. Load module boot file
        I(APP_PATH . DS . $module . DS . $module . '.boot.php');

        // Handle bare /  or /index.php → serve "index" action directly
        $firstSeg = trim($pathnames[1] ?? '');
        if ($firstSeg === '' || $firstSeg === 'index.php') {
            $file       = 'index';
            $actionFile = APP_PATH . DS . $module . DS . 'actions' . DS . 'index.action.php';
            $stopIndex  = 1;
            if (!file_exists($actionFile)) {
                $this->_halt(404, '!!! 404(missing index action) !!! uri: ' . $requestUri);
            }
            require $actionFile;
        } else {
            // 3. Locate action file by walking URL segments
            try {
                $located = ActionLocator::locate($module, $pathnames, $requestUri);
            } catch (ActionException $e) {
                $this->_halt($e->getHttpCode(), $e->getMessage());
            }
            $file      = $located['file'];
            $actionFile = $located['actionFile'];
            $stopIndex  = $located['stopIndex'];

            require $actionFile;
        }

        // 4. Collect tail key→value pairs from remaining URL segments
        $args = ActionResolver::collectTailArgs($pathnames, $stopIndex, $args);

        $this->set('plumephp.file', $file);
        $this->set('plumephp.args', $args);

        // 5. Resolve class name (legacy or PSR-4)
        $className = ActionNaming::resolve($module, $file);

        L('[web]class name:{class}, args:{args}, request:{req}', [
            'class' => $className,
            'args'  => json_encode($args, JSON_UNESCAPED_UNICODE),
            'req'   => json_encode(ActionInvoker::sanitizeForLog($_REQUEST), JSON_UNESCAPED_UNICODE),
        ]);

        // 6. Instantiate and run
        try {
            $res = ActionInvoker::invoke($className, $requestUri);
        } catch (ActionException $e) {
            $this->_halt($e->getHttpCode(), $e->getMessage());
        }

        L('[web]class name: {class} success, result: {result}, cost: {cost}s', [
            'class'  => $className,
            'result' => substr(json_encode($res, JSON_UNESCAPED_UNICODE), 0, 200),
            'cost'   => round(microtime(true) - $startTime, 3),
        ]);

        return $res;
    }

    /**
     * boot.
     */
    protected function boot()
    {
        // Load .env file if it exists, or use defaults for testing/CI environments
        $envFile = PLUME_PHP_PATH.DS.'.env';
        $envVariables = file_exists($envFile) ? PlumeDotEnv::parse($envFile) : [];

        if (isset($envVariables['PLUME_PHP_ENV'])) {
            $env = $envVariables['PLUME_PHP_ENV'];
        } else {
            $env = getenv('PLUME_PHP_ENV')
                ? getenv('PLUME_PHP_ENV')
                : (get_cfg_var('plumephp.env') ?: 'development');
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

        // Session management moved to the before('start') hook in init() so it
        // runs per-request in persistent worker processes (see resetForWorker).

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

        // Snapshot boot-time config so resetForWorker() can restore it,
        // preventing per-request C() writes from leaking across requests
        // in persistent worker processes (FrankenPHP, RoadRunner).
        C("\x00snapshot_take\x00");
    }
}
/**
 * Secure Request object.
 */
