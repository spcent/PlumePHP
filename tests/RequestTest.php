<?php
/**
 * PlumePHP: An extensible micro-framework.
 */

// 加载框架文件
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class RequestTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var PlumeRequest
     */
    private $request;

    public function setUp(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '32.32.32.32';
        $this->request = new PlumeRequest();
    }

    public function testDefaults()
    {
        $this->assertEquals('/', $this->request->url);
        $this->assertEquals('/', $this->request->base);
        $this->assertEquals('GET', $this->request->method);
        $this->assertEquals('', $this->request->referrer);
        $this->assertEquals(true, $this->request->ajax);
        $this->assertEquals('HTTP/1.1', $this->request->scheme);
        $this->assertEquals('', $this->request->type);
        $this->assertEquals(0, $this->request->length);
        $this->assertEquals(true, $this->request->secure);
        $this->assertEquals('', $this->request->accept);
    }

    public function testIpAddress()
    {
        $this->assertEquals('8.8.8.8', $this->request->ip);
    }

    public function testSubdirectory()
    {
        $_SERVER['SCRIPT_NAME'] = '/subdir/index.php';
        $request = new PlumeRequest();
        $this->assertEquals('/subdir', $request->base);
    }

    public function testQueryParameters()
    {
        $_SERVER['REQUEST_URI'] = '/page?id=1&name=bob';
        $request = new PlumeRequest();
        $this->assertEquals('/page?id=1&name=bob', $request->url);
        $this->assertEquals(1, $request->query->id);
        $this->assertEquals('bob', $request->query->name);
    }

    public function testCollections()
    {
        $_SERVER['REQUEST_URI'] = '/page?id=1';
        $_GET['q'] = 1;
        $_POST['q'] = 1;
        $_COOKIE['q'] = 1;
        $_FILES['q'] = 1;
        $request = new PlumeRequest();

        $this->assertEquals(1, $request->query->q);
        $this->assertEquals(1, $request->query->id);
        $this->assertEquals(1, $request->data->q);
        $this->assertEquals(1, $request->cookies->q);
        $this->assertEquals(1, $request->files->q);
    }

    public function testMethodOverrideWithHeader()
    {
        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';
        $request = new PlumeRequest();
        $this->assertEquals('PUT', $request->method);
    }

    public function testMethodOverrideWithPost()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_REQUEST['_method'] = 'PUT';
        $request = new PlumeRequest();
        $this->assertEquals('PUT', $request->method);
    }

    public function testMethodOverrideIgnoredOnGet(): void
    {
        unset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_REQUEST['_method'] = 'DELETE';
        $request = new PlumeRequest();
        // _method tunnelling must be ignored when the real method is not POST
        $this->assertEquals('GET', $request->method);
    }

    public function testIsMobileReturnsFalseForDesktopUA(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0';
        unset($_SERVER['HTTP_X_WAP_PROFILE'], $_SERVER['HTTP_PROFILE']);
        $request = new PlumeRequest();
        $this->assertFalse($request->isMobile());
    }

    public function testIsMobileReturnsTrueForAndroidUA(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 Mobile Safari/537.36';
        unset($_SERVER['HTTP_X_WAP_PROFILE'], $_SERVER['HTTP_PROFILE']);
        $request = new PlumeRequest();
        $this->assertTrue($request->isMobile());
    }

    public function testIsMobileReturnsTrueForWapProfile(): void
    {
        $_SERVER['HTTP_X_WAP_PROFILE'] = 'http://wap.example.com/profile.xml';
        $_SERVER['HTTP_USER_AGENT'] = 'SomeGenericBrowser/1.0';
        unset($_SERVER['HTTP_PROFILE']);
        $request = new PlumeRequest();
        $this->assertTrue($request->isMobile());
    }

    public function testIsMobileReturnsTrueForHttpProfile(): void
    {
        unset($_SERVER['HTTP_X_WAP_PROFILE']);
        $_SERVER['HTTP_PROFILE'] = 'http://wap.example.com/profile.xml';
        $_SERVER['HTTP_USER_AGENT'] = 'SomeGenericBrowser/1.0';
        $request = new PlumeRequest();
        $this->assertTrue($request->isMobile());
    }

    public function testIsMobileReturnsFalseWhenUaMissing(): void
    {
        unset($_SERVER['HTTP_X_WAP_PROFILE'], $_SERVER['HTTP_PROFILE'], $_SERVER['HTTP_USER_AGENT']);
        $request = new PlumeRequest();
        $this->assertFalse($request->isMobile());
    }
}