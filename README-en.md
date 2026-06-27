# PlumePHP — A Lightweight Single-File PHP Framework

> PHP >= 8.1 · PSR-3 Logging · PSR-11 Container · PSR-15 Middleware · Apache-2.0

---

## Quick Start

```bash
# Built-in Web Server (development/testing, single-process)
php -S localhost:8000 -t public/ public/index.php

# With environment variable
PLUME_PHP_ENV=testing php -S localhost:8000 -t public/ public/index.php

# Via framework CLI (with built-in port-conflict detection)
php public/index.php -S
php public/index.php -S -H 0.0.0.0 -P 9000   # custom host and port
php public/index.php -S -b                     # run in background

# Run a CLI command
php public/index.php -m web -c migrate
```

> PHP's built-in server is single-process and handles one request at a time. Use it for development and testing only — not suitable for production.

---

## Introduction

PlumePHP is a single-entry, minimal PHP framework designed for rapid development of small-to-medium systems. It ships with routing, templating, logging, database access, CSRF protection, and middleware out of the box, and supports FrankenPHP Worker persistent-process mode.

---

## Entry Point

The recommended style uses the static facade — no need to capture `$app` in closures, and before/after filter chains fire correctly:

```php
// public/index.php
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

PlumePHP::route('GET /api', function () {
    echo json_encode(['code' => 0, 'data' => 'api', 'msg' => 'success'], JSON_UNESCAPED_UNICODE);
});

PlumePHP::route('GET /', function () {
    echo 'Hello World!';
});

// Catch-all: resolves /{module}/{controller}/{action} from the filesystem
PlumePHP::route('*', function () {
    PlumePHP::app()->runAction();
});

PlumePHP::start();
```

The `$app` instance style is equivalent and also supported:

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

**The difference:** `PlumePHP::method()` goes through `__callStatic` and checks for registered `before/after` filters before dispatching. `$app->method()` calls the engine directly, bypassing the event system. When no filters are registered, both styles behave identically.

---

## Routing

```php
// Method constraints
PlumePHP::route('GET /search', $callback);
PlumePHP::route('GET|POST /form', $callback);

// Named parameters
PlumePHP::route('/user/@id', function ($id) { ... });

// Named parameter with regex constraint
PlumePHP::route('/post/@slug:[a-z-]+', function ($slug) { ... });

// Optional segments
PlumePHP::route('/blog(/@year(/@month))', function ($y, $m) { ... });

// Wildcard (splat)
PlumePHP::route('/files/*', function ($splat) { ... });

// Pass the matched route object (third argument true)
PlumePHP::route('/info', $callback, true);   // last callback arg is PlumeRoute

// Route groups (shared prefix + optional middleware)
PlumePHP::group('/api', function () {
    PlumePHP::route('/users', $callback);
    PlumePHP::route('/orders', $callback);
}, [$middleware]);

// Catch-all
PlumePHP::route('*', $callback);
```

- Routes are matched in declaration order; the first match wins.
- Returning `true` from a handler continues matching to the next route.
- Matching is case-insensitive by default; set `PlumePHP::router()->case_sensitive = true` to enable case-sensitive mode.

### Route Regex Cache

For applications with many routes, enable compiled regex caching to avoid re-parsing on every request:

```php
PlumePHP::app()->enableRouteCache('/path/to/storage/route_cache.php');
// or
PlumePHP::router()->enableCache('/path/to/storage/route_cache.php');
```

---

## Middleware (PSR-15)

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

## Request & Response

```php
$req = PlumePHP::request();
$req->query['id'];           // $_GET
$req->data['name'];          // $_POST
$req->method;                // GET / POST / PUT …
$req->url;                   // current URL
$req->headers['User-Agent'];

$res = PlumePHP::response();
$res->status(201)
    ->header('X-Custom', 'value')
    ->write('body')
    ->send();

// Shorthand JSON responses
PlumePHP::json(['code' => 0, 'data' => $data]);
PlumePHP::jsonp(['code' => 0], 'callback');
```

---

## Template Rendering

