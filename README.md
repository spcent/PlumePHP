# PlumePHP — 羽量级的单文件 PHP 开发框架

> PHP >= 8.1 · PSR-3 日志 · PSR-11 容器 · PSR-15 中间件 · Apache-2.0

---

## 快速启动

```bash
# 内置 Web Server（开发/测试用，单进程）
php -S localhost:8000 -t public/ public/index.php

# 指定环境变量
PLUME_PHP_ENV=testing php -S localhost:8000 -t public/ public/index.php

# 通过框架 CLI 启动（内置端口占用检测）
php public/index.php -S
php public/index.php -S -H 0.0.0.0 -P 9000   # 自定义地址端口
php public/index.php -S -b                     # 后台运行

# 运行 CLI 命令
php public/index.php -m web -c migrate
```

> PHP 内置 Server 是单进程的，一次只处理一个请求，仅用于开发测试，不适合生产环境。

---

## 简介

PlumePHP 是一个单入口、极简 PHP 框架，适合快速构建中小型系统。提供路由、模板、日志、数据库、CSRF、中间件等开箱即用的能力，同时支持 FrankenPHP Worker 常驻内存模式。

---

## 入口文件

推荐使用静态门面写法——无需捕获 `$app` 变量即可在闭包中使用，且能正确触发 before/after 过滤器链：

```php
// public/index.php
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

PlumePHP::route('GET /api', function () {
    echo json_encode(['code' => 0, 'data' => 'api', 'msg' => 'success'], JSON_UNESCAPED_UNICODE);
});

PlumePHP::route('GET /', function () {
    echo 'Hello World!';
});

// 通用路由：按 /{module}/{controller}/{action} 解析文件
PlumePHP::route('*', function () {
    PlumePHP::app()->runAction();
});

PlumePHP::start();
```

也可以使用 `$app` 实例风格（与上面等价，差异见下文）：

```php
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

$app = PlumePHP::app();

$app->route('GET /api', function () {
    echo json_encode(['code' => 0, 'data' => 'api', 'msg' => 'success'], JSON_UNESCAPED_UNICODE);
});

$app->route('*', function () use ($app) {
    $app->runAction();
});

$app->start();
```

**两种写法的区别：**`PlumePHP::method()` 通过 `__callStatic` 转发，会检查并触发 `before/after` 过滤器；`$app->method()` 直接调用，绕过事件系统。如未使用事件过滤器，两者行为完全一致。

---

## 路由

```php
// 方法限定
PlumePHP::route('GET /search', $callback);
PlumePHP::route('GET|POST /form', $callback);

// 命名参数
PlumePHP::route('/user/@id', function ($id) { ... });

// 参数 + 正则约束
PlumePHP::route('/post/@slug:[a-z-]+', function ($slug) { ... });

// 可选段
PlumePHP::route('/blog(/@year(/@month))', function ($y, $m) { ... });

// 通配符（splat）
PlumePHP::route('/files/*', function ($splat) { ... });

// 捕获路由对象（第三个参数为 true）
PlumePHP::route('/info', $callback, true);   // 回调最后一个参数为 PlumeRoute

// 路由组（共享前缀 + 可选中间件）
PlumePHP::group('/api', function () {
    PlumePHP::route('/users', $callback);
    PlumePHP::route('/orders', $callback);
}, [$middleware]);

// 捕获所有（catch-all）
PlumePHP::route('*', $callback);
```

- 按声明顺序匹配，第一个匹配的路由生效
- 回调返回 `true` 则继续匹配下一条路由
- 默认大小写不敏感；`PlumePHP::router()->case_sensitive = true` 开启敏感模式

### 路由正则缓存

路由较多时可开启编译缓存，避免每次请求重复解析正则：

```php
PlumePHP::app()->enableRouteCache('/path/to/storage/route_cache.php');
// 或
PlumePHP::router()->enableCache('/path/to/storage/route_cache.php');
```

---

## 中间件（PSR-15）

```php
class AuthMiddleware implements PlumeMiddlewareInterface
{
    public function process(PlumeRequest $request, PlumeRequestHandlerInterface $handler): PlumeResponse
    {
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return PlumePHP::response()->status(401)->write('Unauthorized');
        }
        return $handler->handle($request);
    }
}

PlumePHP::app()->addMiddleware(new AuthMiddleware());
PlumePHP::start();
```

