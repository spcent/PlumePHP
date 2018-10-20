PlumePHP - 羽量级的单文件php开发框架

# What is PlumePHP

PlumePHP is a fast, simple, extensible, single file framework for PHP.
PlumePHP enables you to quickly and easily build RESTful web applications.
========

### 简介

PlumePHP是一个单文件PHP框架，适用于简单系统的快速开发，提供了简单的路由方式，抛弃了坑爹的PHP模板，采用原生PHP语法来渲染页面。
参考了[leo108](http://leo108.com)的SinglePHP，代码地址[Github](https://github.com/leo108/SinglePHP)和flight框架等。

## 入口文件：index.php

```php
// 加载框架文件
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

// api首页展示
PlumePHP::route('GET /api', function() {
    header('Content-Type: text/html;charset=utf-8');
    echo json_encode(['code'=>0, 'data'=>'api', 'msg'=>'success'], JSON_UNESCAPED_UNICODE);
});

// 通用的路由逻辑
PlumePHP::route('*', function() {
    PlumePHP::app()->run();
});

// 启动
PlumePHP::start();
```

## 控制台cmd入口文件：plume
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (PHP_SAPI != 'cli') {
    echo 'This script run only from the command line'.PHP_EOL;
    exit(255);
}

if (PHP_SAPI == 'cli-server' && is_file(__DIR__.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

// 加载框架文件
include __DIR__ . DIRECTORY_SEPARATOR . 'PlumePHP.php';

// 启动应用程序
try {
    PlumePHP::app()->run();
} catch (Exception $e) {
    // 全局异常处理
    echo $e->getMessage(), PHP_EOL;
    exit(255);
}
```
