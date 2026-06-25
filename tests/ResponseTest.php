<?php

declare(strict_types=1);

class ResponseTest extends \PHPUnit\Framework\TestCase
{
    private PlumeResponse $response;

    public function setUp(): void
    {
        $this->response = new PlumeResponse();
    }

    public function testDefaultStatus(): void
    {
        $this->assertEquals(200, $this->response->status());
    }

    public function testSetValidStatus(): void
    {
        $result = $this->response->status(404);
        $this->assertEquals(404, $this->response->status());
        $this->assertSame($this->response, $result);
    }

    public function testSetInvalidStatusThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->response->status(999);
    }

    public function testHeaderSingle(): void
    {
        $this->response->header('Content-Type', 'application/json');
        $this->assertEquals(['Content-Type' => 'application/json'], $this->response->headers());
    }

    public function testHeaderArray(): void
    {
        $this->response->header(['X-Foo' => 'bar', 'X-Baz' => 'qux']);
        $headers = $this->response->headers();
        $this->assertEquals('bar', $headers['X-Foo']);
        $this->assertEquals('qux', $headers['X-Baz']);
    }

    public function testHeaderReturnsSelf(): void
    {
        $result = $this->response->header('X-Test', '1');
        $this->assertSame($this->response, $result);
    }

    public function testWriteAppendsBody(): void
    {
        $this->response->write('Hello');
        $this->response->write(' World');
        $this->assertEquals(11, $this->response->getContentLength());
    }

    public function testClearResetsState(): void
    {
        $this->response->status(404);
        $this->response->header('X-Test', '1');
        $this->response->write('body');
        $this->response->clear();

        $this->assertEquals(200, $this->response->status());
        $this->assertEquals([], $this->response->headers());
        $this->assertEquals(0, $this->response->getContentLength());
    }

    public function testCacheWithFalseSetsCacheControlHeaders(): void
    {
        $this->response->cache(false);
        $headers = $this->response->headers();
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertArrayHasKey('Expires', $headers);
        $this->assertArrayHasKey('Pragma', $headers);
    }

    public function testCacheWithFalseProducesStringNotArray(): void
    {
        $this->response->cache(false);
        $cc = $this->response->headers()['Cache-Control'];
        $this->assertIsString($cc, 'Cache-Control should be a single string, not an array');
    }

    public function testCacheWithFalseOmitsIe6Directives(): void
    {
        $this->response->cache(false);
        $cc = $this->response->headers()['Cache-Control'];
        $this->assertStringNotContainsString('post-check', $cc);
        $this->assertStringNotContainsString('pre-check', $cc);
    }

    public function testCacheWithFutureTimestamp(): void
    {
        $future = time() + 3600;
        $this->response->cache($future);
        $headers = $this->response->headers();
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertStringContainsString('max-age=', $headers['Cache-Control']);
    }

    public function testSentStartsFalse(): void
    {
        $this->assertFalse($this->response->sent());
    }

    public function testStatusCodes(): void
    {
        foreach ([200, 201, 301, 302, 400, 401, 403, 404, 405, 500, 503] as $code) {
            $this->response->status($code);
            $this->assertEquals($code, $this->response->status());
        }
    }
}
