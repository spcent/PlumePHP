<?php
/**
 * PlumePHP: An extensible micro-framework.
 */

// 加载框架文件
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'PlumePHP.php';

require_once __DIR__.'/classes/Hello.php';

class MapTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var PlumeEngine
     */
    private $app;

    public function setUp(): void
    {
        $this->app = new PlumeEngine();
    }

    // Map a closure
    public function testClosureMapping()
    {
        $this->app->map('map1', function(){
            return 'hello';
        });

        $result = $this->app->map1();
        $this->assertEquals('hello', $result);
    }

    // Map a function
    public function testFunctionMapping()
    {
        $this->app->map('map2', function(){
            return 'hello';
        });

        $result = $this->app->map2();
        $this->assertEquals('hello', $result);
    }

    // Map a class method
    public function testClassMethodMapping()
    {
        $h = new Hello();
        $this->app->map('map3', array($h, 'sayHi'));

        $result = $this->app->map3();
        $this->assertEquals('hello', $result);
    }

    // Map a static class method
    public function testStaticClassMethodMapping()
    {
        $this->app->map('map4', array('Hello', 'sayBye'));

        $result = $this->app->map4();
        $this->assertEquals('goodbye', $result);
    }

    // Unmapped method
    public function testUnmapped()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('doesNotExist must be a mapped method.');
        $this->app->doesNotExist();
    }
}