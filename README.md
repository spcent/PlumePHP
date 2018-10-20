PlumePHP - 羽量级的单文件php开发框架

# What is PlumePHP

PlumePHP is a fast, simple, extensible, single file framework for PHP.
PlumePHP enables you to quickly and easily build RESTful web applications.
========

### 简介

PlumePHP是一个单文件PHP框架，适用于简单系统的快速开发，提供了简单的路由方式，抛弃了坑爹的PHP模板，采用原生PHP语法来渲染页面。
参考了[leo108](http://leo108.com)的SinglePHP，代码地址[Github](https://github.com/leo108/SinglePHP)框架等。

### 目录结构

    ├── application                         # 业务代码文件夹，可在配置中指定路径
    │   ├── web                             # web模块
    |   |   ├──actions                      # web控制器
    |   |   ├──views                        # 视图
    |   |   └──console                      # cmd控制器
    │   │   └── web.boot.php                # web启动文件
    │   └── admin                           # admin模块
    |       ├──actions                      # web控制器
    |       ├──views                        # 视图
    |       └──console                      # cmd控制器
    │       └── admin.boot.php              # admin启动文件
    ├── config
    │   └── config.php                      # 全局配置文件（可选）
    ├── tests                               # 单元测试（可选）
    ├── storage                             # 运行生成文件存储目录（可选）
    │   └── log                             # 日志目录（可选）
    ├── public
    │   └── index.php                       # 入口文件（必须）
    ├── PlumePHP.php                        # PlumePHP核心文件（必须）
    ├── common.php                          # 一些共用函数（可选）
    └── plume                               # cmd命令行启动脚本（可选）

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
