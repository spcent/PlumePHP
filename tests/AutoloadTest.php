<?php
/**
 * PlumePHP: An extensible micro-framework.
 */

// 加载框架文件
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class AutoloadTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PlumeEngine
     */
    private $app;

    public function setUp()
    {
        $this->app = new PlumeEngine();
        $this->app->path(__DIR__.'/classes');
    }

    // Autoload a class
    public function testAutoload()
    {
        $this->app->register('user', 'User');
        $loaders = spl_autoload_functions();

        $user = $this->app->user();
        $this->assertTrue(sizeof($loaders) > 0);
        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
    }

    // Check autoload failure
    public function testMissingClass()
    {
        $test = null;
        $this->app->register('test', 'NonExistentClass');
        if (class_exists('NonExistentClass')) {
            $test = $this->app->test();
        }

        $this->assertEquals(null, $test);
    }
}