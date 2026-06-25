<?php

declare(strict_types=1);

class DocGeneratorTest extends \PHPUnit\Framework\TestCase
{
    private string $appDir;

    protected function setUp(): void
    {
        $this->appDir = sys_get_temp_dir() . '/plume_docgen_' . uniqid();
        mkdir($this->appDir . '/web/actions', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->appDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->removeDir($f) : unlink($f);
        }
        rmdir($dir);
    }

    private function writeAction(string $name, string $content): void
    {
        file_put_contents($this->appDir . '/web/actions/' . $name . '.action.php', $content);
    }

    public function testEmptyAppReturnsValidOpenApiSkeleton(): void
    {
        $doc = PlumeDocGenerator::generate($this->appDir);
        $this->assertSame('3.0.3', $doc['openapi']);
        $this->assertArrayHasKey('info', $doc);
        $this->assertArrayHasKey('paths', $doc);
        $this->assertEmpty($doc['paths']);
    }

    public function testSingleGetEndpointIsParsed(): void
    {
        $this->writeAction('users', <<<'PHP'
        <?php
        /**
         * @api GET /api/users
         * @summary List all users
         * @tag users
         */
        class web_users_action {}
        PHP);

        $doc = PlumeDocGenerator::generate($this->appDir);
        $this->assertArrayHasKey('/api/users', $doc['paths']);
        $this->assertArrayHasKey('get', $doc['paths']['/api/users']);
        $this->assertSame('List all users', $doc['paths']['/api/users']['get']['summary']);
    }

    public function testParamAnnotationWithPathLocation(): void
    {
        $this->writeAction('user_detail', <<<'PHP'
        <?php
        /**
         * @api GET /api/users/{id}
         * @param int $id User ID
         * @tag users
         */
        class web_user_detail_action {}
        PHP);

        $doc    = PlumeDocGenerator::generate($this->appDir);
        $params = $doc['paths']['/api/users/{id}']['get']['parameters'];
        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]['name']);
        $this->assertSame('path', $params[0]['in']);
        $this->assertTrue($params[0]['required']);
        $this->assertSame('integer', $params[0]['schema']['type']);
    }

    public function testQueryParamWithExplicitLocation(): void
    {
        $this->writeAction('search', <<<'PHP'
        <?php
        /**
         * @api GET /api/search
         * @param string $q Search query (query)
         */
        class web_search_action {}
        PHP);

        $doc    = PlumeDocGenerator::generate($this->appDir);
        $params = $doc['paths']['/api/search']['get']['parameters'];
        $this->assertSame('query', $params[0]['in']);
        $this->assertFalse($params[0]['required']);
    }

    public function testResponseAnnotationIsParsed(): void
    {
        $this->writeAction('create', <<<'PHP'
        <?php
        /**
         * @api POST /api/users
         * @response 201 {"code":0,"data":{"id":1}}
         * @response 422 {"code":422,"msg":"Validation failed"}
         */
        class web_create_action {}
        PHP);

        $doc       = PlumeDocGenerator::generate($this->appDir);
        $responses = $doc['paths']['/api/users']['post']['responses'];
        $this->assertArrayHasKey(201, $responses);
        $this->assertArrayHasKey(422, $responses);
        $example = $responses[201]['content']['application/json']['example'];
        $this->assertSame(0, $example['code']);
    }

    public function testTagsAreCollected(): void
    {
        $this->writeAction('a', <<<'PHP'
        <?php
        /**
         * @api GET /a
         * @tag alpha
         */
        class a {}
        PHP);
        $this->writeAction('b', <<<'PHP'
        <?php
        /**
         * @api GET /b
         * @tag beta
         */
        class b {}
        PHP);

        $doc      = PlumeDocGenerator::generate($this->appDir);
        $tagNames = array_column($doc['tags'], 'name');
        $this->assertContains('alpha', $tagNames);
        $this->assertContains('beta', $tagNames);
    }

    public function testAuthAnnotationAddsSecurityEntry(): void
    {
        $this->writeAction('secure', <<<'PHP'
        <?php
        /**
         * @api DELETE /api/users/{id}
         * @auth bearer
         */
        class secure {}
        PHP);

        $doc      = PlumeDocGenerator::generate($this->appDir);
        $security = $doc['paths']['/api/users/{id}']['delete']['security'];
        $this->assertNotEmpty($security);
        $this->assertArrayHasKey('bearer', $security[0]);
    }

    public function testMultipleEndpointsInOneFile(): void
    {
        $this->writeAction('multi', <<<'PHP'
        <?php
        /**
         * @api GET /api/items
         * @summary List items
         */
        /**
         * @api POST /api/items
         * @summary Create item
         */
        class multi {}
        PHP);

        $doc = PlumeDocGenerator::generate($this->appDir);
        $this->assertArrayHasKey('/api/items', $doc['paths']);
        $this->assertArrayHasKey('get', $doc['paths']['/api/items']);
        $this->assertArrayHasKey('post', $doc['paths']['/api/items']);
    }

    public function testCustomInfoBlockIsUsed(): void
    {
        $doc = PlumeDocGenerator::generate($this->appDir, [
            'title'   => 'My API',
            'version' => '2.0.0',
        ]);
        $this->assertSame('My API', $doc['info']['title']);
        $this->assertSame('2.0.0', $doc['info']['version']);
    }

    public function testDefaultResponseAddedWhenNoneAnnotated(): void
    {
        $this->writeAction('noresponse', <<<'PHP'
        <?php
        /** @api GET /api/noop */
        class noresponse {}
        PHP);

        $doc       = PlumeDocGenerator::generate($this->appDir);
        $responses = $doc['paths']['/api/noop']['get']['responses'];
        $this->assertArrayHasKey(200, $responses);
    }
}
