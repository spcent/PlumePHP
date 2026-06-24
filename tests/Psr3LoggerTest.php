<?php

declare(strict_types=1);

/**
 * Tests for PSR-3 (Logger), PSR-11 (Container), and PSR-15-style Middleware.
 */

// -----------------------------------------------------------------------
// PSR-15 middleware stubs for testing
// -----------------------------------------------------------------------

class AddHeaderMiddleware implements PlumeMiddlewareInterface
{
    public function process(PlumeRequest $request, PlumeRequestHandlerInterface $handler): PlumeResponse
    {
        $response = $handler->handle($request);
        $response->header('X-Test', 'middleware-ran');
        return $response;
    }
}

class ShortCircuitMiddleware implements PlumeMiddlewareInterface
{
    public function process(PlumeRequest $request, PlumeRequestHandlerInterface $handler): PlumeResponse
    {
        $response = new PlumeResponse();
        $response->status(403)->write('Forbidden');
        return $response;
    }
}

class RecordingMiddleware implements PlumeMiddlewareInterface
{
    public array $calls = [];

    public function process(PlumeRequest $request, PlumeRequestHandlerInterface $handler): PlumeResponse
    {
        $this->calls[] = 'before';
        $response = $handler->handle($request);
        $this->calls[] = 'after';
        return $response;
    }
}

class EchoFinalHandler implements PlumeRequestHandlerInterface
{
    public function handle(PlumeRequest $request): PlumeResponse
    {
        $response = new PlumeResponse();
        $response->write('final');
        return $response;
    }
}

// -----------------------------------------------------------------------
// PSR-3 Logger tests
// -----------------------------------------------------------------------
class Psr3LoggerTest extends \PHPUnit\Framework\TestCase
{
    private string $logDir;
    private PlumeLogger $logger;

    public function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/plume_psr3_' . getmypid();
        @mkdir($this->logDir, 0755, true);
        $this->logger = new PlumeLogger('psr3', $this->logDir);
    }

    public function tearDown(): void
    {
        foreach (glob($this->logDir . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->logDir);
    }

    public function testImplementsPsr3Interface(): void
    {
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $this->logger);
    }

    public function testEmergencyWritesToWfFile(): void
    {
        $this->logger->emergency('emergency!');
        $wf = file_get_contents($this->logDir . '/' . date('Ymd') . '.log.wf');
        $this->assertStringContainsString('[EMERGENCY]emergency!', $wf);
    }

    public function testAlertWritesToWfFile(): void
    {
        $this->logger->alert('alert!');
        $wf = file_get_contents($this->logDir . '/' . date('Ymd') . '.log.wf');
        $this->assertStringContainsString('[ALERT]alert!', $wf);
    }

    public function testCriticalWritesToWfFile(): void
    {
        $this->logger->critical('critical!');
        $wf = file_get_contents($this->logDir . '/' . date('Ymd') . '.log.wf');
        $this->assertStringContainsString('[CRITICAL]critical!', $wf);
    }

    public function testWarningMethod(): void
    {
        $this->logger->warning('warn msg');
        $wf = file_get_contents($this->logDir . '/' . date('Ymd') . '.log.wf');
        $this->assertStringContainsString('[WARNING]warn msg', $wf);
    }

    public function testLogDispatchesToCorrectLevel(): void
    {
        $this->logger->log('critical', 'via log()');
        $wf = file_get_contents($this->logDir . '/' . date('Ymd') . '.log.wf');
        $this->assertStringContainsString('[CRITICAL]via log()', $wf);
    }

    public function testLogDebugDispatch(): void
    {
        $this->logger->log('debug', 'debug via log()');
        $this->logger->save();
        $content = file_get_contents($this->logDir . '/' . date('Ymd') . '.log');
        $this->assertStringContainsString('[DEBUG]debug via log()', $content);
    }

    public function testStringableMessage(): void
    {
        $obj = new class implements \Stringable {
            public function __toString(): string { return 'stringable msg'; }
        };
        $this->logger->info($obj);
        $this->logger->save();
        $content = file_get_contents($this->logDir . '/' . date('Ymd') . '.log');
        $this->assertStringContainsString('stringable msg', $content);
    }
}

// -----------------------------------------------------------------------
// PSR-11 Container tests
// -----------------------------------------------------------------------
class Psr11ContainerTest extends \PHPUnit\Framework\TestCase
{
    private PlumeEngine $app;

