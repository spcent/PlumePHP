<?php

namespace Plume\Libs;

/**
 * Abstract base class for all action handlers.
 */
abstract class Action
{
    private ?string $csrfToken = null;
    private string $csrfTokenKey = 'plume-csrf-token';
    private string $trueTokenKey = 'plume-csrf';
    private string $csrfHeaderKey = 'X-CSRF-TOKEN';
    private string $csrfPostKey = 'plume_csrf';

    /**
     * Flag for called listener
     *
     * @access private
     * @var boolean
     */
    private $called = false;

    /**
     * User parameters
     *
     * @access private
     * @var array<string, mixed>
     */
    private array $params = [];
    
    /**
     * Whether to enforce CSRF token validation for this action.
     * @var bool
     */
    protected $csrfValidate = true;

    /**
     * Declarative validation rules for request parameters.
     *
     * Format:
     *   protected array $rules = [
     *       'field'   => 'required',
     *       'age'     => 'required|int|min:18|max:120',
     *       'email'   => 'required|email',
     *       'name'    => 'required|string|minLen:2|maxLen:64',
     *       'status'  => 'required|in:active,inactive,pending',
     *       'phone'   => 'required|regex:/^1[3-9]\d{9}$/',
     *       'avatar'  => 'file|mimes:jpg,jpeg,png|maxSize:2048',
     *       'pass2'   => 'required|confirmed:password',
     *       'tags'    => 'array|each:string',
     *   ];
     *
     * Supported constraints:
     *   required, int, float, string, email,
     *   min:<n>, max:<n>, minLen:<n>, maxLen:<n>,
     *   regex:<pattern>, in:<v1,v2,...>,
     *   file, mimes:<ext,...>, maxSize:<kb>,
     *   confirmed[:<other_field>], array, each:<rule>
     *
     * Register project-level custom rules once at boot time:
     *   Action::addRule('phone_cn', fn($v) => (bool)preg_match('/^1[3-9]\d{9}$/', $v));
     *
     * Override validate() for custom logic; return an array of field→message
     * pairs to signal failure, or an empty array on success.
     *
     * @var array<string, string>
     */
    protected array $rules = [];

    /** @var array<string, callable(mixed): bool> Globally registered custom rules */
    private static array $customRules = [];

    /**
     * Register a named custom validation rule available to all Action subclasses.
     *
     * @param string   $name     Rule name used in $rules strings
     * @param callable $callback fn(mixed $value): bool — true means valid
     */
    public static function addRule(string $name, callable $callback): void
    {
        self::$customRules[$name] = $callback;
    }

    /** @var string[] */
    protected array $jsFiles = [];
    /** @var string[] */
    protected array $cssFiles = [];

    /**
     * Set an user defined parameter
     *
     * @access  public
     * @param   string  $name    Parameter name
     * @param   mixed   $value   Value
     * @return self
     */
    public function setParam($name, $value)
    {
        $this->params[$name] = $value;
        return $this;
    }

    /**
     * Get an user defined parameter
     *
     * @access public
     * @param  string  $name            Parameter name
     * @param  mixed   $default         Default value
     * @return mixed
     */
    public function getParam($name, $default = null)
    {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        }

        $request = \PlumePHP::request();
        if (isset($request->query[$name])) {
            return $request->query[$name];
        }

        if (isset($request->data[$name])) {
            return $request->data[$name];
        }

        if (isset($request->cookies[$name])) {
            return $request->cookies[$name];
        }

