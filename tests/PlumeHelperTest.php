<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'PlumeHelper.php';

class PlumeHelperTest extends \PHPUnit\Framework\TestCase
{
    // -----------------------------------------------------------------------
    // JSON
    // -----------------------------------------------------------------------

    public function testJsonFormatArray(): void
    {
        $out = PlumeHelper::jsonFormat(['a' => 1]);
        $this->assertStringContainsString('"a"', $out);
        $this->assertStringContainsString('1', $out);
    }

    public function testJsonFormatString(): void
    {
        $out = PlumeHelper::jsonFormat('{"x":1}');
        $this->assertStringContainsString('"x"', $out);
    }

    // -----------------------------------------------------------------------
    // Security / Crypto
    // -----------------------------------------------------------------------

    public function testGenerateNonceStrLength(): void
    {
        $s = PlumeHelper::generateNonceStr(20);
        $this->assertSame(20, strlen($s));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $s);
    }

    public function testGenerateNonceStrDefaultLength(): void
    {
        $this->assertSame(16, strlen(PlumeHelper::generateNonceStr()));
    }

    public function testGenerateNonceStrProducesDifferentValues(): void
    {
        // Basic randomness sanity check — two 16-char strings colliding is ~1 in 62^16.
        $this->assertNotSame(PlumeHelper::generateNonceStr(16), PlumeHelper::generateNonceStr(16));
    }

    public function testSignatureIsConsistent(): void
    {
        $data = ['b' => '2', 'a' => '1'];
        $sig1 = PlumeHelper::signature($data, 'key');
        $sig2 = PlumeHelper::signature(['a' => '1', 'b' => '2'], 'key');
        $this->assertSame($sig1, $sig2);
    }

    public function testSignatureExcludesSignatureKey(): void
    {
        $sig1 = PlumeHelper::signature(['a' => '1'], 'key');
        $sig2 = PlumeHelper::signature(['a' => '1', 'signature' => 'anything'], 'key');
        $this->assertSame($sig1, $sig2);
    }

    public function testSignatureRequiresKey(): void
    {
        $this->expectException(\TypeError::class);
        PlumeHelper::signature(['a' => '1']); // key is now required, no default
    }

    public function testAuthcodeRoundTrip(): void
    {
        $plain  = 'hello world';
        $key    = 'mySecretKey';
        $enc    = PlumeHelper::authcode($plain, 'ENCODE', $key);
        $dec    = PlumeHelper::authcode($enc, 'DECODE', $key);
        $this->assertSame($plain, $dec);
    }

    public function testAuthcodeWrongKeyReturnsEmpty(): void
    {
        $enc = PlumeHelper::authcode('test', 'ENCODE', 'key1');
        $dec = PlumeHelper::authcode($enc, 'DECODE', 'key2');
        $this->assertSame('', $dec);
    }

    public function testHtmlFilter(): void
    {
        $input  = '<script>alert(1)</script><p>ok</p>';
        $output = PlumeHelper::htmlFilter($input);
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script', $output);
    }

    // -----------------------------------------------------------------------
    // Data / String
    // -----------------------------------------------------------------------

    public function testUuidIsRfc4122(): void
    {
        $id = PlumeHelper::uuid();
        // RFC 4122 v4 format: xxxxxxxx-xxxx-4xxx-[89ab]xxx-xxxxxxxxxxxx
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id
        );
    }

    public function testUuidWithPrefix(): void
    {
        $id = PlumeHelper::uuid('usr');
        $this->assertStringStartsWith('usr-', $id);
        $uuid = substr($id, 4);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testStrcut(): void
    {
        $s = PlumeHelper::strcut('hello world', 5, '...');
        $this->assertSame('hello...', $s);
    }

    public function testStrcutNoTruncation(): void
    {
        $s = PlumeHelper::strcut('hi', 10, '...');
        $this->assertSame('hi', $s);
    }

    public function testStr2hexAndBack(): void
    {
        $original = 'hello';
        $hex      = PlumeHelper::str2hex($original);
        $this->assertSame($original, PlumeHelper::hex2str($hex));
    }

    public function testHumanDateSecondsAgo(): void
    {
        $ts  = time() - 30;
        $out = PlumeHelper::humanDate($ts);
        $this->assertStringContainsString('秒前', $out);
    }

    public function testHumanDateMinutesAgo(): void
    {
        $ts  = time() - 120;
        $out = PlumeHelper::humanDate($ts);
        $this->assertStringContainsString('分钟前', $out);
    }

    public function testHumanDateHoursAgo(): void
    {
        $ts  = time() - 7200;
        $out = PlumeHelper::humanDate($ts);
        $this->assertStringContainsString('小时前', $out);
    }

    public function testHumanDateOldDateUsesFormat(): void
    {
        $ts  = time() - 40000000;
        $out = PlumeHelper::humanDate($ts, 'Y');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $out);
    }

    public function testArrayMergeDeep(): void
    {
        $a = ['x' => ['a' => 1, 'b' => 2]];
        $b = ['x' => ['b' => 99, 'c' => 3]];
        PlumeHelper::arrayMergeDeep($a, $b);
        $this->assertSame(1, $a['x']['a']);
        $this->assertSame(99, $a['x']['b']);
        $this->assertSame(3, $a['x']['c']);
    }

    // -----------------------------------------------------------------------
    // Money
    // -----------------------------------------------------------------------

    public function testMoneyYuanToFen(): void
    {
        $this->assertSame(100, PlumeHelper::moneyYuanToFen(1.00));
        $this->assertSame(199, PlumeHelper::moneyYuanToFen(1.99));
        $this->assertSame(0, PlumeHelper::moneyYuanToFen(0));
    }

    public function testMoneyFenToYuan(): void
    {
        $this->assertSame('1.00', PlumeHelper::moneyFenToYuan(100));
        $this->assertSame('1.99', PlumeHelper::moneyFenToYuan(199));
        $this->assertSame('0.00', PlumeHelper::moneyFenToYuan(0));
    }

    public function testMoneyRoundTrip(): void
    {
        $yuan = '12.50';
        $fen  = PlumeHelper::moneyYuanToFen($yuan);
        $this->assertSame($yuan, PlumeHelper::moneyFenToYuan($fen));
    }

    // -----------------------------------------------------------------------
    // fetchFromArray
    // -----------------------------------------------------------------------

    public function testFetchFromArrayDirect(): void
    {
        $arr = ['a' => 1, 'b' => 2];
        $this->assertSame(1, PlumeHelper::fetchFromArray($arr, 'a'));
        $this->assertNull(PlumeHelper::fetchFromArray($arr, 'z'));
        $this->assertSame(99, PlumeHelper::fetchFromArray($arr, 'z', 99));
    }

    public function testFetchFromArraySlashPath(): void
    {
        $arr = ['a' => ['b' => ['c' => 42]]];
        $this->assertSame(42, PlumeHelper::fetchFromArray($arr, 'a/b/c'));
    }

    public function testFetchFromArrayNullIndexReturnsAll(): void
    {
        $arr = ['x' => 1];
        $this->assertSame($arr, PlumeHelper::fetchFromArray($arr, null));
    }

    // -----------------------------------------------------------------------
    // HTTP helpers
    // -----------------------------------------------------------------------

    public function testIsFromBrowserReturnsFalseWithNoUserAgent(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);
        $this->assertFalse(PlumeHelper::isFromBrowser());
    }

    public function testIsFromBrowserReturnsTrueForGeckoUA(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0';
        $this->assertTrue(PlumeHelper::isFromBrowser());
    }

    public function testIsFromBrowserNotCachedAcrossCalls(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1)';
        $this->assertTrue(PlumeHelper::isFromBrowser());

        unset($_SERVER['HTTP_USER_AGENT']);
        // Without static cache the result must reflect the updated UA on the next call.
        $this->assertFalse(PlumeHelper::isFromBrowser());
    }
}
