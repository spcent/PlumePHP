<?php

declare(strict_types=1);

class PlumeRouter
{
    /**
     * Case sensitive matching.
     */
    public bool $case_sensitive = false;

    /** Path to the compiled-route cache file; null = caching disabled. */
    private ?string $cacheFile = null;

    /** Whether we have already loaded (or built) the cache this request. */
    private bool $cacheLoaded = false;

    /**
     * Mapped routes.
     *
     * @var PlumeRoute[]
     */
    protected array $routes = [];

    /**
     * Pointer to current route.
     */
    protected int $index = 0;

    /** Returns the current PlumeRequest (injected by Engine; falls back to facade). */
    private \Closure $requestProvider;

    /** Returns the current PlumeResponse (injected by Engine; falls back to facade). */
    private \Closure $responseProvider;

    public function __construct(?\Closure $requestProvider = null, ?\Closure $responseProvider = null)
    {
        $this->requestProvider  = $requestProvider  ?? static fn() => \PlumePHP::request();
        $this->responseProvider = $responseProvider ?? static fn() => \PlumePHP::response();
    }

    /**
     * Gets mapped routes.
     *
     * @return PlumeRoute[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Clears all routes in the router.
     */
    public function clear(): void
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
    public function map(string $pattern, callable $callback, bool $pass_route = false): void
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
    /**
     * Enables compiled regex caching to a PHP file.
     * Must be called after routes are registered, before start().
     */
    public function enableCache(string $cacheFile): void
    {
        $this->cacheFile = $cacheFile;
    }

    public function route(PlumeRequest $request): PlumeRoute|false
    {
        if ($this->cacheFile !== null && !$this->cacheLoaded) {
            $this->cacheLoaded = true;
            if (!$this->loadCache()) {
                $this->precompile();
                $this->saveCache();
            }
        }

        while ($route = $this->current()) {
            if (false !== $route && $route->matchMethod($request->method)
                && $route->matchUrl($request->url, $this->case_sensitive)) {
                return $route;
            }
            $this->next();
        }

        return false;
    }

    /** Pre-compiles regex for every registered route and persists to file. */
    private function precompile(): void
    {
        foreach ($this->routes as $route) {
            $route->compile();
        }
    }

    /** Loads compiled regex from the cache file and applies to registered routes. */
    private function loadCache(): bool
    {
        if ($this->cacheFile === null || !file_exists($this->cacheFile)) {
            return false;
        }
        $data = include $this->cacheFile;
        if (!is_array($data)) {
            return false;
        }
        foreach ($this->routes as $route) {
            $key = $route->pattern;
            if (isset($data[$key])) {
                $route->setCompiled($data[$key]['regex'], $data[$key]['ids']);
            }
        }
        return true;
    }

    /** Writes all compiled regex patterns to the cache file atomically. */
    private function saveCache(): void
    {
        if ($this->cacheFile === null) {
            return;
        }
        $data = [];
        foreach ($this->routes as $route) {
            [$regex, $ids] = $route->compile();
            $data[$route->pattern] = ['regex' => $regex, 'ids' => $ids];
        }
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0700, true);
        }
        $tmp = $this->cacheFile . '.' . getmypid() . '.tmp';
        file_put_contents(
            $tmp,
            '<?php return ' . var_export($data, true) . ';' . PHP_EOL,
            LOCK_EX
        );
        rename($tmp, $this->cacheFile);
    }

    /**
     * Gets the current route.
     *
     * @return PlumeRoute
     */
    public function current(): PlumeRoute|false
    {
        return $this->routes[$this->index] ?? false;
    }

    /**
     * Advances the route pointer to the next route.
     *
     * @return void
     */
    public function next()
    {
        $this->index++;
    }

    /**
     * Reset to the first route.
     */
    public function reset(): void
    {
        $this->index = 0;
    }

    /**
     * Register a group of routes sharing a common prefix and optional middleware.
     *
     * Usage:
     *   $router->group('/api', function(PlumeRouter $r) {
     *       $r->map('GET /users', ...);
     *       $r->map('POST /users', ...);
     *   }, ['AuthMiddleware', 'RateLimitMiddleware']);
     *
     * Each callback inside the closure inherits the prefix and is wrapped with
     * the supplied middleware stack automatically.
     *
     * @param string                                       $prefix      URL prefix applied to every route in the group
     * @param callable                                     $callback    Receives $this router; registers child routes
     * @param array<class-string<PlumeMiddlewareInterface>> $middlewares Array of PlumeMiddlewareInterface class names
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $countBefore = count($this->routes);

        $callback($this);

        $countAfter = count($this->routes);

        if (empty($middlewares)) {
            // Just apply the prefix to newly added routes
            for ($i = $countBefore; $i < $countAfter; $i++) {
                $this->routes[$i] = $this->routes[$i]->withPrefix($prefix);
            }
            return;
        }

        // Wrap each new route's callback in the middleware stack
        $responseProvider = $this->responseProvider;
        $requestProvider  = $this->requestProvider;
        for ($i = $countBefore; $i < $countAfter; $i++) {
            $route    = $this->routes[$i]->withPrefix($prefix);
            $original = $route->callback;
            $stack    = array_reverse($middlewares);
            $handler  = new class($original, $responseProvider) implements PlumeRequestHandlerInterface {
                public function __construct(private mixed $cb, private \Closure $rp) {}
                public function handle(PlumeRequest $request): PlumeResponse
                {
                    ($this->cb)($request);
                    return ($this->rp)();
                }
            };
            foreach ($stack as $mwClass) {
                $mw      = new $mwClass();
                $handler = new class($mw, $handler) implements PlumeRequestHandlerInterface {
                    public function __construct(
                        private PlumeMiddlewareInterface $mw,
                        private PlumeRequestHandlerInterface $next
                    ) {}
                    public function handle(PlumeRequest $request): PlumeResponse
                    {
                        return $this->mw->process($request, $this->next);
                    }
                };
            }
            $finalHandler     = $handler;
            $this->routes[$i] = $route->withCallback(function () use ($finalHandler, $requestProvider) {
                $finalHandler->handle(($requestProvider)());
            });
        }
    }
}
/**
 * The PlumeView class represents output to be displayed. It provides
 * methods for managing view data and inserts the data into view templates upon rendering.
 */
