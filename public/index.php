<?php

if (PHP_SAPI == 'cli-server' && is_file(__DIR__.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

if (!isset($_SERVER['HTTP_MOD_REWRITE'])) {
    $_SERVER['HTTP_MOD_REWRITE'] = 'Off';
}

// 加载框架文件
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

$app = PlumePHP::app();

// api首页展示
$app->route('GET /api', function() {
    header('Content-Type: text/html;charset=utf-8');
    json_output('success', 0, 'api', true);
    return false;
});

// 通用的路由逻辑
$app->route('*', function() use ($app) {
    $app->run();
    return false;
});

// 启动
$app->start();