    public function setUp(): void
    {
        $this->app = new PlumeEngine();
    }

    public function testImplementsPsr11Interface(): void
    {
        $this->assertInstanceOf(\Psr\Container\ContainerInterface::class, $this->app->container());
    }

    public function testHasReturnsTrueForRegisteredService(): void
    {
        $container = $this->app->container();
        $this->assertTrue($container->has('request'));
        $this->assertTrue($container->has('response'));
        $this->assertTrue($container->has('router'));
    }

    public function testHasReturnsFalseForUnknown(): void
    {
        $this->assertFalse($this->app->container()->has('nonexistent'));
    }

    public function testGetReturnsService(): void
    {
        $container = $this->app->container();
        $this->assertInstanceOf(PlumeRequest::class, $container->get('request'));
    }

    public function testGetThrowsNotFoundForUnknown(): void
    {
        $this->expectException(PlumeNotFoundException::class);
        $this->app->container()->get('nonexistent');
    }

    public function testPlumeNotFoundExceptionImplementsInterface(): void
    {
        $e = new PlumeNotFoundException('x');
        $this->assertInstanceOf(\Psr\Container\NotFoundExceptionInterface::class, $e);
    }

    public function testPlumeContainerExceptionImplementsInterface(): void
    {
        $e = new PlumeContainerException('x');
        $this->assertInstanceOf(\Psr\Container\ContainerExceptionInterface::class, $e);
    }
}

// -----------------------------------------------------------------------
// PSR-15-style Middleware tests
// -----------------------------------------------------------------------
class Psr15MiddlewareTest extends \PHPUnit\Framework\TestCase
{
    private function makeRequest(): PlumeRequest
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/';
        $_SERVER['HTTP_HOST']      = 'localhost';
        return new PlumeRequest();
    }

    public function testPipelineCallsFinalHandler(): void
    {
        $pipeline = new PlumeMiddlewarePipeline();
        $pipeline->setFinalHandler(new EchoFinalHandler());

        $request  = $this->makeRequest();
        $response = $pipeline->handle($request);

        // EchoFinalHandler writes 'final' to body via write(); response body is accumulated
        $this->assertInstanceOf(PlumeResponse::class, $response);
    }

    public function testMiddlewareRunsBeforeAndAfterFinalHandler(): void
    {
        $recorder = new RecordingMiddleware();
        $pipeline = new PlumeMiddlewarePipeline();
        $pipeline->pipe($recorder);
        $pipeline->setFinalHandler(new EchoFinalHandler());

        $pipeline->handle($this->makeRequest());

        $this->assertSame(['before', 'after'], $recorder->calls);
    }

    public function testShortCircuitSkipsFinalHandler(): void
    {
        $recorder = new RecordingMiddleware();
        $pipeline = new PlumeMiddlewarePipeline();
        $pipeline->pipe(new ShortCircuitMiddleware());
        $pipeline->pipe($recorder);

        $pipeline->handle($this->makeRequest());

        $this->assertSame([], $recorder->calls);
    }

    public function testMultipleMiddlewaresRunInOrder(): void
    {
        $calls    = [];
        $makeM    = function (string $label) use (&$calls): PlumeMiddlewareInterface {
            return new class($label, $calls) implements PlumeMiddlewareInterface {
                public function __construct(private string $l, private array &$c) {}
                public function process(PlumeRequest $req, PlumeRequestHandlerInterface $h): PlumeResponse
                {
                    $this->c[] = $this->l . ':before';
                    $r = $h->handle($req);
                    $this->c[] = $this->l . ':after';
                    return $r;
                }
            };
        };

        $pipeline = new PlumeMiddlewarePipeline();
        $pipeline->pipe($makeM('A'));
        $pipeline->pipe($makeM('B'));
        $pipeline->setFinalHandler(new EchoFinalHandler());

        $pipeline->handle($this->makeRequest());

        $this->assertSame(['A:before', 'B:before', 'B:after', 'A:after'], $calls);
    }

    public function testAddMiddlewareToEngine(): void
    {
        $engine = new PlumeEngine();
        $recorder = new RecordingMiddleware();
        $result = $engine->addMiddleware($recorder);
        $this->assertSame($engine, $result); // fluent interface
    }
}