---

## 请求与响应

```php
$req = PlumePHP::request();
$req->query['id'];          // $_GET
$req->data['name'];         // $_POST
$req->method;               // GET / POST / PUT …
$req->url;                  // 当前 URL
$req->headers['User-Agent'];

$res = PlumePHP::response();
$res->status(201)
    ->header('X-Custom', 'value')
    ->write('body')
    ->send();

// 快捷 JSON 响应
PlumePHP::json(['code' => 0, 'data' => $data]);
PlumePHP::jsonp(['code' => 0], 'callback');
```

---

## 模板渲染

```php
// 渲染 views/home.tpl.php，使用 views/layout.tpl.php 包裹
PlumePHP::render('home', ['user' => $user], null, 'layout');

// 在 Action 内部
$this->assign('user', $user);
$this->render('home', 'layout');   // 第二参数为布局文件名
$this->render('home', '');         // 不使用布局
```

模板文件位于 `views/`，扩展名默认 `.tpl.php`，变量通过 `extract()` 展开后直接可用。

---

## 日志

```php
L('message');                          // DEBUG，写入当日 .log
L('warn', [], 'WARN', true);           // WARN，同时写入 .log.wf
L('query', [], 'SQL');                 // SQL，写入 .log.sql

// PSR-3 接口
PlumePHP::logger()->info('started');
PlumePHP::logger()->error('failed', ['code' => 500]);
```

**日志文件规则：**

| 文件 | 级别 |
|---|---|
| `storage/log/YYYYMMDD.log` | DEBUG / INFO / NOTICE（同时写入 wf） |
| `storage/log/YYYYMMDD.log.wf` | WARN / ERROR / FATAL / CRITICAL，以及 NOTICE |
| `storage/log/YYYYMMDD.log.sql` | SQL |

**输出格式（`setFormatter`）：**

```php
// text（默认）: [2025-01-01 12:00:00][logId][LEVEL]message
// json: {"time":"...","log_id":"...","level":"...","msg":"...","ctx":{...}}
PlumePHP::logger()->setFormatter('json');
```

**写入模式（`setMode`）：**

```php
// normal（默认）：每条日志立即落盘
// batch：缓冲至请求结束统一落盘，减少 Worker 模式下的磁盘 I/O
PlumePHP::logger()->setMode('batch');
```

**自定义 Handler（告警推送等）：**

```php
// 钉钉告警（ERROR 及以上级别触发）
PlumePHP::logger()->addHandler(
    PlumeLogHandlers::dingtalk('https://oapi.dingtalk.com/robot/send?access_token=xxx')
);

// Sentry
PlumePHP::logger()->addHandler(
    PlumeLogHandlers::sentry('https://key@sentry.io/project')
);

// 最低级别过滤器（包装任意 handler）
PlumePHP::logger()->addHandler(
    PlumeLogHandlers::minLevel('WARNING', PlumeLogHandlers::dingtalk($webhook))
);
```

---

## 配置

```php
// config/config.php 返回数组；PLUME_PHP_ENV 可选环境覆盖文件 config/{env}.php

C('USE_SESSION')            // 读取顶层 key
C('DB_CONF.master.db_port') // 点号最多三层
C(['key1', 'key2'])         // 批量读取，返回关联数组
C(['KEY' => 'val'])         // 批量写入

C('MY_KEY', 'value');       // 单 key 写入

// 引擎变量（运行时）
PlumePHP::set('plumephp.base_url', 'https://example.com');
PlumePHP::get('plumephp.base_url');
PlumePHP::has('plumephp.base_url');
PlumePHP::clear('plumephp.base_url');
```

**常用配置项：**

| Key | 默认值 | 说明 |
|---|---|---|
| `USE_SESSION` | `true` | 自动启动 Session |
| `TIME_ZONE` | `'Asia/Shanghai'` | 默认时区 |
| `VDNAME` | `''` | 虚拟目录前缀 |
| `DB_CONF` | `[...]` | 数据库连接配置 |
| `plumephp.handle_errors` | `true` | 将 PHP 错误转为异常 |
| `plumephp.case_sensitive` | `false` | 路由大小写敏感 |
| `plumephp.base_url` | 自动 | 资源/链接基础 URL |
| `plumephp.views.path` | `'./views'` | 模板目录 |
| `plumephp.views.extension` | `'.tpl.php'` | 模板扩展名 |

