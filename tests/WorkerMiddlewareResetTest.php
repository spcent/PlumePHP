<?php

declare(strict_types=1);

/**
 * Verifies that resetForWorker() clears registered middlewares so they do not
 * accumulate across requests in long-lived worker processes.
 */
class WorkerMiddlewareResetTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        PlumePHP::resetForWorker();
    }

    public function testMiddlewaresClearedOnWorkerReset(): void
    {
        $engine = PlumePHP::app();
        $this->assertEmpty($engine->getMiddlewares(), 'Precondition: no middlewares registered');

        $mw = new class implements PlumeMiddlewareInterface {
            public function process(PlumeRequest $req, PlumeRequestHandlerInterface $next): PlumeResponse
            {
                return $next->handle($req);
            }
        };
        $engine->addMiddleware($mw);
        $this->assertCount(1, $engine->getMiddlewares());

        PlumePHP::resetForWorker();
        $this->assertEmpty(PlumePHP::app()->getMiddlewares(),
            'Middlewares must be cleared after resetForWorker()');
    }

    public function testMultipleMiddlewaresClearedOnReset(): void
    {
        $engine = PlumePHP::app();
        $make   = fn() => new class implements PlumeMiddlewareInterface {
            public function process(PlumeRequest $req, PlumeRequestHandlerInterface $next): PlumeResponse
            {
                return $next->handle($req);
            }
        };

        $engine->addMiddleware($make());
        $engine->addMiddleware($make());
        $engine->addMiddleware($make());
        $this->assertCount(3, $engine->getMiddlewares());

        PlumePHP::resetForWorker();
        $this->assertEmpty(PlumePHP::app()->getMiddlewares());
    }

    public function testResetPreservesDefaultModule(): void
    {
        PlumePHP::app()->set('plumephp.default.module', 'api');
        PlumePHP::resetForWorker();
        $this->assertSame('api', PlumePHP::app()->get('plumephp.default.module'));
    }
}
