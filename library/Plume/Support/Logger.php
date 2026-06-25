<?php

declare(strict_types=1);

class PlumeLogger implements \Psr\Log\LoggerInterface
{
    protected array $log = [];

    /** @var array<callable(string $level, string $message, array $context): void> */
    private array $handlers = [];

    public function __construct(
        protected string $logId = '',
        protected string $logPath = ''
    ) {
        if (!$this->logPath) {
            $this->logPath = C('PLUME_LOG_PATH') ?: LOG_PATH;
        }
    }

    public function __destruct()
    {
        $this->save();
    }

    /**
     * Register an external log handler called after every write.
     * Signature: function(string $level, string $message, array $context): void
     */
    public function addHandler(callable $handler): self
    {
        $this->handlers[] = $handler;
        return $this;
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
    public function write(\Stringable|string $msg, array $context = [], string $level = 'DEBUG', bool $wf = false): void
    {
        $msg = (string) $msg;
        if ($msg === '') {
            return;
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
        $upperLevel  = strtoupper($level);

        match(true) {
            $upperLevel === 'SQL' => file_put_contents($this->logPath.DS.date('Ymd').'.log.sql', $log_message, FILE_APPEND | LOCK_EX),
            $wf                  => file_put_contents($this->logPath.DS.date('Ymd').'.log.wf', $log_message, FILE_APPEND | LOCK_EX),
            default              => ($this->log[] = $log_message),
        };

        foreach ($this->handlers as $handler) {
            $handler($level, $msg, $context);
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
    public function fatal(\Stringable|string $msg, array $context = []): void
    {
        $this->write($msg, $context, 'FATAL', true);
    }

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->write($message, $context, 'EMERGENCY', true);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->write($message, $context, 'ALERT', true);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->write($message, $context, 'CRITICAL', true);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->write($message, $context, 'ERROR', true);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->write($message, $context, 'WARNING', true);
    }

    /** Alias for warning() for backwards compatibility. */
    public function warn(\Stringable|string $msg, array $context = []): void
    {
        $this->warning($msg, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->write($message, $context, 'NOTICE');
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->write($message, $context, 'INFO');
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->write($message, $context, 'DEBUG');
    }

    /**
     * PSR-3 log() — dispatches to the appropriate severity method.
     */
    public function log(mixed $level, \Stringable|string $message, array $context = []): void
    {
        $levelStr = is_string($level) ? strtolower($level) : (string) $level;
        match($levelStr) {
            'emergency' => $this->emergency($message, $context),
            'alert'     => $this->alert($message, $context),
            'critical'  => $this->critical($message, $context),
            'error'     => $this->error($message, $context),
            'warning'   => $this->warning($message, $context),
            'notice'    => $this->notice($message, $context),
            'info'      => $this->info($message, $context),
            default     => $this->debug($message, $context),
        };
    }

    public function sql(\Stringable|string $msg, array $context = []): void
    {
        $this->write($msg, $context, 'SQL');
    }
}
/**
 * The base schema class of the PlumePHP framework.
 */