---

## 扩展机制

```php
// 自定义方法
PlumePHP::map('hello', function (string $name) {
    echo "Hello, {$name}!";
});
PlumePHP::hello('World');

// 注册懒加载服务
PlumePHP::register('db', 'MyDB', [$dsn], function ($db) {
    $db->connect();
});
PlumePHP::db();        // 共享实例
PlumePHP::db(false);   // 每次返回新实例

// Before / After 过滤器（before 返回 false 中断链）
PlumePHP::before('start', function (&$params, &$output) { ... });
PlumePHP::after('start',  function (&$params, &$output) { ... });
```

---

## PSR-11 容器

```php
$container = PlumePHP::app()->container();

// 绑定接口→实现
$container->bind(LoggerInterface::class, MyLogger::class);

// 绑定工厂
$container->bindFactory('cache', function ($c) {
    return new RedisCache($c->get('config'));
});

$logger = $container->get(LoggerInterface::class);
```

---

## 数据库

使用内置 **Medoo**（`library/core/Plume/Libs/Medoo.php`）：

```php
$db = DB();               // 默认连接（DB_CONF 第一条）
$db = DB('master');       // 指定连接 key
$db = DB(['db_server' => '127.0.0.1', 'db_name' => 'test', ...]);

$rows  = $db->select('users', ['id', 'name'], ['age[>]' => 18]);
$id    = $db->insert('users', ['name' => 'John', 'age' => 30]);
$db->update('users', ['age' => 31], ['id' => 1]);
$db->delete('users', ['id' => 1]);
$rows  = $db->query('SELECT * FROM users')->fetchAll(\PDO::FETCH_ASSOC);
```

DB 配置字段：`db_server` / `db_port` / `db_user` / `db_password` / `db_name` / `db_charset` / `db_prefix`

---

## Module / Action 系统

URL `/{module}/{controller}/{action}` 自动映射到文件系统：

```
application/
  {module}/
    {module}.boot.php          # 模块引导，定义基础 Action 类
    actions/
      {controller}.action.php  # class {module}_{controller}_action
    console/
      {cmd}.cmd.php            # class {module}_{cmd}_cmd（CLI）
    views/
      {template}.tpl.php
      layout.tpl.php
```

**Action 示例：**

```php
// application/web/actions/home.action.php
class web_home_action extends web_base_action
{
    // protected $csrfValidate = false;  // 关闭该 Action 的 CSRF 验证

    public function invoke()
    {
        $id = $this->getParam('id', 0);

        $this->assign('user', $userData);
        $this->render('home', 'layout');    // 渲染模板

        // 或返回 JSON
        $this->correct(['key' => 'value']); // {code:0, msg:'', data:{...}}
        $this->error('Not found', 404);     // {code:404, msg:'Not found'}
    }

    public function beforeRun()  { /* invoke() 前执行 */ }
    public function afterRun($r) { /* invoke() 后执行 */ }
}
```

**Action 常用方法：**

| 方法 | 说明 |
|---|---|
| `getParam($name, $default)` | 获取 GET/POST/Cookie 参数 |
| `setParam($name, $value)` | 设置请求参数 |
| `assign($name, $value)` | 向模板传递变量 |
| `render($view, $layout, $data)` | 渲染模板（自动注入 CSRF Token） |
| `json($code, $msg, $data)` | 输出 `{code, msg, data}` JSON |
| `correct($data, $msg)` | 等同 `json(0, $msg, $data)` |
| `error($msg, $code, $asJson)` | 输出错误页或 JSON |
| `getCsrfToken()` | 获取当前 CSRF Token |
| `validateCsrfToken()` | 校验 Token（POST/PUT/PATCH 自动调用） |
| `addJs($file)` / `addCss($file)` | 注册资源文件 |

**CSRF 说明：**
- Token 存储在 `plume-csrf-token` Cookie，验证对应 `plume-csrf` HMAC Cookie
- 提交方式：`$_POST['plume_csrf']` 或 `X-CSRF-TOKEN` 请求头
- 模板自动注入 `$csrf_token`（Token 值）和 `$csrf_field`（隐藏域 HTML）

