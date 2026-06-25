<?php

namespace Plume\Libs;

/**
 * 抽象Action动作逻辑
 */
abstract class Action
{
    private $csrfToken = null;
    private $csrfTokenKey = 'plume-csrf-token';
    private $trueTokenKey = 'plume-csrf';
    private $csrfHeaderKey = 'X-CSRF-TOKEN';
    private $csrfPostKey = 'plume_csrf';

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
     * @var array
     */
    private $params = [];
    
    /**
     * csrf验证
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

    protected $jsFiles = [];
    protected $cssFiles = [];

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

    public function addJs($jsFile)
    {
        $this->jsFiles[] = $jsFile;
    }

    public function addCss($cssFile)
    {
        $this->cssFiles[] = $cssFile;
    }

    /**
     * 模板变量赋值
     * @param string $name 名称
     * @param string $value 值
     * @return self
     */
    public function assign($name, $value)
    {
        \PlumePHP::view()->set($name, $value);
        return $this;
    }

    /**
     * 渲染模板
     * @param string $view 模板文件
     * @param string $layout 布局文件
     * @param array $data 模板数据
     * @param string $module 模块名称，默认为false
     */
    public function render($view, $layout = 'layout', $data = [], $module = false)
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
     * run方法
     */
    public function run()
    {
        // Avoid infinite loop, a listener instance can be called only one time
        if ($this->called) {
            return false;
        }

        $this->csrfToken = $this->getCookie($this->csrfTokenKey);
        if ($this->csrfValidate && C('PLUME_PHP_ENV') !== 'testing' && !$this->validateCsrfToken()) {
            header('HTTP/1.1 401 Unauthorized');
            $this->error("Unauthorized");
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
                return false;
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
                            $errors[$field] = "{$field} 不能为空";
                        }
                        break;

                    case 'int':
                        if (!$empty && !ctype_digit((string) $value) && !is_int($value)) {
                            $errors[$field] = "{$field} 必须是整数";
                        }
                        break;

                    case 'float':
                        if (!$empty && !is_numeric($value)) {
                            $errors[$field] = "{$field} 必须是数字";
                        }
                        break;

                    case 'string':
                        if (!$empty && !is_string($value)) {
                            $errors[$field] = "{$field} 必须是字符串";
                        }
                        break;

                    case 'email':
                        if (!$empty && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "{$field} 邮箱格式不正确";
                        }
                        break;

                    case 'min':
                        if (!$empty && is_numeric($value) && (float) $value < (float) $arg) {
                            $errors[$field] = "{$field} 不能小于 {$arg}";
                        }
                        break;

                    case 'max':
                        if (!$empty && is_numeric($value) && (float) $value > (float) $arg) {
                            $errors[$field] = "{$field} 不能大于 {$arg}";
                        }
                        break;

                    case 'minLen':
                        if (!$empty && mb_strlen((string) $value) < (int) $arg) {
                            $errors[$field] = "{$field} 长度不能少于 {$arg} 个字符";
                        }
                        break;

                    case 'maxLen':
                        if (!$empty && mb_strlen((string) $value) > (int) $arg) {
                            $errors[$field] = "{$field} 长度不能超过 {$arg} 个字符";
                        }
                        break;

                    case 'regex':
                        // arg is the pattern, e.g.  /^1[3-9]\d{9}$/
                        if (!$empty && !preg_match((string) $arg, (string) $value)) {
                            $errors[$field] = "{$field} 格式不正确";
                        }
                        break;

                    case 'in':
                        // arg is comma-separated allowed values, e.g. active,inactive,pending
                        if (!$empty) {
                            $allowed = array_map('trim', explode(',', (string) $arg));
                            if (!in_array((string) $value, $allowed, true)) {
                                $errors[$field] = "{$field} 的值必须是以下之一: {$arg}";
                            }
                        }
                        break;

                    case 'confirmed':
                        // Verify value equals another field (default: {field}_confirm)
                        $otherField = $arg ?: ($field . '_confirm');
                        $other      = $this->getParam($otherField);
                        if ($value !== $other) {
                            $errors[$field] = "{$field} 与 {$otherField} 不一致";
                        }
                        break;

                    case 'array':
                        if (!$empty && !is_array($value)) {
                            $errors[$field] = "{$field} 必须是数组";
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
                            $errors[$field] = "{$field} 文件上传失败或未选择文件";
                        }
                        break;

                    case 'mimes':
                        // Comma-separated extensions, e.g. jpg,jpeg,png
                        $fileInfo = $_FILES[$field] ?? null;
                        if ($fileInfo && ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                            $ext      = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
                            $allowed  = array_map('trim', explode(',', strtolower((string) $arg)));
                            if (!in_array($ext, $allowed, true)) {
                                $errors[$field] = "{$field} 只允许上传: {$arg} 格式的文件";
                            }
                        }
                        break;

                    case 'maxSize':
                        // Maximum file size in kilobytes
                        $fileInfo = $_FILES[$field] ?? null;
                        if ($fileInfo && ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                            $sizeKb = ($fileInfo['size'] ?? 0) / 1024;
                            if ($sizeKb > (float) $arg) {
                                $errors[$field] = "{$field} 文件大小不能超过 {$arg}KB";
                            }
                        }
                        break;

                    default:
                        // Check registered custom rules
                        if (isset(self::$customRules[$rule])) {
                            if (!$empty && !(self::$customRules[$rule])($value)) {
                                $errors[$field] = "{$field} 格式不正确";
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
            'required' => $empty ? "{$label} 不能为空" : '',
            'int'      => (!$empty && !ctype_digit((string) $value) && !is_int($value))
                              ? "{$label} 必须是整数" : '',
            'float'    => (!$empty && !is_numeric($value)) ? "{$label} 必须是数字" : '',
            'string'   => (!$empty && !is_string($value)) ? "{$label} 必须是字符串" : '',
            'email'    => (!$empty && !filter_var($value, FILTER_VALIDATE_EMAIL))
                              ? "{$label} 邮箱格式不正确" : '',
            default    => '',
        };
    }

    /**
     * display to json
     * @param int $code 编码
     * @param string $msg 消息
     * @param mixed 数据
     */
    public function json($code, $msg, $data)
    {
        $res = ['code'=>$code, 'msg'=>$msg, 'data'=>$data];
        \PlumePHP::json($res, 200, true, 'utf-8', JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array $ret
     * @param string $msg
     */
    public function correct($ret = [], $msg = 'success')
    {
        $this->json(0, $msg, $ret);
    }

    /**
     * @param string $msg
     * @param bool $json 是否强制显示json
     */
    public function error($msg = "数据异常", $code = 1, $json = false)
    {
        if (!$json && !IS_AJAX) {
            $escapedMsg = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = <<<EOF
<!DOCTYPE html>
<html lang="zh-CN">
    <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>出错啦</title>
    </head>
    <body>
    <div class="container">
        <h1>出错啦！</h1>
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
     * @param null $key
     * @return mixed
     */
    public function getCookie($key = null)
    {
        if ($key) {
            return isset($_COOKIE[$key]) ? $_COOKIE[$key] : null;
        } else {
            return $_COOKIE;
        }
    }

    /**
     * 设置cookie
     * @param $key
     * @param $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     */
    public function setCookie($key, $value, $expire = 86400, $path = '/', $domain = '')
    {
        setcookie($key, $value, time() + $expire, $path, $domain);
    }

    /**
     * 获取对应csrfToken
     * @return null|string
     */
    public function createCsrfToken()
    {
        if (!$this->csrfToken || !$this->getCookie($this->trueTokenKey)) {
            $trueToken = $this->generateCsrf();
            $this->csrfToken = $this->createCsrfCookie($trueToken);
            $trueKey = $this->trueTokenKey;
            $csrfKey = $this->csrfTokenKey;
            $payload = json_encode([$trueKey, $trueToken]);
            $this->setCookie($trueKey, $this->hashData($payload, $this->getCsrfKey()));
            $this->setCookie($csrfKey, $this->csrfToken);
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

    /**
     * @param $token
     * @return string
     */
    private function createCsrfCookie($token)
    {
        $mask = random_bytes(8);
        return str_replace('+', '.', base64_encode($mask . $this->xorTokens($token, $mask)));
    }

    private function xorTokens($token1, $token2)
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
     * 获取csrf
     * @return null
     */
    public function getCsrfToken()
    {
        return $this->csrfToken;
    }

    /**
     * 获取随机字符串
     * @param int $len
     * @return string
     */
    private function generateCsrf(int $len = 32): string
    {
        return substr(bin2hex(random_bytes($len)), 0, $len);
    }

    /**
     * 验证csrfToken
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
        return $token === $trueToken;
    }

    protected function beforeRun()
    {
        return true;
    }
    protected function afterRun($result)
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
