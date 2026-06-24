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
     *       'field'  => 'required',
     *       'age'    => 'required|int|min:18|max:120',
     *       'email'  => 'required|email',
     *       'name'   => 'required|string|minLen:2|maxLen:64',
     *       'status' => 'int',
     *   ];
     *
     * Supported constraints: required, int, float, string, email,
     *   min:<n>, max:<n>, minLen:<n>, maxLen:<n>
     *
     * Override validate() for custom logic; return an array of field→message
     * pairs to signal failure, or an empty array on success.
     *
     * @var array<string, string>
     */
    protected array $rules = [];

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
            $viewObj->set('csrfToken', $this->getCsrfToken());
            $viewObj->set('csrfPost', $this->csrfPostKey);
            $viewObj->set('_csrf_form_', $csrfFormStr);
        }

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
        header('Content-type: text/html; charset=utf-8');
        if ($this->csrfValidate && !$this->validateCsrfToken()) {
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
                [$rule, $arg] = array_pad(explode(':', $constraint, 2), 2, null);

                switch ($rule) {
                    case 'required':
                        if ($value === null || $value === '') {
                            $errors[$field] = "{$field} 不能为空";
                        }
                        break;

                    case 'int':
                        if ($value !== null && $value !== '' && !ctype_digit((string) $value) && !is_int($value)) {
                            $errors[$field] = "{$field} 必须是整数";
                        }
                        break;

                    case 'float':
                        if ($value !== null && $value !== '' && !is_numeric($value)) {
                            $errors[$field] = "{$field} 必须是数字";
                        }
                        break;

                    case 'email':
                        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "{$field} 邮箱格式不正确";
                        }
                        break;

                    case 'min':
                        if ($value !== null && $value !== '' && is_numeric($value) && (float) $value < (float) $arg) {
                            $errors[$field] = "{$field} 不能小于 {$arg}";
                        }
                        break;

                    case 'max':
                        if ($value !== null && $value !== '' && is_numeric($value) && (float) $value > (float) $arg) {
                            $errors[$field] = "{$field} 不能大于 {$arg}";
                        }
                        break;

                    case 'minLen':
                        if ($value !== null && mb_strlen((string) $value) < (int) $arg) {
                            $errors[$field] = "{$field} 长度不能少于 {$arg} 个字符";
                        }
                        break;

                    case 'maxLen':
                        if ($value !== null && mb_strlen((string) $value) > (int) $arg) {
                            $errors[$field] = "{$field} 长度不能超过 {$arg} 个字符";
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
        <p class="msg">{$msg}</p>
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
     * @param null $domain
     */
    public function setCookie($key, $value, $expire = 86400, $path = '/', $domain = null)
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
        return $secret ?: 'plumephp-csrf-fallback-key';
    }

    /**
     * @param $token
     * @return string
     */
    private function createCsrfCookie($token)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-.';
        $mask = substr(str_shuffle(str_repeat($chars, 5)), 0, 8);
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
        $hashLength = mb_strlen(hash_hmac('sha256', '', ''), '8bit');
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