---

## CLI 命令

```php
// application/web/console/migrate.cmd.php
class web_migrate_cmd
{
    public function run(array $opts): void
    {
        // $opts = 解析后的 argv
    }
}
```

```bash
php public/index.php -m web -c migrate
php public/index.php --module web --cmd migrate --dry
```

---

## Schema 与 JSON 映射

`PlumeSchema` 是 JSON 可序列化的数据模型基类，属性名自动转为 snake_case：

```php
class UserSchema extends PlumeSchema
{
    public int $userId = 0;
    public string $userName = '';
}

// 从请求参数创建
$param  = PlumePHP::app()->request()->param();  // PlumeParam
$user   = UserSchema::createFromPlumeParam($param);

// 序列化为 JSON：{"user_id":1,"user_name":"John"}
echo json_encode($user);
```

`PlumeJsonMapper` 独立使用时可将 JSON 数组映射到任意 PHP 对象：

```php
$mapper = new PlumeJsonMapper();
$obj    = $mapper->map($jsonArray, new MyModel());
```

---

## FrankenPHP Worker 模式

Worker 模式下 PHP 常驻内存，每次请求复用进程，消除冷启动开销。入口为 `public/worker.php`：

```php
// 路由每次请求重新注册（resetForWorker 会清空路由）
while (frankenphp_handle_request(function () {
    PlumePHP::resetForWorker();   // 重置路由、请求/响应、Session，保留 boot 配置

    PlumePHP::route('GET /api', fn() => ...);
    PlumePHP::route('*', fn() => PlumePHP::app()->runAction());

    PlumePHP::start();
}));
```

```bash
# 开发（传统模式）
frankenphp php-server --root public/

# Worker 模式
frankenphp php-server --worker public/worker.php --root public/
```

> Worker 模式下，不要在静态变量或模块级单例中存储请求相关状态，它们在请求间持久存在。

---

## 生产部署（Caddy）

PlumePHP 支持两种 Caddy 部署模式，按需选择。

### 方案一：FrankenPHP + Worker 模式（推荐）

FrankenPHP 将 PHP 嵌入 Caddy，结合 `worker.php` 常驻进程，PHP 只启动一次，请求复用同一进程，无冷启动开销，性能最优。

**Caddyfile：**

```caddyfile
{
    frankenphp
    order php_server before file_server
}

yourdomain.com {
    root * /var/www/PlumePHP/public

    tls your@email.com

    header {
        X-Frame-Options DENY
        X-Content-Type-Options nosniff
        X-XSS-Protection "1; mode=block"
        Referrer-Policy strict-origin-when-cross-origin
        -Server
    }

    # 禁止直接访问框架内部目录
    @blocked {
        path /library/* /application/* /config/* /storage/* /vendor/* /tests/*
    }
    respond @blocked 403

    # 静态资源直接服务，不经 PHP
    @static {
        path *.css *.js *.png *.jpg *.jpeg *.gif *.ico *.svg *.woff *.woff2 *.ttf *.eot
    }
    file_server @static

    # PHP Worker 常驻进程模式
    php_server {
        worker worker.php
    }
}
```

**启动命令：**

```bash
# 开发（无 worker，每请求启动一次 PHP）
frankenphp php-server --root public/

# 生产（worker 常驻进程）
frankenphp php-server --worker public/worker.php --root public/

# 通过 Caddyfile 启动
frankenphp run --config /etc/caddy/Caddyfile
```

### 方案二：Caddy + PHP-FPM

适合已有 PHP-FPM 环境，或不想引入 FrankenPHP 的场景。

**Caddyfile：**

```caddyfile
yourdomain.com {
    root * /var/www/PlumePHP/public

    tls your@email.com

    header {
        X-Frame-Options DENY
        X-Content-Type-Options nosniff
        -Server
    }

    # 禁止直接访问框架内部目录
    @blocked {
        path /library/* /application/* /config/* /storage/* /vendor/* /tests/*
    }
    respond @blocked 403

    # 静态资源直接服务，不经 PHP
    @static {
        path *.css *.js *.png *.jpg *.jpeg *.gif *.ico *.svg *.woff *.woff2 *.ttf *.eot
    }
    file_server @static

    # 所有其他请求经 index.php 调度
    php_fastcgi unix//run/php/php8.2-fpm.sock {
        index index.php
        env HTTP_MOD_REWRITE On
    }

    file_server
}
```

