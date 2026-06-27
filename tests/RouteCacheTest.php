<?php

declare(strict_types=1);

class RouteCacheTest extends \PHPUnit\Framework\TestCase
{
    private string $cacheFile;
    private PlumeRouter $router;

    public function setUp(): void
    {
        $this->cacheFile = sys_get_temp_dir() . '/plume_route_cache_' . getmypid() . '.php';
        $this->router = new PlumeRouter();
    }

    public function tearDown(): void
    {
        @unlink($this->cacheFile);
    }

    private function makeRequest(string $url, string $method = 'GET'): PlumeRequest
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $url;
        $_SERVER['HTTP_HOST']      = 'localhost';
        return new PlumeRequest();
    }

    public function testRouteCompileReturnsPair(): void
    {
        $route = new PlumeRoute('/user/@id', fn($id) => $id, ['GET'], false);
        [$regex, $ids] = $route->compile();
        $this->assertNotEmpty($regex);
        $this->assertArrayHasKey('id', $ids);
    }

    public function testSetCompiledSkipsRecompile(): void
    {
        $route = new PlumeRoute('/user/@id', fn($id) => $id, ['GET'], false);
        $route->setCompiled('/custom-regex/', ['id' => null]);
        [$regex, $ids] = $route->compile();
        $this->assertSame('/custom-regex/', $regex);
    }

    public function testEnableCacheCreatesFile(): void
    {
        $this->assertFileDoesNotExist($this->cacheFile);

        $this->router->map('/home', fn() => 'home');
        $this->router->map('/about', fn() => 'about');
        $this->router->enableCache($this->cacheFile);

        $request = $this->makeRequest('/home');
        $this->router->route($request);

        $this->assertFileExists($this->cacheFile);
    }

    public function testCacheFileContainsValidPhp(): void
    {
        $this->router->map('/foo/@bar', fn($b) => $b);
        $this->router->enableCache($this->cacheFile);

        $request = $this->makeRequest('/foo/test');
        $this->router->route($request);

        $data = include $this->cacheFile;
        $this->assertIsArray($data);
        $this->assertArrayHasKey('/foo/@bar', $data);
        $this->assertArrayHasKey('regex', $data['/foo/@bar']);
        $this->assertArrayHasKey('ids', $data['/foo/@bar']);
    }

    public function testCacheHitLoadsCompiledRoutes(): void
    {
        // First router: build and save cache
        $router1 = new PlumeRouter();
        $router1->map('/test/@id', fn($id) => "id:$id");
        $router1->enableCache($this->cacheFile);
        $router1->route($this->makeRequest('/test/42'));

        $this->assertFileExists($this->cacheFile);

        // Second router: should load from cache
        $router2 = new PlumeRouter();
        $router2->map('/test/@id', fn($id) => "id:$id");
        $router2->enableCache($this->cacheFile);

        $request = $this->makeRequest('/test/99');
        $route   = $router2->route($request);

        $this->assertInstanceOf(PlumeRoute::class, $route);
        $this->assertSame('99', $route->params['id']);
    }

    public function testRoutingStillWorksWithCaching(): void
    {
        $this->router->map('/ping', fn() => 'pong');
        $this->router->enableCache($this->cacheFile);

        $route = $this->router->route($this->makeRequest('/ping'));
        $this->assertInstanceOf(PlumeRoute::class, $route);
    }

    public function testNoCacheFileWhenCachingDisabled(): void
    {
        $this->router->map('/hello', fn() => 'world');
        $this->router->route($this->makeRequest('/hello'));
        $this->assertFileDoesNotExist($this->cacheFile);
    }

    public function testSaveCacheDoesNotLeaveTmpFile(): void
    {
        $this->router->map('/atomic', fn() => 'ok');
        $this->router->enableCache($this->cacheFile);
        $this->router->route($this->makeRequest('/atomic'));

        $this->assertFileExists($this->cacheFile);
        // Atomic write via temp+rename: no .tmp file should linger
        $tmpGlob = glob(dirname($this->cacheFile) . '/*.tmp') ?: [];
        $this->assertEmpty($tmpGlob, 'Temporary .tmp file should not persist after atomic rename');
    }
}
