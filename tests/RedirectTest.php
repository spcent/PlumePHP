<?php
/**
 * PlumePHP: An extensible micro-framework.
 */

// 加载框架文件
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class RedirectTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PlumeEngine
     */
    private $app;

    public function getBaseUrl($base, $url)
    {
        if ($base != '/' && strpos($url, '://') === false) {
            $url = preg_replace('#/+#', '/', $base.'/'.$url);
        }
        return $url;
    }

    public function setUp()
    {
        $_SERVER['SCRIPT_NAME'] = '/subdir/index.php';
        $this->app = new PlumeEngine();
        $this->app->set('plumephp.base_url', '/testdir');
    }

    // The base should be the subdirectory
    public function testBase()
    {
        $base = $this->app->request()->base;
        $this->assertEquals('/subdir', $base);
    }

    // Absolute URLs should include the base
    public function testAbsoluteUrl()
    {
        $url = '/login';
        $base = $this->app->request()->base;
        $this->assertEquals('/subdir/login', $this->getBaseUrl($base, $url));
    }

    // Relative URLs should include the base
    public function testRelativeUrl()
    {
        $url = 'login';
        $base = $this->app->request()->base;
        $this->assertEquals('/subdir/login', $this->getBaseUrl($base, $url));
    }

    // External URLs should ignore the base
    public function testHttpUrl()
    {
        $url = 'http://www.yahoo.com';
        $base = $this->app->request()->base;
        $this->assertEquals('http://www.yahoo.com', $this->getBaseUrl($base, $url));
    }

    // Configuration should override derived value
    public function testBaseOverride()
    {
        $url = 'login';
        if ($this->app->get('plumephp.base_url') !== null) {
            $base = $this->app->get('plumephp.base_url');
        } else {
            $base = $this->app->request()->base;
        }

        $this->assertEquals('/testdir/login', $this->getBaseUrl($base, $url));
    }
}