### 注意事项

**日志目录权限：**

```bash
mkdir -p storage/log
chown -R www-data:www-data storage/
chmod -R 755 storage/
```

**虚拟目录（子路径部署）：** 若部署在子路径（如 `/app`），需在 `config/config.php` 设置 `'VDNAME' => 'app'`，并在 Caddyfile 中对应路由块内添加 `uri strip_prefix /app`。

**环境变量注入：** 数据库等配置推荐通过 `.env` 文件传入（`PlumeDotEnv` 内置支持）；也可在 PHP-FPM 模式下通过 `php_fastcgi` 的 `env` 指令注入。

**两种方案对比：**

| | FrankenPHP + worker.php | Caddy + PHP-FPM |
|---|---|---|
| PHP 启动开销 | 一次（进程常驻） | 每请求（取决于 FPM 配置） |
| 部署复杂度 | 低（单二进制） | 中（两个服务） |
| 适用场景 | 高并发、低延迟 | 稳健通用 |

---

## 全局辅助函数

| 函数 | 说明 |
|---|---|
| `C($key, $val)` | 配置读/写（点号最多三层） |
| `I($path, $once)` | 条件包含文件 |
| `L($msg, $ctx, $level, $wf)` | 写日志 |
| `T($e, $offset)` | 格式化异常堆栈字符串 |
| `E($prefix, $e)` | 记录带堆栈的错误日志 |
| `DB($opts)` | 获取 Medoo 实例 |
| `json_output($msg, $code, $data)` | 输出 `{code,msg,data}` JSON 并退出 |
| `redirect($url, $time, $msg)` | HTTP 重定向或 meta-refresh |
| `html_filter($html)` | 过滤 script/iframe/onclick/style |
| `strcut($str, $len, $ext, $zh_len)` | UTF-8 截断（含后缀） |
| `generate_nonce_str($len)` | 随机字母数字字符串 |
| `uuid($prefix)` | MD5 唯一 ID |
| `authcode($str, $op, $key, $exp)` | Discuz 风格加解密 |
| `signature($data, $key)` | MD5 参数签名 |
| `curl_get_contents($url, $post, ...)` | cURL HTTP 请求 |
| `get_client_ip($type)` | 获取真实客户端 IP |
| `is_weixin_browser()` | 微信浏览器检测 |
| `money_yuan_to_fen($price)` | 元（浮点）→ 分（整数） |
| `money_fen_to_yuan($price)` | 分（整数）→ 元（浮点） |
| `export_csv($filename, $data)` | 生成并下载 CSV |
| `dump($var, ...)` | 格式化打印变量 |
| `dump_with_exit(...)` | 格式化打印后退出 |
| `human_date($ts, $fmt)` | 相对时间（"2 小时前"） |
| `str_starts_with()` / `str_ends_with()` | PHP 8.0 polyfill |

---

## 开发命令

```bash
# 安装依赖
composer install

# 运行全部测试
./vendor/bin/phpunit tests/

# 运行单个测试文件
./vendor/bin/phpunit tests/RouterTest.php

# 静态分析
./vendor/bin/phpstan analyse

# 构建单文件发布产物（dist/Plume.php）
composer build
```

---

## 目录结构

```
library/
  PlumePHP.php          # 引导入口 + 全局函数 + 静态门面
  PlumeHelper.php       # 静态工具类
  common.php            # 全局函数别名 → PlumeHelper
  Plume/
    Engine/             # 核心类（Router、Event、Loader、Container…）
    Http/               # HTTP 层（Request、Response、Router、Route）
    Support/            # 服务与工具（Logger、View、Param、Schema…）
  core/Plume/Libs/      # 内置第三方库（Medoo、Curl、Action…）
dist/
  Plume.php             # 单文件发布产物（composer build 生成）
public/
  index.php             # Web 入口
  worker.php            # FrankenPHP Worker 入口
application/            # 业务代码（模块/Action/视图/命令）
config/                 # 配置文件
storage/log/            # 日志目录
```
