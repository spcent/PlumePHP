<?php

declare(strict_types=1);

class PlumeParam
{
    public ?string $module = null;
    public ?string $class = null;
    public ?string $func = null;

    /** @var array<array-key, mixed> */
    private array $urlparam = [];

    /**
     * @param array<array-key, mixed> $params
     */
    public function __construct(array $params = [])
    {
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $this->urlparam);
        }

        if (!empty($_POST)) {
            $this->urlparam = array_merge($this->urlparam, $_POST);
        }

        // Check for JSON input
        if (isset($_SERVER['CONTENT_TYPE'])
            && 0 === strpos($_SERVER['CONTENT_TYPE'], 'application/json')) {
            $body = file_get_contents('php://input');
            if ($body) {
                $data = json_decode($body, true);
                if ($data) {
                    $this->urlparam = array_merge($this->urlparam, $data);
                }
            }
        }

        if ($params) {
            $this->urlparam = array_merge($this->urlparam, $params);
        }
    }

    public function __get(string $pn): mixed
    {
        return $this->getValue($pn);
    }

    /**
     * Returns the parameter value HTML-escaped for safe output in templates.
     * Use this instead of __get() when inserting into HTML context.
     */
    public function html(string $pn): string
    {
        $v = $this->getValue($pn);
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function __set(string $pn, mixed $val)
    {
        if (!$pn) {
            return;
        }
        $this->urlparam[$pn] = $val;
    }

    public function __toString(): string
    {
        return (string) json_encode($this->urlparam, JSON_UNESCAPED_UNICODE);
    }

    public function getValue(string $pn, string $default = ''): mixed
    {
        if (isset($this->urlparam[$pn])) {
            return $this->urlparam[$pn];
        }

        return $default;
    }

    public function has(string $pn): bool
    {
        if (isset($this->urlparam[$pn])) {
            return true;
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $arr
     */
    public function updateParams(array $arr): static
    {
        if (!$arr) {
            return $this;
        }

        $this->urlparam = array_merge($this->urlparam, $arr);

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->urlparam;
    }
}
/**
 * Log class
 * The save path is storage/log, store by day
 * fatal, error and warning will record in .log.wf file
 * sql records will save in .log.sql file.
 */
