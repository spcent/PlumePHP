<?php
/**
 * PlumePHP: An extensible micro-framework.
 */

// 加载框架文件
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class VariableTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PlumeEngine
     */
    private $app;

    public function setUp()
    {
        $this->app = new PlumeEngine();
    }

    // Set and get a variable
    public function testSetAndGet()
    {
        $this->app->set('a', 1);
        $var = $this->app->get('a');
        $this->assertEquals(1, $var);
    }

    // Clear a specific variable
    public function testClear()
    {
        $this->app->set('b', 1);
        $this->app->clear('b');
        $var = $this->app->get('b');
        $this->assertEquals(null, $var);
    }

    // Clear all variables
    public function testClearAll()
    {
        $this->app->set('c', 1);
        $this->app->clear();
        $var = $this->app->get('c');
        $this->assertEquals(null, $var);
    }

    // Check if a variable exists
    public function testHas()
    {
        $this->app->set('d', 1);
        $this->assertTrue($this->app->has('d'));
    }
}