        return $default;
    }

    public function addJs(string $jsFile): void
    {
        $this->jsFiles[] = $jsFile;
    }

    public function addCss(string $cssFile): void
    {
        $this->cssFiles[] = $cssFile;
    }

    /**
     * Assign a variable to the view template.
     * @param string $name  Variable name
     * @param mixed  $value Variable value
     * @return self
     */
    public function assign($name, $value)
    {
        \PlumePHP::view()->set($name, $value);
        return $this;
    }

    /**
     * Render a view template with an optional layout.
     * @param string      $view   View file name (without extension)
     * @param string      $layout Layout file name; empty string disables layout
     * @param array       $data   Extra variables passed to the template
     * @param string|false $module Module name; defaults to the current module
     */
    /**
     * @param array<string, mixed> $data
     * @param string|false         $module
     */
    public function render(string $view, string $layout = 'layout', array $data = [], mixed $module = false): void
    {
        if ($module === false) {
            $module = \PlumePHP::get('plumephp.module');
        }

        $viewObj = \PlumePHP::view();
        if ($this->csrfValidate) {
            $csrfFormStr = sprintf('<input type="hidden" name="%s" value="%s" />', $this->csrfPostKey, $this->csrfToken);
            $viewObj->set('csrf_token', $this->getCsrfToken());
            $viewObj->set('csrf_field', $csrfFormStr);
        }
        header('Content-type: text/html; charset=utf-8');

        $viewObj->set('js_files', $this->jsFiles);
        $viewObj->set('css_files', $this->cssFiles);
        $viewObj->path = APP_PATH.DS.$module.DS.'views';
        $viewObj->render($view, $data, $layout);
    }

    /**
     * Entry point called by the dispatcher; handles CSRF, validation, and lifecycle hooks.
     */
    public function run(): mixed
    {
        // Avoid infinite loop, a listener instance can be called only one time
        if ($this->called) {
            return false;
        }

        $this->csrfToken = $this->getCookie($this->csrfTokenKey);
        if ($this->csrfValidate && C('PLUME_PHP_ENV') !== 'testing' && !$this->validateCsrfToken()) {
            header('HTTP/1.1 403 Forbidden');
            $this->error("Forbidden");
        }

        $this->createCsrfToken();
        if (!$this->beforeRun()) {
            return false;
        }

        // Declarative validation
        if (!empty($this->rules)) {
            $errors = $this->validate();
            if (!empty($errors)) {
                $firstMsg = reset($errors);
                $this->error((string) $firstMsg, 422, true);
            }
        }

        $executed = $this->execute();
        $result = $this->afterRun($executed);

        $this->called = true;
        return $result;
    }

    /**
     * Validate request parameters against $this->rules.
     *
     * @return array<string, string> field→error message map; empty means valid
     */
    public function validate(): array
    {
        $errors = [];
        foreach ($this->rules as $field => $ruleStr) {
            $value       = $this->getParam($field);
            $constraints = array_filter(array_map('trim', explode('|', $ruleStr)));

            foreach ($constraints as $constraint) {
                // Split on first colon only; regex rules may contain colons inside the pattern
                $colonPos = strpos($constraint, ':');
                if ($colonPos !== false) {
                    $rule = substr($constraint, 0, $colonPos);
                    $arg  = substr($constraint, $colonPos + 1);
                } else {
                    $rule = $constraint;
                    $arg  = null;
                }

                $empty = ($value === null || $value === '');

                switch ($rule) {
                    case 'required':
                        if ($empty) {
                            $errors[$field] = "{$field} is required";
                        }
                        break;

                    case 'int':
                        if (!$empty && !ctype_digit((string) $value) && !is_int($value)) {
                            $errors[$field] = "{$field} must be an integer";
                        }
                        break;

                    case 'float':
                        if (!$empty && !is_numeric($value)) {
                            $errors[$field] = "{$field} must be a number";
                        }
                        break;

                    case 'string':
                        if (!$empty && !is_string($value)) {
                            $errors[$field] = "{$field} must be a string";
                        }
                        break;

                    case 'email':
                        if (!$empty && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "{$field} must be a valid email address";
                        }
                        break;

                    case 'min':
                        if (!$empty && is_numeric($value) && (float) $value < (float) $arg) {
                            $errors[$field] = "{$field} must be at least {$arg}";
                        }
                        break;

                    case 'max':
                        if (!$empty && is_numeric($value) && (float) $value > (float) $arg) {
                            $errors[$field] = "{$field} must be no greater than {$arg}";
                        }
                        break;

                    case 'minLen':
                        if (!$empty && mb_strlen((string) $value) < (int) $arg) {
                            $errors[$field] = "{$field} must be at least {$arg} characters long";
                        }
                        break;

                    case 'maxLen':
                        if (!$empty && mb_strlen((string) $value) > (int) $arg) {
                            $errors[$field] = "{$field} must be no more than {$arg} characters long";
                        }
                        break;

                    case 'regex':
                        // arg is the pattern, e.g.  /^1[3-9]\d{9}$/
                        if (!$empty && !preg_match((string) $arg, (string) $value)) {
                            $errors[$field] = "{$field} format is invalid";
                        }
                        break;

                    case 'in':
                        // arg is comma-separated allowed values, e.g. active,inactive,pending
                        if (!$empty) {
                            $allowed = array_map('trim', explode(',', (string) $arg));
                            if (!in_array((string) $value, $allowed, true)) {
                                $errors[$field] = "{$field} must be one of: {$arg}";
                            }
                        }
                        break;

                    case 'confirmed':
                        // Verify value equals another field (default: {field}_confirm)
                        $otherField = $arg ?: ($field . '_confirm');
                        $other      = $this->getParam($otherField);
                        if ($value !== $other) {
                            $errors[$field] = "{$field} does not match {$otherField}";
                        }
                        break;

                    case 'array':
                        if (!$empty && !is_array($value)) {
                            $errors[$field] = "{$field} must be an array";
                        }
                        break;

                    case 'each':
                        // Apply a single sub-rule to every element of an array field
                        if (is_array($value) && $arg !== null) {
                            foreach ($value as $idx => $item) {
                                $subErrors = $this->applySimpleRule("{$field}[{$idx}]", $item, (string) $arg, null);
                                if ($subErrors) {
                                    $errors[$field] = $subErrors;
                                    break;
                                }
                            }
                        }
                        break;

                    case 'file':
                        // Checks the field exists in $_FILES and has no upload error
                        $fileInfo = $_FILES[$field] ?? null;
                        if ($fileInfo === null || ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                            $errors[$field] = "{$field} file upload failed or no file selected";
                        }
                        break;

                    case 'mimes':
                        // Comma-separated extensions, e.g. jpg,jpeg,png
                        $fileInfo = $_FILES[$field] ?? null;
                        if ($fileInfo && ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                            $ext      = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
                            $allowed  = array_map('trim', explode(',', strtolower((string) $arg)));
                            if (!in_array($ext, $allowed, true)) {
                                $errors[$field] = "{$field} only allows: {$arg} file types";
                            }
                        }
                        break;

                    case 'maxSize':
                        // Maximum file size in kilobytes
                        $fileInfo = $_FILES[$field] ?? null;
                        if ($fileInfo && ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                            $sizeKb = ($fileInfo['size'] ?? 0) / 1024;
                            if ($sizeKb > (float) $arg) {
                                $errors[$field] = "{$field} file size must not exceed {$arg} KB";
                            }
                        }
                        break;

                    default:
                        // Check registered custom rules
                        if (isset(self::$customRules[$rule])) {
                            if (!$empty && !(self::$customRules[$rule])($value)) {
                                $errors[$field] = "{$field} format is invalid";
                            }
                        }
                        break;
                }

                // Stop checking this field once an error is found
                if (isset($errors[$field])) {
                    break;
                }
            }
        }
        return $errors;
    }

    /**
     * Apply a single named rule to $value; used internally by the "each" rule.
     *
     * @return string Error message, or empty string if valid
     */
    private function applySimpleRule(string $label, mixed $value, string $rule, ?string $arg): string
    {
        $empty = ($value === null || $value === '');
        return match ($rule) {
            'required' => $empty ? "{$label} is required" : '',
            'int'      => (!$empty && !ctype_digit((string) $value) && !is_int($value))
                              ? "{$label} must be an integer" : '',
            'float'    => (!$empty && !is_numeric($value)) ? "{$label} must be a number" : '',
            'string'   => (!$empty && !is_string($value)) ? "{$label} must be a string" : '',
            'email'    => (!$empty && !filter_var($value, FILTER_VALIDATE_EMAIL))
                              ? "{$label} must be a valid email address" : '',
            default    => '',
        };
    }

    /**
     * Emit a JSON envelope response.
     * @param int    $code Response code (0 = success)
     * @param string $msg  Human-readable message
     * @param mixed  $data Response data payload
     */
    public function json(int $code, string $msg, mixed $data): void
    {
        $res = ['code'=>$code, 'msg'=>$msg, 'data'=>$data];
        \PlumePHP::json($res, 200, true, 'utf-8', JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<mixed> $ret
     */
    public function correct(array $ret = [], string $msg = 'success'): void
    {
        $this->json(0, $msg, $ret);
    }

    /**
     * Terminate the action with an error response.
     * @param string $msg  Error message
     * @param int    $code Error code (default 1)
     * @param bool   $json Force JSON output even for non-AJAX requests
     */
    public function error(string $msg = "Data error", int $code = 1, bool $json = false): never
    {
        if (!$json && !IS_AJAX) {
            $escapedMsg = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = <<<EOF
<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>An Error Occurred</title>
    </head>
    <body>
    <div class="container">
        <h1>An Error Occurred</h1>
        <p class="msg">{$escapedMsg}</p>
    </div>
    </body>
</html>
EOF;
            echo $html;
        } else {
            $this->json($code, $msg, '');
        }
        exit;
    }

    /**
     * @param string|null $key
     * @return mixed
     */
    public function getCookie(?string $key = null)
    {
        if ($key) {
            return isset($_COOKIE[$key]) ? $_COOKIE[$key] : null;
        } else {
            return $_COOKIE;
        }
    }

    /**
     * Set a cookie on the response.
     * @param string                            $key      Cookie name
     * @param string                            $value    Cookie value
     * @param int                               $expire   Lifetime in seconds (default 86400)
     * @param string                            $path     Cookie path
     * @param string                            $domain   Cookie domain
     * @param bool                              $httpOnly Prevent JavaScript access (default true)
     * @param bool                              $secure   Transmit over HTTPS only (default auto-detected)
     * @param 'Lax'|'lax'|'Strict'|'strict'|'None'|'none' $sameSite SameSite policy (default 'Lax')
     */
    public function setCookie(
        string $key,
        string $value,
        int $expire = 86400,
        string $path = '/',
        string $domain = '',
        bool $httpOnly = true,
        bool $secure = false,
        string $sameSite = 'Lax'
    ): void {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        /** @var array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'lax'|'Strict'|'strict'|'None'|'none'} $options */
        $options = [
            'expires'  => time() + $expire,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure || $isHttps,
            'httponly' => $httpOnly,
            'samesite' => in_array($sameSite, ['Lax', 'lax', 'Strict', 'strict', 'None', 'none'], true)
                ? $sameSite
                : 'Lax',
        ];
        setcookie($key, $value, $options);
    }

    /**
     * Generate (or refresh) the CSRF token cookie pair for the current request.
     * @return string|null The masked CSRF token
     */
    public function createCsrfToken()
    {
        if (!$this->csrfToken || !$this->getCookie($this->trueTokenKey)) {
            $trueToken = $this->generateCsrf();
            $this->csrfToken = $this->createCsrfCookie($trueToken);
            $trueKey = $this->trueTokenKey;
            $csrfKey = $this->csrfTokenKey;
            $payload = (string) json_encode([$trueKey, $trueToken]);
            $this->setCookie($trueKey, $this->hashData($payload, $this->getCsrfKey()));
            $this->setCookie($csrfKey, $this->csrfToken ?? '');
        }
        return $this->csrfToken;
    }

    private function hashData(string $data, string $key): string
    {
        $hash = hash_hmac('sha256', $data, $key);
        return $hash . $data;
    }

    private function getCsrfKey(): string
    {
        $secret = getenv('APP_SECRET');
        if (!$secret) {
            $env = defined('PLUME_PHP_ENV') ? constant('PLUME_PHP_ENV') : (C('PLUME_PHP_ENV') ?: '');
            if ($env !== 'testing') {
                throw new \RuntimeException(
                    'APP_SECRET environment variable must be set to enable CSRF protection. '
                    . 'Add APP_SECRET=<random-32-char-string> to your .env file.'
                );
            }
            return 'plumephp-csrf-testing-key';
        }
        if (strlen($secret) < 16) {
            throw new \RuntimeException('APP_SECRET must be at least 16 characters long.');
        }
        return $secret;
    }

    private function createCsrfCookie(string $token): string
    {
        $mask = random_bytes(8);
        return str_replace('+', '.', base64_encode($mask . $this->xorTokens($token, $mask)));
    }

    private function xorTokens(string $token1, string $token2): string
    {
        $n1 = mb_strlen($token1, '8bit');
        $n2 = mb_strlen($token2, '8bit');
        if ($n1 > $n2) {
            $token2 = str_pad($token2, $n1, $token2);
        } elseif ($n1 < $n2) {
            $token1 = str_pad($token1, $n2, $n1 === 0 ? ' ' : $token1);
        }
        return $token1 ^ $token2;
    }

    /**
     * Return the current CSRF token value.
     * @return string|null
     */
    public function getCsrfToken()
    {
        return $this->csrfToken;
    }

    /**
     * Generate a random hex string for use as a CSRF token.
     * @param int $len Token length in characters
     * @return string
     */
    private function generateCsrf(int $len = 32): string
    {
        return substr(bin2hex(random_bytes(max(1, $len))), 0, $len);
    }

    /**
     * Validate the CSRF token submitted with the current request.
     * @return bool True if valid (or if the request method does not require validation)
     */
    public function validateCsrfToken()
    {
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        } else {
            $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        }

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $trueToken = $this->trueTokenKey;
        $csrfPost = $this->csrfPostKey;
        $csrfHeader = 'HTTP_'.str_replace('-', '_', $this->csrfHeaderKey);
        if (empty($_COOKIE[$trueToken])) {
            return false;
        }

        $cookieValue = $_COOKIE[$trueToken];
        $hashLength = 64; // SHA-256 HMAC hex output is always 64 bytes
        $storedHash = mb_substr($cookieValue, 0, $hashLength, '8bit');
        $rawPayload = mb_substr($cookieValue, $hashLength, mb_strlen($cookieValue, '8bit'), '8bit');
        $expectedHash = hash_hmac('sha256', $rawPayload, $this->getCsrfKey());
        if (!hash_equals($storedHash, $expectedHash)) {
            return false;
        }
        $decoded = json_decode($rawPayload, true);
        $trueToken = isset($decoded[1]) ? $decoded[1] : '';
        if (empty($trueToken)) {
            return false;
        }
        $token = isset($_POST[$csrfPost]) ? $_POST[$csrfPost] : (isset($_SERVER[$csrfHeader]) ? $_SERVER[$csrfHeader] : null);
        $token = base64_decode(str_replace('.', '+', $token));
        $n = mb_strlen($token, '8bit');
        if ($n <= 8) {
            return false;
        }
        $mask = mb_substr($token, 0, 8, '8bit');
        $token = mb_substr($token, 8, $n-8, '8bit');

        $n1 = mb_strlen($mask, '8bit');
        $n2 = mb_strlen($token, '8bit');
        if ($n1 > $n2) {
            $token = str_pad($token, $n1, $token);
        } elseif ($n1 < $n2) {
            $mask = str_pad($mask, $n2, $n1 === 0 ? ' ' : $mask);
        }
        $token = $mask ^ $token;
        return hash_equals($trueToken, $token);
    }

    protected function beforeRun(): bool
    {
        return true;
    }

    protected function afterRun(mixed $result): mixed
    {
        return $result;
    }

    /**
     * Execute the action
     *
     * @abstract
     * @access public
     * @return mixed
     */
    abstract public function execute();
}
