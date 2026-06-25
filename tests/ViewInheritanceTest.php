<?php

declare(strict_types=1);

/**
 * Tests for PlumeView block/extends template inheritance.
 */
class ViewInheritanceTest extends \PHPUnit\Framework\TestCase
{
    private string $tplDir;
    private string $cacheDir;
    private PlumeView $view;

    protected function setUp(): void
    {
        $this->tplDir   = sys_get_temp_dir() . '/plume_view_inherit_' . uniqid();
        $this->cacheDir = sys_get_temp_dir() . '/plume_view_cache_' . uniqid();
        mkdir($this->tplDir,   0755, true);
        mkdir($this->cacheDir, 0755, true);

        $this->view = new PlumeView($this->tplDir);
        $this->view->extension = '.tpl.php';
        $this->view->cachePath = $this->cacheDir;
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->tplDir);
        $this->cleanDir($this->cacheDir);
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->cleanDir($f) : unlink($f);
        }
        rmdir($dir);
    }

    private function write(string $name, string $content): void
    {
        file_put_contents($this->tplDir . '/' . $name . '.tpl.php', $content);
    }

    private function fetch(string $file): string
    {
        ob_start();
        $this->view->render($file, [], false);
        return ob_get_clean();
    }

    // ---------------------------------------------------------------------------
    // Basic block compilation (no inheritance)
    // ---------------------------------------------------------------------------

    public function testBlockCompilesAndOutputsContent(): void
    {
        $this->write('simple_block', "{block 'title'}Hello{/block}");
        $output = $this->fetch('simple_block');
        $this->assertStringContainsString('Hello', $output);
    }

    public function testYieldOutputsEmptyStringByDefault(): void
    {
        $this->write('yield_only', "{yield 'missing_block'}");
        $output = $this->fetch('yield_only');
        $this->assertSame('', trim($output));
    }

    // ---------------------------------------------------------------------------
    // Template inheritance: child overrides parent block
    // ---------------------------------------------------------------------------

    public function testChildOverridesParentBlock(): void
    {
        // Parent template
        $this->write('base', '<title>{yield \'title\'}</title><body>{yield \'content\'}</body>');

        // Child template
        $this->write('page', implode("\n", [
            "{extends 'base'}",
            "{block 'title'}My Page{/block}",
            "{block 'content'}Hello World{/block}",
        ]));

        $output = $this->fetch('page');
        $this->assertStringContainsString('<title>My Page</title>', $output);
        $this->assertStringContainsString('<body>Hello World</body>', $output);
    }

    public function testParentBlockUsedAsDefaultWhenChildOmits(): void
    {
        $this->write('base2', '<footer>{yield \'footer\'}</footer>');
        $this->write('page2', implode("\n", [
            "{extends 'base2'}",
            "{block 'title'}Only Title{/block}",
            // 'footer' block intentionally omitted
        ]));

        $output = $this->fetch('page2');
        // Footer should be empty (no default in parent, child didn't define it)
        $this->assertStringContainsString('<footer></footer>', $output);
    }

    // ---------------------------------------------------------------------------
    // Variable substitution still works in inherited templates
    // ---------------------------------------------------------------------------

    public function testVariableEscapingWorksInChild(): void
    {
        $this->write('base3', '<h1>{yield \'heading\'}</h1>');
        $this->write('page3', implode("\n", [
            "{extends 'base3'}",
            '{block \'heading\'}{$title}{/block}',
        ]));

        $this->view->set('title', '<script>xss</script>');
        $output = $this->fetch('page3');
        $this->assertStringContainsString('&lt;script&gt;', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    // ---------------------------------------------------------------------------
    // Comment stripping still works
    // ---------------------------------------------------------------------------

    public function testCommentsStrippedInInheritedTemplate(): void
    {
        $this->write('base4', '<div>{yield \'body\'}</div>');
        $this->write('page4', implode("\n", [
            "{extends 'base4'}",
            "{block 'body'}{# this comment should disappear #}visible{/block}",
        ]));

        $output = $this->fetch('page4');
        $this->assertStringNotContainsString('this comment', $output);
        $this->assertStringContainsString('visible', $output);
    }

    // ---------------------------------------------------------------------------
    // Raw variable works in inherited templates
    // ---------------------------------------------------------------------------

    public function testRawVariableInChild(): void
    {
        $this->write('base5', '{yield \'body\'}');
        $this->write('page5', implode("\n", [
            "{extends 'base5'}",
            '{block \'body\'}{$html|raw}{/block}',
        ]));

        $this->view->set('html', '<b>bold</b>');
        $output = $this->fetch('page5');
        $this->assertStringContainsString('<b>bold</b>', $output);
    }
}
