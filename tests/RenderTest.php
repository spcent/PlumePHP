<?php
/**
 * PlumePHP: An extensible micro-framework.
 */

// 加载框架文件
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class RenderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PlumeEngine
     */
    private $app;

    public function setUp()
    {
        $this->app = new PlumeEngine();
        $this->app->set('plumephp.views.path', __DIR__.'/views');
        $this->app->set('plumephp.views.extension', '.php');
    }

    // Render a view
    public function testRenderView()
    {
        $this->app->render('hello', array('name' => 'Bob'));
        $this->expectOutputString('Hello, Bob!');
    }

    // Renders a view into a layout
    public function testRenderLayout()
    {
        $this->app->render('hello', array('name' => 'Bob'), 'content');
        $this->app->render('layouts/layout');
        $this->expectOutputString('<html>Hello, Bob!</html>');
    }
}