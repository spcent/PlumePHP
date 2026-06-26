<?php

declare(strict_types=1);

class PlumeRoute
{
    /** @var array<string, string|null> Route parameters populated during matchUrl() */
    public array $params = [];

    /** @var string Matching regular expression populated during matchUrl() */
    public string $regex = '';

    /** @var string URL splat content populated during matchUrl() */
    public string $splat = '';

    /** Pre-compiled regex pattern (populated by compile() or setCompiled()). */
    private string $compiledRegex = '';

    /** @var array<string, null> Named parameter keys collected during regex compilation. */
    private array $compiledIds = [];

    /**
     * @param string   $pattern  URL pattern
     * @param mixed    $callback Callback function (callable, not typed as property type)
     * @param string[] $methods  HTTP methods
     * @param bool     $pass     Pass self in callback parameters
     */
    public function __construct(
        public readonly string $pattern,
        public readonly mixed $callback,
        public readonly array $methods,
        public readonly bool $pass
    ) {}

    /**
     * Pre-compiles the route pattern to a regex and stores it.
     * Returns [regex, namedParamKeys] for caching.
     */
    /** @return array{0: string, 1: array<string, null>} */
    public function compile(): array
    {
        if ($this->compiledRegex !== '') {
            return [$this->compiledRegex, $this->compiledIds];
        }
        $ids   = [];
        $regex = str_replace([')', '/*'], [')?', '(/?|/.*?)'], $this->pattern);
        $regex = preg_replace_callback(
            '#@([\w]+)(:([^/\(\)]*))?#',
            function ($matches) use (&$ids) {
                $ids[$matches[1]] = null;
                return isset($matches[3])
                    ? '(?P<'.$matches[1].'>'.$matches[3].')'
                    : '(?P<'.$matches[1].'>[^/\?]+)';
            },
            $regex
        );
        $last_char = substr($this->pattern, -1);
        $regex    .= ($last_char === '/') ? '?' : '/?';
        $this->compiledRegex = $regex;
        $this->compiledIds   = $ids;
        return [$regex, $ids];
    }

    /**
     * Loads a pre-compiled regex from the route cache.
     */
    /**
     * @param array<string, null> $ids
     */
    public function setCompiled(string $regex, array $ids): void
    {
        $this->compiledRegex = $regex;
        $this->compiledIds   = $ids;
    }

    /**
     * Checks if a URL matches the route pattern. Also parses named parameters in the URL.
     *
     * @param string $url            Requested URL
     * @param bool   $case_sensitive Case sensitive matching
     *
     * @return bool Match status
     */
    public function matchUrl(string $url, bool $case_sensitive = false): bool
    {
        // Wildcard or exact match
        if ('*' === $this->pattern || $this->pattern === $url) {
            return true;
        }

        $last_char = substr($this->pattern, -1);
        // Get splat
        if ('*' === $last_char) {
            $n = 0;
            $len = strlen($url);
            $count = substr_count($this->pattern, '/');
            for ($i = 0; $i < $len; $i++) {
                if ('/' === $url[$i]) {
                    $n++;
                }
                if ($n === $count) {
                    break;
                }
            }

            $this->splat = (string) substr($url, $i + 1);
        }

        // Use pre-compiled regex if available (route caching), otherwise compile now
        [$regex, $ids] = $this->compile();

        // Attempt to match route and named parameters
        if (preg_match('#^'.$regex.'(?:\?.*)?$#'.(($case_sensitive) ? '' : 'i'), $url, $matches)) {
            foreach ($ids as $k => $v) {
                $this->params[$k] = (array_key_exists($k, $matches))
                    ? urldecode($matches[$k]) : null;
            }
            $this->regex = $regex;

            return true;
        }

        return false;
    }

    /**
     * Checks if an HTTP method matches the route methods.
     *
     * @param string $method HTTP method
     *
     * @return bool Match status
     */
    public function matchMethod(string $method): bool
    {
        return in_array('*', $this->methods, true) || in_array($method, $this->methods, true);
    }

    /**
     * Returns a new route with the given prefix prepended to the pattern.
     * Used by PlumeRouter::group().
     */
    public function withPrefix(string $prefix): self
    {
        $prefix  = rtrim($prefix, '/');
        $pattern = $prefix . '/' . ltrim($this->pattern, '/');
        return new self($pattern, $this->callback, $this->methods, $this->pass);
    }

    /**
     * Returns a new route with a different callback.
     * Used by PlumeRouter::group() to wrap middleware.
     */
    public function withCallback(callable $callback): self
    {
        return new self($this->pattern, $callback, $this->methods, $this->pass);
    }
}
/**
 * The PlumeRouter class is responsible for routing an HTTP request to
 * an assigned callback function. The PlumeRouter tries to match the
 * requested URL against a series of URL patterns.
 */
