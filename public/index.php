<?php

if (PHP_SAPI == 'cli-server' && is_file(__DIR__.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

if (!isset($_SERVER['HTTP_MOD_REWRITE'])) {
    $_SERVER['HTTP_MOD_REWRITE'] = 'Off';
}

// 加载框架文件
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

// api首页展示
PlumePHP::route('GET /api', function() {
    header('Content-Type: text/html;charset=utf-8');
    json_output('success', 0, 'api', true);
});

// 通用的路由逻辑
PlumePHP::route('*', function() {
    PlumePHP::app()->run();
});

// 启动
PlumePHP::start();