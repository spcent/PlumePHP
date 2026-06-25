<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class RouteGroupTest extends \PHPUnit\Framework\TestCase
{
    private PlumeRouter $router;

    public function setUp(): void
    {
        $this->router = new PlumeRouter();
    }

    public function testGroupPrefixIsApplied(): void
    {
        $this->router->group('/api', function (PlumeRouter $r) {
            $r->map('GET /users', function () { echo 'users'; });
            $r->map('GET /posts', function () { echo 'posts'; });
        });

        $routes = $this->router->getRoutes();
        $this->assertCount(2, $routes);
        $this->assertSame('/api/users', $routes[0]->pattern);
        $this->assertSame('/api/posts', $routes[1]->pattern);
    }

    public function testGroupWithTrailingSlashPrefix(): void
    {
        $this->router->group('/api/', function (PlumeRouter $r) {
            $r->map('GET /items', function () {});
        });

        $routes = $this->router->getRoutes();
        $this->assertSame('/api/items', $routes[0]->pattern);
    }

    public function testGroupPreservesHttpMethods(): void
    {
        $this->router->group('/v1', function (PlumeRouter $r) {
            $r->map('POST /login', function () {});
        });

        $routes = $this->router->getRoutes();
        $this->assertSame(['POST'], $routes[0]->methods);
    }

    public function testGroupCallbackReceivesRouter(): void
    {
        $receivedRouter = null;
        $this->router->group('/x', function (PlumeRouter $r) use (&$receivedRouter) {
            $receivedRouter = $r;
        });
        $this->assertInstanceOf(PlumeRouter::class, $receivedRouter);
    }

    public function testGroupRoutesMatchRequestUrl(): void
    {
        $this->router->group('/api', function (PlumeRouter $r) {
            $r->map('GET /ping', function () { echo 'pong'; });
        });

        $request         = new PlumeRequest();
        $request->url    = '/api/ping';
        $request->method = 'GET';
        $route           = $this->router->route($request);

        $this->assertInstanceOf(PlumeRoute::class, $route);
        $this->expectOutputString('pong');
        ($route->callback)();
    }

    public function testEmptyGroupAddsNoRoutes(): void
    {
        $this->router->group('/empty', function (PlumeRouter $r) {});
        $this->assertCount(0, $this->router->getRoutes());
    }

    public function testNestedGroupsAccumulate(): void
    {
        $this->router->group('/api', function (PlumeRouter $r) {
            $r->map('GET /a', function () {});
        });
        $this->router->group('/v2', function (PlumeRouter $r) {
            $r->map('GET /b', function () {});
        });

        $routes = $this->router->getRoutes();
        $this->assertCount(2, $routes);
        $this->assertSame('/api/a', $routes[0]->pattern);
        $this->assertSame('/v2/b', $routes[1]->pattern);
    }
}
