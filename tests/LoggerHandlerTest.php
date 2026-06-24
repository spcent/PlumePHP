<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class LoggerHandlerTest extends \PHPUnit\Framework\TestCase
{
    private string $logDir;
    private PlumeLogger $logger;

    public function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/plume_handler_test_' . getmypid();
        @mkdir($this->logDir, 0755, true);
        $this->logger = new PlumeLogger('test-handler', $this->logDir);
    }

    public function tearDown(): void
    {
        // Flush the in-memory buffer before deleting the directory so
        // __destruct() doesn't try to write to a non-existent path.
        $this->logger->save();
        unset($this->logger);

        foreach (glob($this->logDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->logDir);
    }

    public function testHandlerIsCalledOnWrite(): void
    {
        $received = [];
        $this->logger->addHandler(function (string $level, string $msg, array $ctx) use (&$received) {
            $received[] = compact('level', 'msg', 'ctx');
        });

        $this->logger->info('test message', ['key' => 'val']);

        $this->assertCount(1, $received);
        $this->assertSame('INFO', $received[0]['level']);
        $this->assertSame('test message', $received[0]['msg']);
        $this->assertSame(['key' => 'val'], $received[0]['ctx']);
    }

    public function testMultipleHandlersAllCalled(): void
    {
        $count = 0;
        $this->logger->addHandler(function () use (&$count) { $count++; });
        $this->logger->addHandler(function () use (&$count) { $count++; });

        $this->logger->debug('hello');
        $this->assertSame(2, $count);
    }

    public function testHandlerChaining(): void
    {
        $result = $this->logger->addHandler(function () {});
        $this->assertInstanceOf(PlumeLogger::class, $result);
    }

    public function testHandlerNotCalledOnEmptyMessage(): void
    {
        $called = false;
        $this->logger->addHandler(function () use (&$called) { $called = true; });

        $this->logger->write('');
        $this->assertFalse($called);
    }

    public function testHandlerReceivesInterpolatedMessage(): void
    {
        $received = null;
        $this->logger->addHandler(function (string $level, string $msg) use (&$received) {
            $received = $msg;
        });

        $this->logger->info('Hello {name}', ['name' => 'World']);
        $this->assertSame('Hello World', $received);
    }

    public function testHandlerCalledForAllLevels(): void
    {
        $levels = [];
        $this->logger->addHandler(function (string $level) use (&$levels) {
            $levels[] = $level;
        });

        $this->logger->debug('d');
        $this->logger->info('i');
        $this->logger->warning('w');
        $this->logger->error('e');

        $this->assertSame(['DEBUG', 'INFO', 'WARNING', 'ERROR'], $levels);
    }
}
