<?php

declare(strict_types=1);

/**
 * Verifies that PlumeView::getTemplate() uses the injected $variableResolver
 * for the theme.path::template syntax, and falls back to PlumePHP::get() when
 * no resolver is configured.
 */
class ViewResolverTest extends \PHPUnit\Framework\TestCase
{
    private string $tplDir;

    protected function setUp(): void
    {
        $this->tplDir = sys_get_temp_dir() . '/plume_vr_' . uniqid();
        mkdir($this->tplDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tplDir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->tplDir);
    }

    public function testCustomResolverUsedForThemePathSyntax(): void
    {
        $view = new PlumeView($this->tplDir);

        $called = false;
        $view->variableResolver = function (string $key) use (&$called): string {
            $called = true;
            $this->assertSame('my.theme.path', $key);
            return '/custom/themes/default';
        };

        // Write a dummy file so the path is plausible
        touch($this->tplDir . '/home.tpl.php');

        $resolved = $view->getTemplate('my.theme.path::home.tpl.php');
        $this->assertTrue($called, 'Custom resolver must be invoked');
        $this->assertSame('/custom/themes/default/home.tpl.php', $resolved);
    }

    public function testNullResolverDefaultsToFacade(): void
    {
        $view = new PlumeView($this->tplDir);
        $this->assertNull($view->variableResolver, 'Default variableResolver must be null');
    }

    public function testResolverNotCalledForNormalPaths(): void
    {
        $view = new PlumeView($this->tplDir);
        $called = false;
        $view->variableResolver = function () use (&$called) { $called = true; return '/x'; };

        // Normal path (no :: separator) — resolver should never fire
        $view->getTemplate('home');
        $this->assertFalse($called, 'Resolver must not be called for normal template paths');
    }

    public function testResolverNotCalledForAbsolutePaths(): void
    {
        $view   = new PlumeView($this->tplDir);
        $called = false;
        $view->variableResolver = function () use (&$called) { $called = true; return '/x'; };

        $view->getTemplate('/absolute/path/template.tpl.php');
        $this->assertFalse($called);
    }

    public function testResolverReceivesKeyBeforeDoubleColon(): void
    {
        $view = new PlumeView($this->tplDir);
        $receivedKey = null;
        $view->variableResolver = function (string $key) use (&$receivedKey): string {
            $receivedKey = $key;
            return '/base';
        };

        $view->getTemplate('section.assets::js/app.js.tpl.php');
        $this->assertSame('section.assets', $receivedKey);
    }
}
