<?php

declare(strict_types=1);

class ActionResolverTest extends \PHPUnit\Framework\TestCase
{
    public function testParseSimplePath(): void
    {
        $result = ActionResolver::parse('/web/home', '', null);
        $this->assertSame('/web/home', $result['urlPath']);
        $this->assertSame(['', 'web', 'home'], $result['pathnames']);
        $this->assertSame([], $result['args']);
    }

    public function testParseStripsQueryString(): void
    {
        $result = ActionResolver::parse('/web/home?id=1&name=test', '', null);
        $this->assertSame('/web/home', $result['urlPath']);
        $this->assertSame(['id' => '1', 'name' => 'test'], $result['args']);
    }

    public function testParseStripsVdname(): void
    {
        $result = ActionResolver::parse('/app/web/home', 'app', null);
        $this->assertSame('/web/home', $result['urlPath']);
        $this->assertSame(['', 'web', 'home'], $result['pathnames']);
    }

    public function testParseAppliesPathAlias(): void
    {
        $result = ActionResolver::parse('/api/v2/users', '', ['/api/v2' => '/web']);
        $this->assertSame('/web/users', $result['urlPath']);
    }

    public function testExtractModuleFromPathname(): void
    {
        $pathnames = ['', 'blog', 'post'];
        $this->assertSame('blog', ActionResolver::extractModule($pathnames, 'web'));
    }

    public function testExtractModuleDefaultWhenEmpty(): void
    {
        $pathnames = ['', ''];
        $this->assertSame('web', ActionResolver::extractModule($pathnames, 'web'));
    }

    public function testExtractModuleDefaultForIndexPhp(): void
    {
        $pathnames = ['', 'index.php', 'something'];
        $this->assertSame('admin', ActionResolver::extractModule($pathnames, 'admin'));
    }

    public function testCollectTailArgs(): void
    {
        // URL: /web/user/detail/id/42/type/premium
        $pathnames = ['', 'web', 'user', 'detail', 'id', '42', 'type', 'premium'];
        // stopIndex=3 means action was found at segment 3
        $result = ActionResolver::collectTailArgs($pathnames, 3, []);
        $this->assertSame(['id' => '42', 'type' => 'premium'], $result);
    }

    public function testCollectTailArgsMergesBaseArgs(): void
    {
        $pathnames = ['', 'web', 'home', 'key', 'val'];
        $result = ActionResolver::collectTailArgs($pathnames, 2, ['qs' => 'existing']);
        $this->assertSame(['qs' => 'existing', 'key' => 'val'], $result);
    }

    public function testCollectTailArgsOddTrailingSegmentGetsNullValue(): void
    {
        // Odd number: last key has no value
        $pathnames = ['', 'web', 'home', 'key'];
        $result = ActionResolver::collectTailArgs($pathnames, 2, []);
        $this->assertSame(['key' => null], $result);
    }
}
