<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'PlumePHP.php';

class ViewCacheTest extends \PHPUnit\Framework\TestCase
{
    private string $tplDir;
    private string $cacheDir;
    private PlumeView $view;

    public function setUp(): void
    {
        $base = sys_get_temp_dir() . '/plume_view_test_' . getmypid();
        $this->tplDir   = $base . '/tpl';
        $this->cacheDir = $base . '/cache';
        @mkdir($this->tplDir,   0755, true);
        @mkdir($this->cacheDir, 0755, true);

        $this->view = new PlumeView($this->tplDir);
        $this->view->cachePath = $this->cacheDir;
    }

    public function tearDown(): void
    {
        foreach (glob($this->cacheDir . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->cacheDir);
        foreach (glob($this->tplDir . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->tplDir);
        @rmdir(dirname($this->tplDir));
    }

    private function writeTpl(string $name, string $content): string
    {
        $path = $this->tplDir . '/' . $name . '.tpl.php';
        file_put_contents($path, $content);
        return $path;
    }

    public function testPlainTemplateRendersWithoutCache(): void
    {
        $this->view->cachePath = '';
        $this->writeTpl('plain', '<p>Hello</p>');
        ob_start();
        $this->view->render('plain', [], false);
        $out = ob_get_clean();
        $this->assertSame('<p>Hello</p>', $out);
    }

    public function testSyntaxSugarEscapesVar(): void
    {
        $this->writeTpl('escape', '{$name}');
        $this->view->set('name', '<b>World</b>');
        ob_start();
        $this->view->render('escape', [], false);
        $out = ob_get_clean();
        $this->assertStringContainsString('&lt;b&gt;World&lt;/b&gt;', $out);
    }

    public function testSyntaxSugarRawVar(): void
    {
        $this->writeTpl('raw', '{$html|raw}');
        $this->view->set('html', '<b>Bold</b>');
        ob_start();
        $this->view->render('raw', [], false);
        $out = ob_get_clean();
        $this->assertSame('<b>Bold</b>', $out);
    }

    public function testCommentIsStripped(): void
    {
        $this->writeTpl('comment', 'before{# this is a comment #}after');
        ob_start();
        $this->view->render('comment', [], false);
        $out = ob_get_clean();
        $this->assertSame('beforeafter', $out);
    }

    public function testCacheFileIsCreated(): void
    {
        $this->writeTpl('cached', 'static content');
        ob_start();
        $this->view->render('cached', [], false);
        ob_end_clean();

        $files = glob($this->cacheDir . '/*.php') ?: [];
        $this->assertCount(1, $files);
    }

    public function testCacheFileIsReused(): void
    {
        $this->writeTpl('reuse', 'hello');
        ob_start(); $this->view->render('reuse', [], false); ob_end_clean();

        $files = glob($this->cacheDir . '/*.php') ?: [];
        $mtime1 = filemtime($files[0]);

        sleep(1);
        ob_start(); $this->view->render('reuse', [], false); ob_end_clean();

        $this->assertSame($mtime1, filemtime($files[0]), 'Cache file should not be rewritten if source unchanged');
    }

    public function testCacheInvalidatedWhenSourceChanges(): void
    {
        $tplPath = $this->writeTpl('change', 'v1');
        ob_start(); $this->view->render('change', [], false); ob_end_clean();

        $files  = glob($this->cacheDir . '/*.php') ?: [];
        $mtime1 = filemtime($files[0]);

        // Ensure source mtime is newer than cache
        sleep(1);
        touch($tplPath, time() + 2);

        ob_start(); $this->view->render('change', [], false); ob_end_clean();

        clearstatcache();
        $this->assertGreaterThan($mtime1, filemtime($files[0]), 'Cache should be regenerated after source change');
    }
}
