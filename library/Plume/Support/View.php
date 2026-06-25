<?php

declare(strict_types=1);

class PlumeView
{
    /**
     * Location of view templates.
     *
     * @var string
     */
    public $path;

    /**
     * File extension.
     *
     * @var string
     */
    public $extension = '.tpl.php';

    /**
     * Theme.
     *
     * @var string
     */
    public $theme = 'default';

    /**
     * View variables.
     *
     * @var array
     */
    protected $vars = [];

    /**
     * Template file.
     *
     * @var string
     */
    private $template;

    /**
     * Resolved content path (set during render).
     */
    private string $content = '';

    /**
     * Constructor.
     *
     * @param string $path Path to templates directory
     */
    public function __construct(string $path = '.')
    {
        $this->path = $path;
    }

    /**
     * Gets a template variable.
     *
     * @param string $key Key
     *
     * @return mixed Value
     */
    public function get(string $key): mixed
    {
        return $this->vars[$key] ?? null;
    }

    /**
     * Sets a template variable.
     *
     * @param mixed $key   Key
     * @param mixed $value Value
     */
    public function set($key, $value = null)
    {
        if (is_array($key) || is_object($key)) {
            foreach ($key as $k => $v) {
                $this->vars[$k] = $v;
            }
        } else {
            $this->vars[$key] = $value;
        }
    }

    /**
     * Checks if a template variable is set.
     *
     * @param string $key Key
     *
     * @return bool If key exists
     */
    public function has(string $key): bool
    {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a template variable. If no key is passed in, clear all variables.
     *
     * @param string $key Key
     */
    public function clear($key = null)
    {
        if (null === $key) {
            $this->vars = [];
        } else {
            unset($this->vars[$key]);
        }
    }

    /**
     * Directory where compiled templates are cached.
     * Set to a writable path to enable compilation caching; leave empty to disable.
     */
    public string $cachePath = '';

    /**
     * Renders a template.
     *
     * @param string $file   Template file
     * @param array  $data   Template data
     * @param string $layout layout file
     *
     * @throws \Exception If template not found
     */
    public function render(string $file, ?array $data = [], string|false $layout = 'layout'): void
    {
        $this->content = $this->getTemplate($file);
        if (!file_exists($this->content)) {
            throw new \Exception("Template file not found: {$this->content}.");
        }

        if ($data) {
            $this->vars = array_merge($this->vars, $data);
        }

        $includeFile = $this->cachePath
            ? $this->getCompiledTemplate($this->content)
            : $this->content;

        extract($this->vars);
        if ('' === $layout || false === $layout) {
            include $includeFile;
        } else {
            $layoutFile = $this->path.DS.$layout.$this->extension;
            if (!file_exists($layoutFile)) {
                throw new \Exception("Layout file not found: {$layoutFile}.");
            }

            $layoutInclude = $this->cachePath
                ? $this->getCompiledTemplate($layoutFile)
                : $layoutFile;
            include $layoutInclude;
        }
    }

    /**
     * Returns the path to a compiled (cached) version of the template.
     * Recompiles when the source is newer than the cached file.
     * Compilation converts lightweight syntax sugar to PHP:
     *   {$var}        → <?= htmlspecialchars($var, ENT_QUOTES, 'UTF-8') ?>
     *   {$var|raw}    → <?= $var ?>
     *   {# comment #} → (removed)
     *
     * @param string $sourcePath Absolute path to the source template
     *
     * @return string Path to the compiled file (falls back to source on failure)
     */
    protected function getCompiledTemplate(string $sourcePath): string
    {
        $cacheDir = rtrim($this->cachePath, '/\\');
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true)) {
            return $sourcePath;
        }

        $cacheFile = $cacheDir . DS . md5($sourcePath) . '.php';

        if (file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($sourcePath)) {
            return $cacheFile;
        }

        $source = file_get_contents($sourcePath);
        if ($source === false) {
            return $sourcePath;
        }

        $compiled = $this->compileTemplate($source);
        if (@file_put_contents($cacheFile, $compiled, LOCK_EX) === false) {
            return $sourcePath;
        }

        return $cacheFile;
    }

    /**
     * Compiles template syntax sugar to plain PHP.
     */
    protected function compileTemplate(string $source): string
    {
        // Strip {# comment #} blocks
        $source = preg_replace('/\{#.*?#\}/s', '', $source);

        // {$var|raw} compiles to unescaped echo tag
        $source = preg_replace(
            '/\{\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(?:\[[^\]]+\])*)\|raw\}/',
            '<?= $$1 ?>',
            $source
        );

        // {$var} compiles to escaped echo tag
        $source = preg_replace(
            '/\{\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(?:\[[^\]]+\])*)\}/',
            "<?= htmlspecialchars((string)\$$1, ENT_QUOTES, 'UTF-8') ?>",
            $source
        );

        return $source;
    }

    /**
     * Gets the output of a template.
     *
     * @param string $file   Template file
     * @param array  $data   Template data
     * @param string $layout Layout file
     * @param bool   $escape When true (default), htmlspecialchars() all string vars before rendering
     *
     * @return string Output of template
     */
    public function fetch(string $file, array $data = [], string $layout = '', bool $escape = true): string
    {
        ob_start();

        if ($escape) {
            $savedVars  = $this->vars;
            $escaper    = static fn(mixed $v): mixed =>
                is_string($v) ? htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $v;
            $this->vars = array_map($escaper, $this->vars);
            $data       = array_map($escaper, $data);
        }

        $this->render($file, $data, $layout);
        $output = ob_get_clean();

        if ($escape) {
            $this->vars = $savedVars;
        }

        return (string) $output;
    }

    /**
     * Checks if a template file exists.
     *
     * @param string $file Template file
     *
     * @return bool Template file exists
     */
    public function exists(string $file): bool
    {
        return file_exists($this->getTemplate($file));
    }

    /**
     * Gets the full path to a template file.
     * E.g.:
     * // in app settings files
     * PlumePHP::set('theme.path', '/home/myrootfolder/public/themes/current_theme').
     *
     * PlumePHP::render('theme.path::myview', $params);
     *
     * @param string $file Template file with prefix
     *
     * @return string Template file location
     */
    public function getTemplate(string $file): string
    {
        $ext = $this->extension;
        if (!empty($ext) && (substr($file, -1 * strlen($ext)) !== $ext)) {
            $file .= $ext;
        }

        $parts = explode('::', $file);
        if (2 === count($parts)) {
            $base_path_key = $parts[0];
            $file_path = $parts[1];

            return rtrim(PlumePHP::get($base_path_key), '/').'/'.$file_path;
        }

        if (('/' === substr($file, 0, 1))) {
            return $file;
        }

        return $this->path.DS.$file;
    }

    /**
     * Displays escaped output.
     *
     * @param string $str String to escape
     *
     * @return string Escaped string
     */
    public function e(string $str)
    {
        echo htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
/**
 * Lightweight .env file parser — replaces parse_ini_file().
 * Supports: # comments, single/double quoted values, inline comments,
 * and type coercion (true/false/null/numbers).
 */
