<?php
/**
 * PlumePHP: An extensible micro-framework.
 */

// 加载框架文件
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class PlumePHPTest extends PHPUnit_Framework_TestCase
{
    public function setUp() {
        PlumePHP::init();
    }

    // Checks that default components are loaded
    public function testDefaultComponents()
    {
        $request = PlumePHP::request();
        $response = PlumePHP::response();
        $router = PlumePHP::router();
        $view = PlumePHP::view();

        $this->assertEquals('PlumeRequest', get_class($request));
        $this->assertEquals('PlumeResponse', get_class($response));
        $this->assertEquals('PlumeRouter', get_class($router));
        $this->assertEquals('PlumeView', get_class($view));
    }

    // Test get/set of variables
    public function testGetAndSet()
    {
        PlumePHP::set('a', 1);
        $var = PlumePHP::get('a');
        $this->assertEquals(1, $var);

        PlumePHP::clear();

        $vars = PlumePHP::get();
        $this->assertEquals(0, count($vars));

        PlumePHP::set('a', 1);
        PlumePHP::set('b', 2);
        $vars = PlumePHP::get();

        $this->assertEquals(2, count($vars));
        $this->assertEquals(1, $vars['a']);
        $this->assertEquals(2, $vars['b']);
    }

    // Register a class
    public function testRegister()
    {
        PlumePHP::path(__DIR__.'/classes');
        PlumePHP::register('user', 'User');

        $user = PlumePHP::user();
        $loaders = spl_autoload_functions();

        $this->assertTrue(sizeof($loaders) > 0);
        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
    }

    // Map a function
    public function testMap()
    {
        PlumePHP::map('map1', function(){
            return 'hello';
        });

        $result = PlumePHP::map1();
        $this->assertEquals('hello', $result);
    }

    // Unmapped method
    public function testUnmapped()
    {
        $this->setExpectedException('Exception', 'doesNotExist must be a mapped method.');
        PlumePHP::doesNotExist();
    }
}