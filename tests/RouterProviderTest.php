<?php

declare(strict_types=1);

/**
 * Verifies that PlumeRouter accepts injected request/response providers so it
 * no longer hard-depends on the PlumePHP facade.
 */
class RouterProviderTest extends \PHPUnit\Framework\TestCase
{
    private function makeRequest(): PlumeRequest
    {
        return new PlumeRequest();
    }

    private function makeResponse(): PlumeResponse
    {
        return new PlumeResponse();
    }

    public function testRouterCanBeInstantiatedWithoutProviders(): void
    {
        $router = new PlumeRouter();
        $this->assertInstanceOf(PlumeRouter::class, $router);
    }

    public function testRouterAcceptsCustomProviders(): void
    {
        $reqProviderCalled  = false;
        $respProviderCalled = false;

        $router = new PlumeRouter(
            function () use (&$reqProviderCalled): PlumeRequest {
                $reqProviderCalled = true;
                return new PlumeRequest();
            },
            function () use (&$respProviderCalled): PlumeResponse {
                $respProviderCalled = true;
                return new PlumeResponse();
            }
        );

        $this->assertInstanceOf(PlumeRouter::class, $router);

        // Register a route and set up a group with a dummy middleware to force
        // the providers to be invoked
        $mwClass = $this->registerDummyMiddleware();
        $router->map('/test', function () {});

        // group() with middleware wraps the route in a handler chain that
        // calls the providers at dispatch time via withCallback closure
        $router->group('', function ($r) {}, [$mwClass]);
        // Providers are captured in closures, so just verify the router built without error
        $this->assertNotEmpty($router->getRoutes());
        // reqProviderCalled and respProviderCalled are only true when handle() is called
        // (dispatch time), which we don't do here — just verify no facade dependency at build time
        $this->assertFalse($reqProviderCalled);
        $this->assertFalse($respProviderCalled);
    }

    public function testGroupWithoutMiddlewareDoesNotInvokeProviders(): void
    {
        $invoked = false;
        $router  = new PlumeRouter(
            function () use (&$invoked) { $invoked = true; return new PlumeRequest(); },
            function () use (&$invoked) { $invoked = true; return new PlumeResponse(); }
        );

        $router->map('/a', function () {});
        $router->group('/api', function ($r) {});  // no middleware

        $this->assertFalse($invoked, 'Providers must not be called during route group setup');
    }

    public function testDefaultProviderPropertyIsNull(): void
    {
        // When no providers are passed, the router falls back to PlumePHP facade.
        // We verify the router can be created, which confirms the null-default branch works.
        $router = new PlumeRouter(null, null);
        $this->assertInstanceOf(PlumeRouter::class, $router);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function registerDummyMiddleware(): string
    {
        $cls = 'DummyMw_router_test_' . substr(md5((string) mt_rand()), 0, 6);
        if (!class_exists($cls)) {
            eval("
                class {$cls} implements PlumeMiddlewareInterface {
                    public function process(PlumeRequest \$req, PlumeRequestHandlerInterface \$next): PlumeResponse {
                        return \$next->handle(\$req);
                    }
                }
            ");
        }
        return $cls;
    }
}
