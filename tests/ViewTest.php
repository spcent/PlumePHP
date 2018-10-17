<?php
/**
 * PlumePHP: An extensible micro-framework.
 */

// 加载框架文件
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class ViewTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PlumeView
     */
    private $view;

    public function setUp()
    {
        $this->view = new PlumeView();
        $this->view->path = __DIR__.'/views';
        $this->view->extension = '.php';
    }

    // Set template variables
    public function testVariables()
    {
        $this->view->set('test', 123);
        $this->assertEquals(123, $this->view->get('test'));
        $this->assertTrue($this->view->has('test'));
        $this->assertTrue(!$this->view->has('unknown'));

        $this->view->clear('test');
        $this->assertEquals(null, $this->view->get('test'));
    }

    // Check if template files exist
    public function testTemplateExists()
    {
        $this->assertTrue($this->view->exists('hello.php'));
        $this->assertTrue(!$this->view->exists('unknown.php'));
    }

    // Render a template
    public function testRender()
    {
        $this->view->render('hello', array('name' => 'Bob'), false);
        $this->expectOutputString('Hello, Bob!');
    }

    // Fetch template output
    public function testFetch()
    {
        $output = $this->view->fetch('hello', array('name' => 'Bob'));
        $this->assertEquals('Hello, Bob!', $output);
    }

    // Default extension
    public function testTemplateWithExtension()
    {
        $this->view->set('name', 'Bob');
        $this->view->render('hello.php', null, false);
        $this->expectOutputString('Hello, Bob!');
    }

    // Custom extension
    public function testTemplateWithCustomExtension()
    {
        $this->view->set('name', 'Bob');
        $this->view->extension = '.html';
        $this->view->render('world', null, false);
        $this->expectOutputString('Hello world, Bob!');
    }
}