```php
// Render views/home.tpl.php wrapped in views/layout.tpl.php
PlumePHP::render('home', ['user' => $user], null, 'layout');

// Inside an Action
$this->assign('user', $user);
$this->render('home', 'layout');   // second arg is the layout file name
$this->render('home', '');         // no layout
```

Templates live in `views/` with the `.tpl.php` extension by default. Variables are made available via `extract()`.

---

## Logging

```php
L('message');                          // DEBUG — written to daily .log
L('warn', [], 'WARN', true);           // WARN — also written to .log.wf
L('query', [], 'SQL');                 // SQL — written to .log.sql

// PSR-3 interface
PlumePHP::logger()->info('started');
PlumePHP::logger()->error('failed', ['code' => 500]);
```

**Log file rules:**

| File | Levels |
|---|---|
| `storage/log/YYYYMMDD.log` | DEBUG / INFO / NOTICE (NOTICE also written to wf) |
| `storage/log/YYYYMMDD.log.wf` | WARN / ERROR / FATAL / CRITICAL, and NOTICE |
| `storage/log/YYYYMMDD.log.sql` | SQL |

**Output format (`setFormatter`):**

```php
// text (default): [2025-01-01 12:00:00][logId][LEVEL]message
// json: {"time":"...","log_id":"...","level":"...","msg":"...","ctx":{...}}
PlumePHP::logger()->setFormatter('json');
```

**Write mode (`setMode`):**

```php
// normal (default): each entry is flushed to disk immediately
// batch: buffer all entries and flush at request end — reduces disk I/O in Worker mode
PlumePHP::logger()->setMode('batch');
```

**Custom handlers (alerting, etc.):**

```php
// DingTalk alert (triggered on ERROR and above)
PlumePHP::logger()->addHandler(
    PlumeLogHandlers::dingtalk('https://oapi.dingtalk.com/robot/send?access_token=xxx')
);

// Sentry
PlumePHP::logger()->addHandler(
    PlumeLogHandlers::sentry('https://key@sentry.io/project')
);

// Minimum-level filter (wraps any handler)
PlumePHP::logger()->addHandler(
    PlumeLogHandlers::minLevel('WARNING', PlumeLogHandlers::dingtalk($webhook))
);
```

---

## Configuration

```php
// config/config.php returns a plain array.
// PLUME_PHP_ENV selects an optional override file: config/{env}.php

C('USE_SESSION')             // read a top-level key
C('DB_CONF.master.db_port')  // dot notation, up to three levels
C(['key1', 'key2'])          // bulk read — returns associative array
C(['KEY' => 'val'])          // bulk write

C('MY_KEY', 'value');        // single-key write

// Engine variables (runtime)
PlumePHP::set('plumephp.base_url', 'https://example.com');
PlumePHP::get('plumephp.base_url');
PlumePHP::has('plumephp.base_url');
PlumePHP::clear('plumephp.base_url');
```

**Common configuration options:**

| Key | Default | Description |
|---|---|---|
| `USE_SESSION` | `true` | Auto-start session |
| `TIME_ZONE` | `'Asia/Shanghai'` | Default timezone |
| `VDNAME` | `''` | Virtual directory prefix |
| `DB_CONF` | `[...]` | Database connection config |
| `plumephp.handle_errors` | `true` | Convert PHP errors to exceptions |
| `plumephp.case_sensitive` | `false` | Case-sensitive route matching |
| `plumephp.base_url` | auto | Base URL for assets/links |
| `plumephp.views.path` | `'./views'` | Template directory |
| `plumephp.views.extension` | `'.tpl.php'` | Template file extension |

---

## Extension Mechanisms

```php
// Add a custom method
PlumePHP::map('hello', function (string $name) {
    echo "Hello, {$name}!";
});
PlumePHP::hello('World');

// Register a lazy-loaded service
PlumePHP::register('db', 'MyDB', [$dsn], function ($db) {
    $db->connect();
});
PlumePHP::db();        // shared instance
PlumePHP::db(false);   // new instance on every call

// Before / After filters (returning false from before breaks the chain)
PlumePHP::before('start', function (&$params, &$output) { ... });
PlumePHP::after('start',  function (&$params, &$output) { ... });
```

