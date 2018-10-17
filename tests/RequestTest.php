<?php
/**
 * PlumePHP: An extensible micro-framework.
 */

// 加载框架文件
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class RequestTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PlumeRequest
     */
    private $request;

    public function setUp()
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
        $_REQUEST['_method'] = 'PUT';
        $request = new PlumeRequest();
        $this->assertEquals('PUT', $request->method);
    }
}