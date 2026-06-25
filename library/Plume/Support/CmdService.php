<?php

declare(strict_types=1);

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

    public function init(array $options, ?object $context = null)
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
    public function run(): int
    {
        if (isset($this->args['version'])) {
            echo '➤ PlumePHP version: ', PLUME_VERSION, PHP_EOL;
            return 0;
        }

        $this->showWelcome();
        if (isset($this->args['help'])) {
            $this->showHelp();
            return 0;
        }

        if (isset($this->args['http'])) {
            $this->runHTTPServer();
            return 0;
        }

        if (empty($this->args['module'])) {
            $this->showHelp();
            return 0;
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
            return $this->error('Command file not found: '.$filename);
        }

        $className = $module.'_'.str_replace(['\\', '/'], '_', $file).'_cmd';

        // Loads the file.
        require $filename;

        L('[cli]class name: '.$className.', args: '.json_encode($this->args));

        if (!class_exists($className)) {
            return $this->error('Command class not found: '.$className);
        }

        $actionInstance = new $className();
        if (!method_exists($actionInstance, 'run')) {
            return $this->error('Command class missing run() method: '.$className);
        }

        $result = $actionInstance->run($this->args);
        return is_int($result) ? $result : 0;
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

    protected function error(string $msg): int
    {
        fwrite(STDERR, '➤ ERROR: ' . $msg . PHP_EOL);
        return 1;
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
        // Check for port conflict before attempting to bind.
        $sock = @fsockopen($this->host, (int) $this->port, $errno, $errstr, 1);
        if ($sock !== false) {
            fclose($sock);
            return $this->error("Port {$this->port} is already in use on {$this->host}");
        }

        $PHP           = escapeshellcmd(PHP_BINARY);
        $address       = escapeshellarg("{$this->host}:{$this->port}");
        $document_root = escapeshellarg($this->docroot);
        $router_script = escapeshellarg($this->docroot . DS . 'index.php');

        if (isset($this->args['background'])) {
            $this->options['background'] = true;
        }

        if ($this->options['background'] ?? false) {
            echo "➤ PlumePHP: RunServer by PHP inner http server {$this->host}:{$this->port}\n";
        }

        // The router script must be passed so all requests (including dynamic
        // routes) are dispatched through public/index.php instead of 404ing.
        $cmd = "{$PHP} -S {$address} -t {$document_root} {$router_script}";
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
