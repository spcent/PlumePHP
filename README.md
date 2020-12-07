PlumePHP - 羽量级的单文件php开发框架

### 简介

PlumePHP是一个单入口，单文件PHP框架，适用于简单系统的快速开发，提供了简单的路由方式，抛弃了坑爹的PHP模板，采用原生PHP语法来渲染页面。如果您正在开发一个简单的功能，而又不想使用Yii，CodeIgniter，ThinkPHP等框架，则可以试用一下该框架。

区别于其他Web框架，该框架是一个绝对的单文件框架（整个框架只有一个index.php文件，与PHP入口文件index.php相同），不需要额外的引用或配置。

使用方法也及其简单，只需下载框架文件index.php到项目根目录，然后通过浏览器输入项目访问地址，即可自动生成框架目录结构（项目目录需要有写入权限）。

### 特点

- 加载简单，引入只需一个文件
- 日志机制
- 支持HTTP（网页模式）和cli（脚本模式）两种模式
- 事件驱动
- 数据库操作简便
- 助手类引入便捷
- 文件处理封装
- 时间处理封装
- Socket请求封装，支持代理模式
- Session支持cookie、file、db多种方式
- 支持委托代理机制
- 安全过滤器机制
- 其他

## 入口文件：index.php

```php
// 加载框架文件
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

$app = PlumePHP::app();

// api首页展示
$app->route('GET /api', function() {
    echo json_encode(['code'=>0, 'data'=>'api', 'msg'=>'success'], JSON_UNESCAPED_UNICODE);
});

$app->route('GET /', function () {
    echo 'Hello World!';
    return false;
});

// 通用的路由逻辑
$app->route('*', function() use ($app) {
   $app->runAction();
});

// 启动
$app->start();
```

or 

```php
// 加载框架文件
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

// api首页展示
PlumePHP::route('GET /api', function() {
    echo json_encode(['code'=>0, 'data'=>'api', 'msg'=>'success'], JSON_UNESCAPED_UNICODE);
});

// 通用的路由逻辑
PlumePHP::route('*', function() {
    PlumePHP::app()->runAction();
});

// 启动
PlumePHP::start();
```