---

## PSR-11 Container

```php
$container = PlumePHP::app()->container();

// Bind an interface to a concrete implementation
$container->bind(LoggerInterface::class, MyLogger::class);

// Bind a factory closure
$container->bindFactory('cache', function ($c) {
    return new RedisCache($c->get('config'));
});

$logger = $container->get(LoggerInterface::class);
```

---

## Database

Uses the bundled **Medoo** library (`library/core/Plume/Libs/Medoo.php`):

```php
$db = DB();               // default connection (first entry in DB_CONF)
$db = DB('master');       // connection by key
$db = DB(['db_server' => '127.0.0.1', 'db_name' => 'test', ...]);

$rows  = $db->select('users', ['id', 'name'], ['age[>]' => 18]);
$id    = $db->insert('users', ['name' => 'John', 'age' => 30]);
$db->update('users', ['age' => 31], ['id' => 1]);
$db->delete('users', ['id' => 1]);
$rows  = $db->query('SELECT * FROM users')->fetchAll(\PDO::FETCH_ASSOC);
```

DB config keys: `db_server` / `db_port` / `db_user` / `db_password` / `db_name` / `db_charset` / `db_prefix`

---

## Module / Action System

The URL `/{module}/{controller}/{action}` is automatically mapped to the filesystem:

```
application/
  {module}/
    {module}.boot.php          # Module bootstrap; defines the base Action class
    actions/
      {controller}.action.php  # class {module}_{controller}_action
    console/
      {cmd}.cmd.php            # class {module}_{cmd}_cmd (CLI only)
    views/
      {template}.tpl.php
      layout.tpl.php
```

**Action example:**

```php
// application/web/actions/home.action.php
class web_home_action extends web_base_action
{
    // protected $csrfValidate = false;  // disable CSRF for this action

    public function invoke()
    {
        $id = $this->getParam('id', 0);

        $this->assign('user', $userData);
        $this->render('home', 'layout');    // render template

        // or return JSON
        $this->correct(['key' => 'value']); // {code:0, msg:'', data:{...}}
        $this->error('Not found', 404);     // {code:404, msg:'Not found'}
    }

    public function beforeRun()  { /* runs before invoke() */ }
    public function afterRun($r) { /* runs after invoke()  */ }
}
```

**Action helper methods:**

| Method | Purpose |
|---|---|
| `getParam($name, $default)` | Fetch GET / POST / Cookie parameter |
| `setParam($name, $value)` | Set a request parameter |
| `assign($name, $value)` | Pass a variable to the template |
| `render($view, $layout, $data)` | Render template (auto-injects CSRF token) |
| `json($code, $msg, $data)` | Emit `{code, msg, data}` JSON |
| `correct($data, $msg)` | Shorthand for `json(0, $msg, $data)` |
| `error($msg, $code, $asJson)` | Error page or JSON error response |
| `getCsrfToken()` | Get the current CSRF token |
| `validateCsrfToken()` | Validate token (auto-called on POST/PUT/PATCH) |
| `addJs($file)` / `addCss($file)` | Register asset files for the layout |

**CSRF notes:**
- Token stored in the `plume-csrf-token` cookie; validated against the `plume-csrf` HMAC cookie.
- Submit via `$_POST['plume_csrf']` or the `X-CSRF-TOKEN` request header.
- Templates receive `$csrf_token` (token value) and `$csrf_field` (hidden input HTML) automatically.

---

## CLI Commands

```php
// application/web/console/migrate.cmd.php
class web_migrate_cmd
{
    public function run(array $opts): void
    {
        // $opts = parsed argv
    }
}
```

```bash
php public/index.php -m web -c migrate
php public/index.php --module web --cmd migrate --dry
```

---

## Schema & JSON Mapping

`PlumeSchema` is a JSON-serializable data model base class. Property names are automatically converted to `snake_case` during serialization:

```php
class UserSchema extends PlumeSchema
{
    public int $userId = 0;
    public string $userName = '';
}

// Create from request parameters
$param = PlumePHP::app()->request()->param();  // PlumeParam
$user  = UserSchema::createFromPlumeParam($param);

// Serializes to JSON: {"user_id":1,"user_name":"John"}
echo json_encode($user);
```

