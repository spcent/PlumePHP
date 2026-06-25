<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class ParamTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $_GET    = [];
        $_POST   = [];
        $_SERVER = ['QUERY_STRING' => ''];
    }

    public function testGetReturnsRawValueWithoutEscaping(): void
    {
        $_POST['name'] = '<script>alert(1)</script>';
        $param = new PlumeParam();
        // __get() must return the raw value — no implicit htmlentities
        $this->assertSame('<script>alert(1)</script>', $param->name);
    }

    public function testHtmlMethodEscapesForOutput(): void
    {
        $_POST['name'] = '<b>Hello & "World"</b>';
        $param = new PlumeParam();
        $this->assertSame(
            '&lt;b&gt;Hello &amp; &quot;World&quot;&lt;/b&gt;',
            $param->html('name')
        );
    }

    public function testHtmlMethodReturnsEmptyStringForMissingKey(): void
    {
        $param = new PlumeParam();
        $this->assertSame('', $param->html('nonexistent'));
    }

    public function testGetValueReturnDefault(): void
    {
        $param = new PlumeParam();
        $this->assertSame('fallback', $param->getValue('missing', 'fallback'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $param = new PlumeParam();
        $this->assertFalse($param->has('nope'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $_POST['foo'] = 'bar';
        $param = new PlumeParam();
        $this->assertTrue($param->has('foo'));
    }

    public function testSetAndGetParam(): void
    {
        $param = new PlumeParam();
        $param->key = 'value';
        $this->assertSame('value', $param->key);
    }

    public function testToArrayReturnsMergedParams(): void
    {
        $_POST['a'] = '1';
        $param = new PlumeParam(['b' => '2']);
        $arr = $param->toArray();
        $this->assertSame('1', $arr['a']);
        $this->assertSame('2', $arr['b']);
    }

    public function testNumericValuesNotMangled(): void
    {
        $_POST['price'] = '99.99';
        $param = new PlumeParam();
        // Previously htmlentities('99.99') returned '99.99' but now we verify
        // that the raw type is preserved without wrapping.
        $this->assertSame('99.99', $param->price);
    }
}
