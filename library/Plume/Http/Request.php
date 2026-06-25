<?php

declare(strict_types=1);

class PlumeRequest
{
    /**
     * @var string URL being requested
     */
    public $url;

    /**
     * @var string Parent subdirectory of the URL
     */
    public $base;

    /**
     * @var string Request method (GET, POST, PUT, DELETE)
     */
    public $method;

    /**
     * @var string Referrer URL
     */
    public $referrer;

    /**
     * @var string IP address of the client
     */
    public $ip;

    /**
     * @var bool Whether the request is an AJAX request
     */
    public $ajax;

    /**
     * @var string Server protocol (http, https)
     */
    public $scheme;

    /**
     * @var string Browser information
     */
    public $user_agent;

    /**
     * @var string Content type
     */
    public $type;

    /**
     * @var int Content length
     */
    public $length;

    /**
     * @var PlumeCollection Query string parameters
     */
    public $query;

    /**
     * @var PlumeCollection Post parameters
     */
    public $data;

    /**
     * @var PlumeCollection Cookie parameters
     */
    public $cookies;

    /**
     * @var PlumeCollection Uploaded files
     */
    public $files;

    /**
     * @var bool Whether the connection is secure
     */
    public $secure;

    /**
     * @var string HTTP accept parameters
     */
    public $accept;

    /**
     * Constructor.
     *
     * @param array $config Request configuration
     */
    public function __construct(array $config = [])
    {
        // Default properties
        if (empty($config)) {
            $config = [
                'url'        => str_replace('@', '%40', self::getVar('REQUEST_URI', '/')),
                'base'       => str_replace(['\\', ' '], ['/', '%20'], dirname(self::getVar('SCRIPT_NAME'))),
                'method'     => self::getMethod(),
                'referrer'   => self::getVar('HTTP_REFERER'),
                'ip'         => self::getVar('REMOTE_ADDR'),
                'ajax'       => 'XMLHttpRequest' === self::getVar('HTTP_X_REQUESTED_WITH'),
                'scheme'     => self::getVar('SERVER_PROTOCOL', 'HTTP/1.1'),
                'user_agent' => self::getVar('HTTP_USER_AGENT'),
                'type'       => self::getVar('CONTENT_TYPE'),
                'length'     => self::getVar('CONTENT_LENGTH', 0),
                'query'      => new PlumeCollection($_GET),
                'data'       => new PlumeCollection($_POST),
                'cookies'    => new PlumeCollection($_COOKIE),
                'files'      => new PlumeCollection($_FILES),
                'secure'     => 'off' !== self::getVar('HTTPS', 'off'),
                'accept'     => self::getVar('HTTP_ACCEPT'),
            ];
        }

        $this->init($config);
    }

    /**
     * Initialize request properties.
     *
     * @param array $properties Array of request properties
     */
    public function init(array $properties = [])
    {
        // Set all the defined properties
        foreach ($properties as $name => $value) {
            $this->{$name} = $value;
        }

        // Get the requested URL without the base directory
        if ('/' !== $this->base && strlen($this->base) > 0 && 0 === strpos($this->url, $this->base)) {
            $this->url = substr($this->url, strlen($this->base));
        }

        // Default url
        if (empty($this->url)) {
            $this->url = '/';
        } else {
            // Merge URL query parameters with $_GET
            $_GET += self::parseQuery($this->url);
            $this->query->setData($_GET);
        }

        // Check for JSON input
        if (0 === strpos($this->type, 'application/json')) {
            $body = $this->getBody();
            if ('' !== $body) {
                $data = json_decode($body, true);
                if (null !== $data) {
                    $this->data->setData($data);
                }
            }
        }
    }

    /**
     * Gets the body of the request.
     *
     * php://input is buffered and safe to read multiple times in PHP 7.1+.
     * We intentionally avoid a static cache here so persistent worker
     * processes (FrankenPHP, RoadRunner) do not carry a previous request's
     * body into the next request.
     *
     * @return string Raw HTTP request body
     */
    public static function getBody(): string
    {
        $method = self::getMethod();
        if ('POST' === $method || 'PUT' === $method || 'PATCH' === $method) {
            return file_get_contents('php://input') ?? '';
        }
        return '';
    }

    /**
     * Gets the request method.
     */
    public static function getMethod(): string
    {
        $method = self::getVar('REQUEST_METHOD', 'GET');
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        } elseif (isset($_REQUEST['_method'])) {
            $method = $_REQUEST['_method'];
        }

        return strtoupper($method);
    }

    /**
     * Gets a variable from $_SERVER using $default if not provided.
     *
     * @param string $var     Variable name
     * @param string $default Default value to substitute
     *
     * @return string Server variable value
     */
    public static function getVar(string $var, $default = '')
    {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : $default;
    }

    /**
     * Parse query parameters from a URL.
     *
     * @param string $url URL string
     *
     * @return array Query parameters
     */
    public static function parseQuery(string $url): array
    {
        $params = [];
        $args = parse_url($url);
        if (isset($args['query'])) {
            parse_str($args['query'], $params);
        }

        return $params;
    }

    /**
     * 通关ua判断是否为手机.
     */
    public function isMobile(): bool
    {
        //正则表达式,批配不同手机浏览器UA关键词。
        $regex_match = '/(nokia|iphone|android|motorola|^mot\\-|softbank|foma|docomo|kddi|up\\.browser|up\\.link|';
        $regex_match .= 'htc|dopod|blazer|netfront|helio|hosin|huawei|novarra|CoolPad|webos|techfaith|palmsource|';
        $regex_match .= 'blackberry|alcatel|amoi|ktouch|nexian|samsung|^sam\\-|s[cg]h|^lge|ericsson|philips|sagem|wellcom|bunjalloo|maui|';
        $regex_match .= 'symbian|smartphone|midp|wap|phone|windows ce|iemobile|^spice|^bird|^zte\\-|longcos|pantech|gionee|^sie\\-|portalmmm|';
        $regex_match .= 'jig\\s browser|hiptop|^ucweb|^benq|haier|^lct|opera\\s*mobi|opera\\*mini|320×320|240×320|176×220';
        $regex_match .= '|mqqbrowser|juc|iuc|ios|ipad';
        $regex_match .= ')/i';

        return isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])
                        or preg_match($regex_match, strtolower($_SERVER['HTTP_USER_AGENT']));
    }
}
/**
 * The PlumeResponse class represents an HTTP response. The object
 * contains the response headers, HTTP status code, and response
 * body.
 */
