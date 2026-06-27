<?php

declare(strict_types=1);

/**
 * Verifies that ActionLocator and ActionInvoker throw ActionException instead of
 * calling PlumePHP::app()->_halt() directly, decoupling them from the facade.
 */
class ActionExceptionTest extends \PHPUnit\Framework\TestCase
{
    // -----------------------------------------------------------------------
    // ActionException itself
    // -----------------------------------------------------------------------

    public function testActionExceptionCarriesHttpCode(): void
    {
        $e = new ActionException(404, 'not found');
        $this->assertSame(404, $e->getHttpCode());
        $this->assertSame('not found', $e->getMessage());
    }

    public function testActionExceptionIsRuntimeException(): void
    {
        $e = new ActionException(500, 'server error');
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    // -----------------------------------------------------------------------
    // ActionLocator — invalid segment
    // -----------------------------------------------------------------------

    public function testLocatorThrowsOnInvalidSegment(): void
    {
        ActionLocator::warmCache(null); // clear static cache

        // Segment exceeds max length (> 15 chars)
        $pathnames = ['', 'web', 'thisistoolong12345'];
        $this->expectException(ActionException::class);
        $this->expectExceptionCode(404);
        ActionLocator::locate('web', $pathnames, '/web/thisistoolong12345');
    }

    public function testLocatorThrowsOnMissingIndexFile(): void
    {
        ActionLocator::warmCache(null);

        // Force the real index action to appear absent via the path cache
        $indexPath = APP_PATH . DS . 'web' . DS . 'actions' . DS . 'index.action.php';
        ActionLocator::warmCache([$indexPath => false]);

        $pathnames = ['', 'web', ''];
        try {
            ActionLocator::locate('web', $pathnames, '/web/');
            $this->fail('Expected ActionException');
        } catch (ActionException $e) {
            $this->assertSame(404, $e->getHttpCode());
        } finally {
            ActionLocator::warmCache(null); // always restore
        }
    }

    // -----------------------------------------------------------------------
    // ActionInvoker — missing class / missing run method
    // -----------------------------------------------------------------------

    public function testInvokerThrowsOnMissingClass(): void
    {
        $this->expectException(ActionException::class);
        $this->expectExceptionCode(404);
        ActionInvoker::invoke('NonExistentClass_xyz_abc', '/test/path');
    }

    public function testInvokerThrowsOnMissingRunMethod(): void
    {
        // Define a class that has no run() method
        if (!class_exists('NoRunMethodAction_test')) {
            eval('class NoRunMethodAction_test {}');
        }

        $this->expectException(ActionException::class);
        $this->expectExceptionCode(404);
        ActionInvoker::invoke('NoRunMethodAction_test', '/test/path');
    }

    public function testInvokerReturnsRunResult(): void
    {
        if (!class_exists('RunReturnsHello_test')) {
            eval('class RunReturnsHello_test { public function run() { return "hello"; } }');
        }

        $result = ActionInvoker::invoke('RunReturnsHello_test', '/test/path');
        $this->assertSame('hello', $result);
    }

    // -----------------------------------------------------------------------
    // ActionException http code preserved in message
    // -----------------------------------------------------------------------

    public function testActionExceptionMessageIsPreserved(): void
    {
        $msg = '!!! 404(missing action file) !!! uri: /foo/bar action file: /web/actions/bar.action.php';
        $e   = new ActionException(404, $msg);
        $this->assertStringContainsString('missing action file', $e->getMessage());
    }
}
