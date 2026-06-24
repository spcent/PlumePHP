<?php

declare(strict_types=1);

class LoggerTest extends \PHPUnit\Framework\TestCase
{
    private string $logDir;
    private PlumeLogger $logger;

    public function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/plume_test_logs_' . getmypid();
        @mkdir($this->logDir, 0755, true);
        $this->logger = new PlumeLogger('test-id', $this->logDir);
    }

    public function tearDown(): void
    {
        foreach (glob($this->logDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->logDir);
    }

    public function testWriteDebugAppendsToLog(): void
    {
        $this->logger->debug('hello debug');
        $this->logger->save();

        $logFile = $this->logDir . '/' . date('Ymd') . '.log';
        $this->assertFileExists($logFile);
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('[DEBUG]hello debug', $content);
        $this->assertStringContainsString('[test-id]', $content);
    }

    public function testWriteInfoLevel(): void
    {
        $this->logger->info('info message');
        $this->logger->save();

        $content = file_get_contents($this->logDir . '/' . date('Ymd') . '.log');
        $this->assertStringContainsString('[INFO]info message', $content);
    }

    public function testWriteErrorGoesToWfFile(): void
    {
        $this->logger->error('something broke');

        $wfFile = $this->logDir . '/' . date('Ymd') . '.log.wf';
        $this->assertFileExists($wfFile);
        $content = file_get_contents($wfFile);
        $this->assertStringContainsString('[ERROR]something broke', $content);
    }

    public function testWriteWarnGoesToWfFile(): void
    {
        $this->logger->warn('a warning');

        $wfFile = $this->logDir . '/' . date('Ymd') . '.log.wf';
        $this->assertStringContainsString('[WARNING]a warning', file_get_contents($wfFile));
    }

    public function testWriteFatalGoesToWfFile(): void
    {
        $this->logger->fatal('fatal error');

        $wfFile = $this->logDir . '/' . date('Ymd') . '.log.wf';
        $this->assertStringContainsString('[FATAL]fatal error', file_get_contents($wfFile));
    }

    public function testWriteSqlGoesToSqlFile(): void
    {
        $this->logger->sql('SELECT 1');

        $sqlFile = $this->logDir . '/' . date('Ymd') . '.log.sql';
        $this->assertFileExists($sqlFile);
        $this->assertStringContainsString('[SQL]SELECT 1', file_get_contents($sqlFile));
    }

    public function testContextInterpolation(): void
    {
        $this->logger->write('User {name} logged in', ['name' => 'Alice'], 'INFO');
        $this->logger->save();

        $content = file_get_contents($this->logDir . '/' . date('Ymd') . '.log');
        $this->assertStringContainsString('User Alice logged in', $content);
    }

    public function testEmptyMessageIsSkipped(): void
    {
        $this->logger->write('');
        $this->logger->save();

        $logFile = $this->logDir . '/' . date('Ymd') . '.log';
        $this->assertFileDoesNotExist($logFile);
    }

    public function testLogFormatContainsTimestamp(): void
    {
        $this->logger->info('ts check');
        $this->logger->save();

        $content = file_get_contents($this->logDir . '/' . date('Ymd') . '.log');
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $content);
    }

    public function testSaveFlushesAndClearsBuffer(): void
    {
        $this->logger->debug('first');
        $this->logger->save();
        $this->logger->save(); // second save should not duplicate

        $logFile = $this->logDir . '/' . date('Ymd') . '.log';
        $content = file_get_contents($logFile);
        $this->assertEquals(1, substr_count($content, 'first'));
    }
}
