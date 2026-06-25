<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'PlumePHP.php';

/**
 * Verifies that __callStatic fast path and filter path both produce correct results.
 */
class HotPathTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        PlumePHP::app()->init();
    }

    public function testGetAndSetViaFastPath(): void
    {
        PlumePHP::set('test.key', 'fast-path-value');
        $this->assertSame('fast-path-value', PlumePHP::get('test.key'));
    }

    public function testHasViaFastPath(): void
    {
        PlumePHP::set('hp.exists', true);
        $this->assertTrue(PlumePHP::has('hp.exists'));
        $this->assertFalse(PlumePHP::has('hp.missing'));
    }

    public function testGetDispatcherReturnsEvent(): void
    {
        $this->assertInstanceOf(PlumeEvent::class, PlumePHP::app()->getDispatcher());
    }

    public function testHasFiltersReturnsFalseInitially(): void
    {
        $dispatcher = PlumePHP::app()->getDispatcher();
        $this->assertFalse($dispatcher->hasFilters('some-nonexistent-event'));
    }

    public function testHasFiltersReturnsTrueAfterHook(): void
    {
        $dispatcher = PlumePHP::app()->getDispatcher();
        PlumePHP::before('start', function () {});
        $this->assertTrue($dispatcher->hasFilters('start'));
    }

    public function testBeforeFilterOnRegisteredMethodStillWorks(): void
    {
        // 'route' is registered through the event dispatcher (not a direct method),
        // so the hot path does NOT intercept it and the before filter runs normally.
        $called = false;
        PlumePHP::before('route', function () use (&$called) {
            $called = true;
        });

        PlumePHP::route('/hot-path-test', function () {});
        $this->assertTrue($called, 'Before filter on event-registered method should still fire');

        // Clean up
        PlumePHP::app()->getDispatcher()->clear('route');
        PlumePHP::app()->router()->clear();
    }

    public function testDirectMethodsDoNotTriggerEventDispatcherByDesign(): void
    {
        // Direct engine methods (get/set/has/clear) have never gone through
        // PlumeEvent::run(), so before-filters on them have never been invoked.
        // The hot path preserves this behaviour while removing unnecessary overhead.
        $called = false;
        PlumePHP::before('get', function () use (&$called) { $called = true; });

        PlumePHP::get('any.key');

        // Filter should NOT fire — this is intended and matches pre-existing behaviour.
        $this->assertFalse($called);

        PlumePHP::app()->getDispatcher()->clear('get');
    }
}
