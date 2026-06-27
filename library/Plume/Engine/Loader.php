<?php

declare(strict_types=1);

class PlumeLoader
{
    /**
     * Registered classes.
     *
     * @var array<string, array{0: callable|string, 1: array<mixed>, 2: callable|null}>
     */
    protected array $classes = [];

    /**
     * Class instances.
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Autoload directories.
     *
     * @var string[]
     */
    protected static array $dirs = [];

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
     * @param callable|null   $callback Function to call after object instantiation
     */
    /**
     * @param array<mixed> $params
     */
    public function register(string $name, mixed $class, array $params = [], ?callable $callback = null): void
    {
        unset($this->instances[$name]);
        $this->classes[$name] = [$class, $params, $callback];
    }

    /**
     * Unregister a class.
     *
     * @param string $name Registry name
     */
    public function unregister(string $name): void
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
     * @return mixed Class instance or null
     */
    public function load(string $name, bool $shared = true): mixed
    {
        $obj = null;
        if (isset($this->classes[$name])) {
            [$class, $params, $callback] = $this->classes[$name];
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
     * @return mixed Class instance or null
     */
    public function getInstance(string $name): mixed
    {
        return isset($this->instances[$name]) ? $this->instances[$name] : null;
    }

    /**
     * Gets a new instance of a class.
     *
     * @param mixed        $class  Class name or callback function to instantiate class
     * @param array<mixed> $params Class initialization parameters
     *
     * @throws \Exception
     *
     * @return mixed Class instance
     */
    public function newInstance(mixed $class, array $params = []): mixed
    {
        if (is_callable($class)) {
            return call_user_func_array($class, $params);
        }

        return new $class(...$params);
    }

    /**
     * @param string $name Registry name
     *
     * @return mixed Class information or null if not registered
     */
    public function get(string $name): mixed
    {
        return isset($this->classes[$name]) ? $this->classes[$name] : null;
    }

    /**
     * Resets the object to the initial state.
     * Note: static $dirs is intentionally NOT reset — autoload paths are process-global
     * and shared across all engine instances (including worker-mode resets).
     */
    public function reset(): void
    {
        $this->classes = [];
        $this->instances = [];
    }

    // Autoloading Functions

    /**
     * Starts/stops autoloader.
     *
     * @param bool  $enabled Enable/disable autoloading
     * @param mixed $dirs    Autoload directories
     */
    public static function autoload(bool $enabled = true, mixed $dirs = []): void
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
    public static function loadClass(string $class): void
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
    public static function addDirectory(mixed $dir): void
    {
        if (is_iterable($dir)) {
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
