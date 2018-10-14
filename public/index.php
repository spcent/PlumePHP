<?php

if (PHP_SAPI == 'cli-server' && is_file(__DIR__.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

if (!isset($_SERVER['HTTP_MOD_REWRITE'])) {
    $_SERVER['HTTP_MOD_REWRITE'] = 'Off';
}

// 加载框架文件
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

// add core path
PlumePHP::path(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'core');

// api首页展示
PlumePHP::route('GET /api', function() {
    header('Content-Type: text/html;charset=utf-8');
    json_output('success', 0, 'api', true);
});

// api请求入口
PlumePHP::route('POST /api', function() {
    // 设置请求头
    $env = PlumePHP::app()->get('plumephp.env');
    if ($env != 'production') {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        header("Access-Control-Allow-Methods:POST, GET, OPTIONS");
    }

    $rpcServer = new \Plume\Libs\JsonRpcServer();
    $rpcServer->dispatch();
});

// api调试入口
PlumePHP::route('GET /bizcall', function() {
    header('Content-Type: text/html;charset=utf-8');
    try {
        // 用户先登录获取token
        $vo = new PlumeViewObject();
        $user_func_data = PlumePHP::biz($vo->path, $vo);
        if (is_null($user_func_data)) $user_func_data = 0;
        json_output('success', 0, $user_func_data, true);
    } catch(Exception $e) {
        $code = $e->getCode();
        if (!$code) $code = 1;
        $GPC = array_merge($_GET, $_POST, $_COOKIE);
        json_output($e->getMessage(), $code, $GPC, true);
    }
});

// 通用的路由逻辑
PlumePHP::route('*', function() {
    PlumePHP::app()->run();
});

// 启动
PlumePHP::start();