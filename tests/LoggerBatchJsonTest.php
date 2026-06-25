<?php

declare(strict_types=1);

/**
 * Tests for PlumeLogger batch mode and JSON formatter.
 */
class LoggerBatchJsonTest extends \PHPUnit\Framework\TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/plume_logger_test_' . uniqid();
        mkdir($this->logDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->logDir);
    }

    // ---------------------------------------------------------------------------
    // JSON formatter
    // ---------------------------------------------------------------------------

    public function testJsonFormatterWritesValidJson(): void
    {
        $logger = new PlumeLogger('test', $this->logDir);
        $logger->setFormatter('json');
        $logger->write('hello world', [], 'INFO');
        $logger->save();

        $logFile = $this->logDir . '/' . date('Ymd') . '.log';
        $this->assertFileExists($logFile);

        $line    = trim(file_get_contents($logFile));
        $decoded = json_decode($line, true);
        $this->assertIsArray($decoded);
        $this->assertSame('INFO', $decoded['level']);
        $this->assertSame('hello world', $decoded['msg']);
        $this->assertArrayHasKey('time', $decoded);
        $this->assertArrayHasKey('log_id', $decoded);
    }

    public function testJsonFormatterIncludesContext(): void
    {
        $logger = new PlumeLogger('test', $this->logDir);
        $logger->setFormatter('json');
        $logger->write('User {id} logged in', ['id' => 42], 'INFO');
        $logger->save();

        $logFile = $this->logDir . '/' . date('Ymd') . '.log';
        $line    = trim(file_get_contents($logFile));
        $decoded = json_decode($line, true);

        $this->assertSame('User 42 logged in', $decoded['msg']);
        $this->assertSame(['id' => 42], $decoded['ctx']);
    }

    public function testTextFormatterIsDefault(): void
    {
        $logger = new PlumeLogger('test', $this->logDir);
        $logger->write('plain text entry', [], 'DEBUG');
        $logger->save();

        $logFile = $this->logDir . '/' . date('Ymd') . '.log';
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('[DEBUG]', $content);
        $this->assertStringContainsString('plain text entry', $content);
        // Should NOT be JSON
        $this->assertNull(json_decode(trim($content)));
    }

    public function testSetFormatterIgnoresInvalidValue(): void
    {
        $logger = new PlumeLogger('test', $this->logDir);
        $logger->setFormatter('xml'); // invalid — falls back to 'text'
        $logger->write('msg', [], 'INFO');
        $logger->save();

        $logFile = $this->logDir . '/' . date('Ymd') . '.log';
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('[INFO]', $content);
    }

    // ---------------------------------------------------------------------------
    // Batch mode
    // ---------------------------------------------------------------------------

    public function testBatchModeBuffersErrorsUntilSave(): void
    {
        $logger = new PlumeLogger('test', $this->logDir);
        $logger->setMode('batch');

        $logger->write('a fatal error', [], 'ERROR', true);

        // wf file should NOT yet exist
        $wfFile = $this->logDir . '/' . date('Ymd') . '.log.wf';
        $this->assertFileDoesNotExist($wfFile);

        $logger->save();
        $this->assertFileExists($wfFile);
        $this->assertStringContainsString('a fatal error', file_get_contents($wfFile));
    }

    public function testNormalModeWritesErrorImmediately(): void
    {
        $logger = new PlumeLogger('test', $this->logDir);
        // mode is 'normal' by default

        $logger->write('immediate error', [], 'ERROR', true);

        $wfFile = $this->logDir . '/' . date('Ymd') . '.log.wf';
        $this->assertFileExists($wfFile);
        $this->assertStringContainsString('immediate error', file_get_contents($wfFile));
    }

    public function testBatchModeBuffersDebugAndFlushesOnSave(): void
    {
        $logger = new PlumeLogger('test', $this->logDir);
        $logger->setMode('batch');

        $logger->write('debug msg', [], 'DEBUG');
        $logger->write('info msg', [], 'INFO');

        $logFile = $this->logDir . '/' . date('Ymd') . '.log';
        $this->assertFileDoesNotExist($logFile);

        $logger->save();
        $this->assertFileExists($logFile);
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('debug msg', $content);
        $this->assertStringContainsString('info msg', $content);
    }

    public function testSetModeIgnoresInvalidValue(): void
    {
        $logger = new PlumeLogger('test', $this->logDir);
        $logger->setMode('async'); // invalid, falls back to 'normal'

        $logger->write('test error', [], 'ERROR', true);

        // normal mode → immediate write
        $wfFile = $this->logDir . '/' . date('Ymd') . '.log.wf';
        $this->assertFileExists($wfFile);
    }

    // ---------------------------------------------------------------------------
    // Handler interaction with formatters
    // ---------------------------------------------------------------------------

    public function testHandlerReceivesRawMessageNotFormatted(): void
    {
        $received = [];
        $logger   = new PlumeLogger('test', $this->logDir);
        $logger->setFormatter('json');
        $logger->addHandler(function (string $level, string $msg, array $ctx) use (&$received) {
            $received = compact('level', 'msg', 'ctx');
        });

        $logger->write('handler test', ['k' => 'v'], 'WARNING', true);

        $this->assertSame('WARNING', $received['level']);
        $this->assertSame('handler test', $received['msg']);
        $this->assertSame(['k' => 'v'], $received['ctx']);
    }
}