`PlumeJsonMapper` can be used standalone to map a JSON array onto any PHP object:

```php
$mapper = new PlumeJsonMapper();
$obj    = $mapper->map($jsonArray, new MyModel());
```

---

## FrankenPHP Worker Mode

In Worker mode PHP stays alive between requests, eliminating cold-start overhead. The entry point is `public/worker.php`:

```php
// Routes must be re-registered each iteration because resetForWorker() clears the router.
while (frankenphp_handle_request(function () {
    PlumePHP::resetForWorker();   // clears router, request/response, session; preserves boot config

    PlumePHP::route('GET /api', fn() => ...);
    PlumePHP::route('*', fn() => PlumePHP::app()->runAction());

    PlumePHP::start();
}));
```

```bash
# Development (traditional mode, no worker overhead)
frankenphp php-server --root public/

# Worker mode (persistent processes)
frankenphp php-server --worker public/worker.php --root public/
```

> Do not store mutable, request-specific state in static variables or module-level singletons — they persist across requests in Worker mode.

---

## Global Helper Functions

| Function | Purpose |
|---|---|
| `C($key, $val)` | Config get/set (dot notation, up to 3 levels) |
| `I($path, $once)` | Conditional file include |
| `L($msg, $ctx, $level, $wf)` | Write a log entry |
| `T($e, $offset)` | Format exception stack trace as string |
| `E($prefix, $e)` | Log an error with stack trace |
| `DB($opts)` | Get a Medoo instance |
| `json_output($msg, $code, $data)` | Emit `{code,msg,data}` JSON and exit |
| `redirect($url, $time, $msg)` | HTTP redirect or meta-refresh |
| `html_filter($html)` | Strip script/iframe/onclick/style tags |
| `strcut($str, $len, $ext, $zh_len)` | UTF-8 string truncation with suffix |
| `generate_nonce_str($len)` | Random alphanumeric string |
| `uuid($prefix)` | MD5-based unique ID |
| `authcode($str, $op, $key, $exp)` | Discuz-style encrypt/decrypt |
| `signature($data, $key)` | MD5 parameter signature |
| `curl_get_contents($url, $post, ...)` | HTTP request via cURL |
| `get_client_ip($type)` | Get the real client IP address |
| `is_weixin_browser()` | Detect WeChat browser |
| `money_yuan_to_fen($price)` | Float yuan → integer cents |
| `money_fen_to_yuan($price)` | Integer cents → float yuan |
| `export_csv($filename, $data)` | Generate and download a CSV file |
| `dump($var, ...)` | Pretty-print variables |
| `dump_with_exit(...)` | Pretty-print then exit |
| `human_date($ts, $fmt)` | Relative time string ("2 hours ago") |
| `str_starts_with()` / `str_ends_with()` | PHP 8.0 polyfills |

---

## Development Commands

```bash
# Install dependencies
composer install

# Run all tests
./vendor/bin/phpunit tests/

# Run a single test file
./vendor/bin/phpunit tests/RouterTest.php

# Static analysis
./vendor/bin/phpstan analyse

# Build single-file distribution artifact (dist/Plume.php)
composer build
```

---

## Directory Structure

```
library/
  PlumePHP.php          # Bootstrap entry point + global functions + static facade
  PlumeHelper.php       # Static utility class
  common.php            # Global function aliases → PlumeHelper
  Plume/
    Engine/             # Core classes (Router, Event, Loader, Container…)
    Http/               # HTTP layer (Request, Response, Router, Route)
    Support/            # Services & utilities (Logger, View, Param, Schema…)
  core/Plume/Libs/      # Bundled third-party libraries (Medoo, Curl, Action…)
dist/
  Plume.php             # Single-file distribution artifact (generated by composer build)
public/
  index.php             # Web entry point
  worker.php            # FrankenPHP Worker entry point
application/            # Application code (modules / Actions / views / commands)
config/                 # Configuration files
storage/log/            # Log directory
```
