<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'PlumePHP.php';

/**
 * Tests for C() snapshot/restore — worker-mode config isolation.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConfigWorkerTest extends \PHPUnit\Framework\TestCase
{
    public function testSnapshotPreservesBootTimeConfig(): void
    {
        C('APP_NAME', 'TestApp');
        C("\x00snapshot_take\x00");

        // Simulate per-request mutation
        C('APP_NAME', 'MutatedApp');
        $this->assertEquals('MutatedApp', C('APP_NAME'));

        // Restore to boot snapshot
        C("\x00snapshot_restore\x00");
        $this->assertEquals('TestApp', C('APP_NAME'));
    }

    public function testRestoreBeforeSnapshotIsNoop(): void
    {
        C('KEY', 'value');

        // Restore with no snapshot taken — should not change anything
        C("\x00snapshot_restore\x00");
        $this->assertEquals('value', C('KEY'));
    }

    public function testSnapshotIsolatesNewKeysAddedPerRequest(): void
    {
        C('BOOT_KEY', 'boot');
        C("\x00snapshot_take\x00");

        // Per-request code adds a transient key
        C('REQUEST_KEY', 'transient');
        $this->assertEquals('transient', C('REQUEST_KEY'));

        C("\x00snapshot_restore\x00");
        $this->assertNull(C('REQUEST_KEY'), 'Per-request key must not survive restore');
        $this->assertEquals('boot', C('BOOT_KEY'), 'Boot-time key must survive restore');
    }

    public function testSnapshotCanBeRetakenAfterRestore(): void
    {
        C('V', '1');
        C("\x00snapshot_take\x00");
        C('V', '2');
        C("\x00snapshot_restore\x00");
        $this->assertEquals('1', C('V'));

        // Update and re-snapshot
        C('V', '3');
        C("\x00snapshot_take\x00");
        C('V', '4');
        C("\x00snapshot_restore\x00");
        $this->assertEquals('3', C('V'));
    }

    public function testDotNotationSurvivesSnapshot(): void
    {
        C(['DB_CONF' => ['master' => ['db_port' => 3306]]]);
        C("\x00snapshot_take\x00");

        C('DB_CONF', ['master' => ['db_port' => 9999]]);
        C("\x00snapshot_restore\x00");

        $this->assertEquals(3306, C('DB_CONF.master.db_port'));
    }
}
