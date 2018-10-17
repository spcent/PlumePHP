<?php
/**
 * PlumePHP: An extensible micro-framework.
 */

// 加载框架文件
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';
require_once __DIR__.'/classes/Hello.php';

class DispatcherTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PlumeEvent
     */
    private $dispatcher;
    function setUp(){
        $this->dispatcher = new PlumeEvent();
    }

    // Map a closure
    public function testClosureMapping()
    {
        $this->dispatcher->set('map1', function(){
            return 'hello';
        });

        $result = $this->dispatcher->run('map1');
        $this->assertEquals('hello', $result);
    }

    // Map a function
    public function testFunctionMapping()
    {
        $this->dispatcher->set('map2', function(){
            return 'hello';
        });

        $result = $this->dispatcher->run('map2');
        $this->assertEquals('hello', $result);
    }

    // Map a class method
    public function testClassMethodMapping()
    {
        $h = new Hello();
        $this->dispatcher->set('map3', array($h, 'sayHi'));
        $result = $this->dispatcher->run('map3');
        $this->assertEquals('hello', $result);
    }

    // Map a static class method
    public function testStaticClassMethodMapping()
    {
        $this->dispatcher->set('map4', array('Hello', 'sayBye'));
        $result = $this->dispatcher->run('map4');
        $this->assertEquals('goodbye', $result);
    }

    // Run before and after filters
    public function testBeforeAndAfter()
    {
        $this->dispatcher->set('hello', function($name){
            return "Hello, $name!";
        });

        $this->dispatcher->hook('hello', 'before', function(&$params, &$output){
            // Manipulate the parameter
            $params[0] = 'Fred';
        });

        $this->dispatcher->hook('hello', 'after', function(&$params, &$output){
            // Manipulate the output
            $output .= " Have a nice day!";
        });

        $result = $this->dispatcher->run('hello', array('Bob'));
        $this->assertEquals('Hello, Fred! Have a nice day!', $result);
    }

    // Test an invalid callback
    public function testInvalidCallback()
    {
        $this->setExpectedException('Exception', 'Invalid callback specified.');
        $this->dispatcher->execute(array('NonExistentClass', 'nonExistentMethod'));
    }
}