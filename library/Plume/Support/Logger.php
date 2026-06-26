<?php

declare(strict_types=1);

class PlumeLogger implements \Psr\Log\LoggerInterface
{
    protected array $log = [];

    /** @var array<callable(string $level, string $message, array $context): void> */
    private array $handlers = [];

    /**
     * Output format: 'text' (default) or 'json'.
     * JSON format: {"time":"...","log_id":"...","level":"...","msg":"...","ctx":{...}}
     */
    private string $formatter = 'text';

    /**
     * Write mode: 'normal' (default) or 'batch'.
     * In batch mode ALL log entries (including wf-level errors) are buffered in
     * memory and flushed together at save() time — useful in Worker processes
     * where disk I/O per-request adds up.  NOTICE and fatal-class levels
     * ($wf=true) still receive their own flush at the end of each request.
     */
    private string $mode = 'normal';

    /** Buffered wf entries when mode=batch */
    private array $wfLog = [];

    public function __construct(
        protected string $logId = '',
        protected string $logPath = ''
    ) {
        if (!$this->logPath) {
            $this->logPath = C('PLUME_LOG_PATH') ?: LOG_PATH;
        }
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }

        // Flush buffered DEBUG/INFO/NOTICE logs on shutdown so fatal errors
        // (OOM, parse errors) do not silently discard in-flight log entries.
        // __destruct() is not guaranteed to run on fatal errors.
        register_shutdown_function([$this, 'save']);
    }

    public function __destruct()
    {
        $this->save();
    }

    /**
     * Set output format.
     *
     * @param string $format 'text' or 'json'
     */
    public function setFormatter(string $format): self
    {
        $this->formatter = in_array($format, ['text', 'json'], true) ? $format : 'text';
        return $this;
    }

    /**
     * Set write mode.
     *
     * @param string $mode 'normal' or 'batch'
     */
    public function setMode(string $mode): self
    {
        $this->mode = in_array($mode, ['normal', 'batch'], true) ? $mode : 'normal';
        return $this;
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
     * Format a log entry based on the configured formatter.
     */
    private function formatEntry(string $msg, string $level, array $context = []): string
    {
        if ($this->formatter === 'json') {
            return json_encode([
                'time'   => date('Y-m-d H:i:s'),
                'log_id' => $this->logId,
                'level'  => $level,
                'msg'    => $msg,
                'ctx'    => $context ?: new \stdClass(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }
        return date('[Y-m-d H:i:s]') . '[' . $this->logId . ']' . "[{$level}]" . $msg . PHP_EOL;
    }

    /**
     * Write log.
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
                $replace['{' . $key . '}'] = $val;
            }
            $msg = strtr($msg, $replace);
        }

        if (empty($this->logId)) {
            $this->logId = sprintf('%x', ((int) (microtime(true) * 10000) % 864000000) * 10000 + random_int(0, 9999));
        }

        $logMessage = $this->formatEntry($msg, $level, $context);
        $upperLevel = strtoupper($level);

        if ($upperLevel === 'SQL') {
            file_put_contents($this->logPath . DS . date('Ymd') . '.log.sql', $logMessage, FILE_APPEND | LOCK_EX);
        } elseif ($upperLevel === 'NOTICE') {
            $this->log[] = $logMessage;
            if ($this->mode === 'batch') {
                $this->wfLog[] = $logMessage;
            } else {
                file_put_contents($this->logPath . DS . date('Ymd') . '.log.wf', $logMessage, FILE_APPEND | LOCK_EX);
            }
        } elseif ($wf) {
            if ($this->mode === 'batch') {
                $this->wfLog[] = $logMessage;
            } else {
                file_put_contents($this->logPath . DS . date('Ymd') . '.log.wf', $logMessage, FILE_APPEND | LOCK_EX);
            }
        } else {
            $this->log[] = $logMessage;
        }

        foreach ($this->handlers as $handler) {
            $handler($level, $msg, $context);
        }
    }

    /**
     * Flush all buffered log entries to disk.
     */
    public function save(): void
    {
        if (!empty($this->log)) {
            file_put_contents(
                $this->logPath . DS . date('Ymd') . '.log',
                implode('', $this->log),
                FILE_APPEND | LOCK_EX
            );
            $this->log = [];
        }

        if (!empty($this->wfLog)) {
            file_put_contents(
                $this->logPath . DS . date('Ymd') . '.log.wf',
                implode('', $this->wfLog),
                FILE_APPEND | LOCK_EX
            );
            $this->wfLog = [];
        }
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
