<?php

declare(strict_types=1);

class PlumeEvent
{
    /** @var array<string, mixed> Mapped events */
    protected array $events = [];

    /** @var array<string, array<string, mixed[]>> Method filters */
    protected array $filters = [];

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
    /**
     * @param mixed[] $params
     */
    public function run(string $name, array $params = []): mixed
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
     * @param string $name     Event name
     * @param mixed  $callback Callback function
     */
    public function set(string $name, mixed $callback): void
    {
        $this->events[$name] = $callback;
    }

    /**
     * Gets an assigned callback.
     *
     * @param string $name Event name
     *
     * @return callable|null Callback function
     */
    public function get(string $name): callable|null
    {
        $cb = $this->events[$name] ?? null;
        return is_callable($cb) ? $cb : null;
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
    public function clear(?string $name = null): void
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
     * @param callable $callback Callback function
     */
    public function hook(string $name, string $type, mixed $callback): void
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
    /**
     * @param callable[] $filters
     * @param mixed[]    $params
     */
    public function filter(array $filters, array &$params, mixed &$output): void
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
     * @param mixed $callback Callback function
     * @param array $params   Function parameters
     *
     * @throws \Exception
     *
     * @return mixed Function results
     */
    /**
     * @param mixed[] $params
     */
    public function execute(mixed $callback, array &$params = []): mixed
    {
        if (is_array($callback) && is_string($callback[0]) && isset($callback[1])) {
            $classname = $callback[0];
            $method = $callback[1];
            if (class_exists($classname)) {
                $r_method = new ReflectionMethod($classname, $method);
                if (!$r_method->isStatic()) {
                    $callback[0] = new $callback[0]();
                }
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
     * @param callable $func   Callable to invoke
     * @param mixed[]  $params Function parameters
     *
     * @return mixed Function results
     */
    public static function callFunction(callable $func, array &$params = []): mixed
    {
        return $func(...$params);
    }

    /**
     * Invokes a method.
     *
     * @param mixed $func   Class method
     * @param array $params Class method parameters
     *
     * @return mixed Function results
     */
    /**
     * @param mixed[] $params
     */
    public static function invokeMethod(mixed $func, array &$params = []): mixed
    {
        [$class, $method] = (array) $func;

        $instance = is_object($class);
        if (!$instance && method_exists($class, $method)) {
            $methodChecker = new \ReflectionMethod($class, $method);
            if (!$methodChecker->isStatic()) {
                $class = new $class();
                $instance = is_object($class);
            }
        }

        return $instance ? $class->{$method}(...$params) : $class::$method(...$params);
    }

    /**
     * Returns true when at least one before/after filter is registered for $name.
     * Used by PlumePHP::__callStatic to decide whether to use the fast path.
     */
    public function hasFilters(string $name): bool
    {
        return !empty($this->filters[$name]);
    }

    /**
     * Resets the object to the initial state.
     */
    public function reset(): void
    {
        $this->events = [];
        $this->filters = [];
    }
}
// View object
