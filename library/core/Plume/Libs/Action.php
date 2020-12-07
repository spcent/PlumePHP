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
        if ($request->query[$name]) {
            return $request->query[$name];
        }

        if ($request->data[$name]) {
            return $request->data[$name];
        }

        if ($request->cookies[$name]) {
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

        $executed = $this->execute();
        $result = $this->afterRun($executed);

        $this->called = true;
        return $result;
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
            $this->setCookie($trueKey, $this->hashData(serialize([$trueKey, $trueToken]), 'plumephp'));
            $this->setCookie($csrfKey, $this->csrfToken);
        }
        return $this->csrfToken;
    }

    private function hashData($data, $key)
    {
        $hash = hash_hmac('sha256', $data, $key);
        return $hash . $data;
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
    private function generateCsrf($len = 32)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < $len; $i++) {
            $code .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $code;
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

        $trueToken = $_COOKIE[$trueToken];
        $test = hash_hmac('sha256', '', '', false);
        $hashLength = mb_strlen($test, '8bit');
        $trueToken = unserialize(mb_substr($trueToken, $hashLength, mb_strlen($trueToken, '8bit'), '8bit'))[1];
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
