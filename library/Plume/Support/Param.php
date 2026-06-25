<?php

declare(strict_types=1);

class PlumeParam
{
    public $module;
    public $class;
    public $func;
    private $urlparam = [];

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

    public function __get(string $pn)
    {
        $v = $this->getValue($pn);
        if (is_string($v)) {
            $v = htmlentities($v, ENT_QUOTES);
        }

        return $v;
    }

    public function __set(string $pn, string $val)
    {
        if (!$pn) {
            return;
        }
        $this->urlparam[$pn] = $val;
    }

    public function __toString()
    {
        return json_encode($this->urlparam, JSON_UNESCAPED_UNICODE);
    }

    public function getValue(string $pn, string $default = '')
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

    public function updateParams(array $arr)
    {
        if (!$arr) {
            return $this;
        }

        $this->urlparam = array_merge($this->urlparam, $arr);

        return $this;
    }

    public function toArray()
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
