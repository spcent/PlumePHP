<?php

declare(strict_types=1);

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
    public function register(string $name, $class, array $params = [], ?callable $callback = null)
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
