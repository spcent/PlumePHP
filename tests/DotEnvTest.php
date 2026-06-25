<?php

declare(strict_types=1);

class DotEnvTest extends \PHPUnit\Framework\TestCase
{
    private string $tmpFile;

    public function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/plume_dotenv_' . getmypid() . '.env';
    }

    public function tearDown(): void
    {
        @unlink($this->tmpFile);
    }

    private function write(string $content): void
    {
        file_put_contents($this->tmpFile, $content);
    }

    public function testBasicKeyValue(): void
    {
        $this->write("FOO=bar\nBAZ=qux\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertSame('bar', $result['FOO']);
        $this->assertSame('qux', $result['BAZ']);
    }

    public function testCommentsAreIgnored(): void
    {
        $this->write("# this is a comment\nKEY=value\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertArrayNotHasKey('# this is a comment', $result);
        $this->assertSame('value', $result['KEY']);
    }

    public function testInlineCommentStripped(): void
    {
        $this->write("KEY=hello # inline comment\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertSame('hello', $result['KEY']);
    }

    public function testDoubleQuotedValue(): void
    {
        $this->write('KEY="hello world"' . "\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertSame('hello world', $result['KEY']);
    }

    public function testSingleQuotedValue(): void
    {
        $this->write("KEY='hello world'\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertSame('hello world', $result['KEY']);
    }

    public function testBooleanCoercionTrue(): void
    {
        $this->write("A=true\nB=yes\nC=on\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertTrue($result['A']);
        $this->assertTrue($result['B']);
        $this->assertTrue($result['C']);
    }

    public function testBooleanCoercionFalse(): void
    {
        $this->write("A=false\nB=no\nC=off\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertFalse($result['A']);
        $this->assertFalse($result['B']);
        $this->assertFalse($result['C']);
    }

    public function testNullCoercion(): void
    {
        $this->write("KEY=null\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertNull($result['KEY']);
    }

    public function testIntegerCoercion(): void
    {
        $this->write("PORT=8080\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertSame(8080, $result['PORT']);
    }

    public function testFloatCoercion(): void
    {
        $this->write("RATE=1.5\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertSame(1.5, $result['RATE']);
    }

    public function testMissingFileReturnsEmptyArray(): void
    {
        $result = PlumeDotEnv::parse('/nonexistent/path/.env');
        $this->assertSame([], $result);
    }

    public function testLinesWithoutEqualsAreSkipped(): void
    {
        $this->write("NODASH\nKEY=value\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertArrayNotHasKey('NODASH', $result);
        $this->assertSame('value', $result['KEY']);
    }

    public function testDoubleQuotePreservesHashInValue(): void
    {
        $this->write('KEY="has#hash"' . "\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertSame('has#hash', $result['KEY']);
    }

    public function testTabBeforeHashInlineCommentStripped(): void
    {
        $this->write("KEY=hello\t# tab inline comment\n");
        $result = PlumeDotEnv::parse($this->tmpFile);
        $this->assertSame('hello', $result['KEY']);
    }
}
