<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class ResponseStreamTest extends \PHPUnit\Framework\TestCase
{
    private string $tmpFile;

    public function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'plume_resp_test_');
    }

    public function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
    }

    public function testDownloadThrowsForMissingFile(): void
    {
        $r = new PlumeResponse();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/File not found/');
        $r->download('/nonexistent/path/file.bin');
    }

    public function testStreamCallsEmitAndMarksSent(): void
    {
        $r = new PlumeResponse();
        $emitted = [];

        ob_start();
        $r->stream(function (callable $emit) use (&$emitted) {
            $emit('hello', 'message', 'evt1');
            $emitted[] = 'called';
        });
        ob_end_clean();

        $this->assertTrue($r->sent());
        $this->assertSame(['called'], $emitted);
    }

    public function testStreamOutputContainsDataLines(): void
    {
        $r = new PlumeResponse();

        ob_start();
        $r->stream(function (callable $emit) {
            $emit('line1', 'msg');
        });
        $out = ob_get_clean();

        $this->assertStringContainsString("data: line1\n", (string) $out);
        $this->assertStringContainsString("\n\n", (string) $out);
    }

    public function testStreamWithCustomEvent(): void
    {
        $r = new PlumeResponse();

        ob_start();
        $r->stream(function (callable $emit) {
            $emit('payload', 'update', 'id42');
        });
        $out = (string) ob_get_clean();

        $this->assertStringContainsString("id: id42\n", $out);
        $this->assertStringContainsString("event: update\n", $out);
        $this->assertStringContainsString("data: payload\n", $out);
    }

    public function testDownloadStreamsFileContents(): void
    {
        file_put_contents($this->tmpFile, 'binary-data-here');

        $r = new PlumeResponse();
        ob_start();
        $r->download($this->tmpFile, 'test.bin');
        $out = ob_get_clean();

        $this->assertTrue($r->sent());
        $this->assertSame('binary-data-here', $out);
    }
}
