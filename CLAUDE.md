# CLAUDE.md

## Commands

```bash
# Run all tests
./vendor/bin/phpunit tests/

# Run a single test file
./vendor/bin/phpunit tests/RouterTest.php

# Start dev server
php -S localhost:8000 -t public/

# Run CLI command
php public/index.php -m {module} -c {cmd} [options]

# Install dependencies
composer install

# Build single-file dist artifact
composer build
```

> PHPUnit `^10.5` is in `require-dev`. Tests extend `PHPUnit\Framework\TestCase`. Run static analysis with `./vendor/bin/phpstan analyse`.

---

## Architecture

### Source layout

`library/PlumePHP.php` (~346 lines) is the bootstrap entry point: it defines global functions (`C`, `I`, `L`, `T`, `E`) and `require_once`s all framework class files in dependency order. Framework classes live in `library/Plume/` (PSR-4 namespace `Plume\`).

```
library/
  PlumePHP.php          Bootstrap facade + global functions
  PlumeHelper.php       Static utility class
  common.php            Global function aliases → PlumeHelper
  Plume/
    Engine/             Core classes
    Http/               HTTP layer
    Support/            Services & utilities
  core/Plume/Libs/      Bundled third-party libraries
```

A single-file distribution is generated at `dist/Plume.php` via `composer build`.

### Class map

```
PlumePHP                  Static facade; all public API calls forwarded via __callStatic
  └─ PlumeEngine          Singleton app instance: DI container + request dispatcher
       ├─ PlumeRouter         URL pattern matching (declaration order, first match wins)
       ├─ PlumeRoute          Individual route representation
       ├─ PlumeRequest        HTTP request wrapper (query, POST, headers, cookies, files)
       ├─ PlumeResponse       HTTP response builder (headers, status, JSON, redirect, ETag)
       ├─ PlumeView           Native-PHP template rendering with layouts
       ├─ PlumeEvent          Before/after filter chains on any method
       ├─ PlumeLoader         Class autoloader + lazy service registry
       ├─ PlumeCollection     ArrayAccess/Iterator/Countable superglobal wrapper
       ├─ PlumeParam          GET+POST+JSON merged param container with auto-sanitize
       ├─ PlumeLogger         PSR-3 LoggerInterface, file-based logging by level/type
       ├─ PlumeLogHandlers    Structured log handler system
       ├─ PlumeSchema         Base class for JSON-serializable data models
       ├─ PlumeJsonMapper     JSON-to-typed-object mapper with namespace resolution
       ├─ PlumeCmdService     CLI argv parser + command runner
       ├─ PlumeDotEnv         .env file parser
       ├─ PlumeDocGenerator   API documentation generator
       ├─ PlumeHelper         Static utility methods (JSON, cURL, redirects)
       ├─ PlumeContainer      PSR-11 DI container
       ├─ PlumeMiddlewarePipeline  Middleware execution pipeline
       ├─ ActionNaming        URL path → class name converter
       ├─ ActionLocator       Finds action files on disk
       ├─ ActionResolver      Resolves actions from routes
       ├─ ActionInvoker       Executes action methods
       └─ ActionException     Custom exception for action errors
```

`PlumePHP::app()` returns the `PlumeEngine` singleton.

### Defined constants (set at boot)

| Constant | Value |
|---|---|
| `PLUME_VERSION` | `'1.3.1'` |
| `DS` | `DIRECTORY_SEPARATOR` |
| `PLUME_PHP_PATH` | Path to `library/` |
| `VENDOR_PATH` | Path to `library/vendor/` |
| `APP_PATH` | Path to `application/` |
| `CONFIG_PATH` | Path to `config/` |
| `PUBLIC_PATH` | Path to `public/` |
| `LOG_PATH` | Path to log directory |
| `IS_CLI` | `1` if running from CLI |
| `SITE_DOMAIN` | Current origin (web only) |
| `IS_GET` / `IS_POST` / `IS_AJAX` | Request type booleans (web only) |

---

## Extension Mechanisms

```php
// Add/override a method on the engine
PlumePHP::map('methodName', function(...$args) { ... });

// Register a lazy-instantiated service
PlumePHP::register('db', 'MyDB', [$param1], function($db) { /* post-init */ });
PlumePHP::db();        // shared instance
PlumePHP::db(false);   // new instance every call

// Before/after filters (returning false from before breaks chain)
PlumePHP::before('start', function(&$params, &$output) { ... });
PlumePHP::after('start',  function(&$params, &$output) { ... });
```

---

## Request Lifecycle

1. `public/index.php` includes `library/PlumePHP.php`, calls `PlumePHP::app()`
2. Routes are registered; wildcard `*` route calls `$app->runAction()`
3. `PlumePHP::start()` dispatches through the router (output is buffered)
4. `runAction()` resolves `/{module}/{controller}/{action}`:
   - Loads `application/{module}/{module}.boot.php`
   - Loads `application/{module}/actions/{controller}/{action}.action.php`  
     _or_ `application/{module}/actions/{controller}.action.php` (flat layout)
   - Instantiates `{module}_{controller}_action`, calls `execute()` → `run()` → `invoke()`
5. `PlumePHP::stop($code)` flushes buffered response

### Worker mode (FrankenPHP / RoadRunner)

`public/worker.php` is the persistent worker entry point. Call `PlumePHP::resetForWorker()` between requests to reset per-request state (router, loader instances, dispatcher filters, engine vars) while preserving boot-time config and timezone.

```php
while (frankenphp_handle_request(function () {
    PlumePHP::resetForWorker();
    PlumePHP::route('*', fn() => PlumePHP::app()->runAction());
    PlumePHP::start();
}));
```

---

## Module / Action Layout

```
application/
  {module}/
    {module}.boot.php              # Required: bootstraps module, defines base action class
    actions/
      {controller}.action.php      # Class: {module}_{controller}_action
    console/
      {cmd}.cmd.php                # Class: {module}_{cmd}_cmd  (CLI only)
    views/
      {template}.tpl.php           # Templates; variables available via extract()
      layout.tpl.php               # Default layout (wrap $__content__)
```

### Action class structure

```php
// application/web/actions/home.action.php
class web_home_action extends web_base_action {
    // protected $csrfValidate = false;  // disable CSRF for this action

    public function invoke() {
        $id  = $this->getParam('id', 0);      // GET/POST/cookie
        $raw = $this->request->query['q'];     // direct access

        // Render view
        $this->assign('user', $userData);
        $this->render('home', 'layout');       // '' = no layout

        // Or JSON response
        $this->correct(['key' => 'value']);    // {code:0, msg:'', data:{...}}
        $this->error('Not found', 404);        // {code:404, msg:'Not found'}
    }

    public function beforeRun()  { /* runs before invoke() */ }
    public function afterRun($r) { /* runs after invoke()  */ }
}
```

### Action helper methods (`library/core/Plume/Libs/Action.php`)

| Method | Purpose |
|---|---|
| `getParam($name, $default)` | Fetch from GET/POST/cookie |
| `setParam($name, $value)` | Set a request param |
| `assign($name, $value)` | Assign variable to view |
| `render($view, $layout, $data)` | Render template (auto-adds CSRF token) |
| `json($code, $msg, $data)` | Emit `{code, msg, data}` JSON envelope |
| `correct($data, $msg)` | Shorthand: `json(0, $msg, $data)` |
| `error($msg, $code, $asJson)` | Error page or JSON |
| `getCookie($key)` | Read cookie |
| `setCookie($key, $val, $exp, $path, $domain)` | Write cookie |
| `getCsrfToken()` | Get current CSRF token |
| `validateCsrfToken()` | Validate token (auto-called on POST/PUT/PATCH) |
| `addJs($file)` / `addCss($file)` | Register assets for layout |

### CLI command class structure

```php
// application/web/console/install.cmd.php
class web_install_cmd {
    public function run($opts) { /* $opts = parsed argv */ }
}
```

```bash
php public/index.php -m web -c install
php public/index.php --module web --cmd install --dry
```

---

## Routing

```php
PlumePHP::route('/path', $callback);
PlumePHP::route('GET /search', $callback);
PlumePHP::route('GET|POST /form', $callback);
PlumePHP::route('/user/@id', function($id) { ... });           // named param
PlumePHP::route('/post/@slug:[a-z-]+', function($slug) { });   // with regex
PlumePHP::route('/blog(/@year(/@month))', function($y, $m) { }); // optional
PlumePHP::route('/files/*', function($splat) { });             // wildcard
PlumePHP::route('*', function() { });                           // catch-all

// Route groups with shared prefix and optional middleware
PlumePHP::group('/api', function() {
    PlumePHP::route('/users', $callback);
}, [$middleware]);
```

- First match wins; returning `true` from a handler continues to next match
- Route object passed as last arg when third param is `true`
- Matching is case-insensitive by default (`$router->case_sensitive = true` to change)

---

## Configuration

`config/config.php` returns a plain array. Environment-specific overrides go in `config/{env}.php` (selected via `PLUME_PHP_ENV`). `.env` files are supported via `PlumeDotEnv`.

```php
// Get
C('USE_SESSION')                // top-level key
C('DB_CONF.master.db_port')    // dot notation, max 3 levels
C(['key1', 'key2'])             // multiple keys at once

// Set
C('MY_KEY', 'value');

// Direct
$cfg = PlumePHP::get('plumephp.base_url');
PlumePHP::set('plumephp.base_url', 'https://example.com');
```

**Key config options:**

| Key | Default | Purpose |
|---|---|---|
| `USE_SESSION` | `true` | Auto-start session |
| `TIME_ZONE` | `'Asia/Shanghai'` | Default timezone |
| `VDNAME` | `''` | Virtual directory prefix |
| `DB_CONF` | `[...]` | Database connections |
| `plumephp.handle_errors` | `true` | Convert PHP errors to exceptions |
| `plumephp.base_url` | auto | Base URL for assets/links |

---

## Logging

Logs written to `storage/log/` (or `LOG_PATH`). `PlumeLogger` implements PSR-3 `LoggerInterface`.

| File | Levels |
|---|---|
| `YYYYMMDD.log` | DEBUG, INFO, NOTICE |
| `YYYYMMDD.log.wf` | WARN, ERROR, FATAL (and NOTICE) |
| `YYYYMMDD.log.sql` | SQL queries |

```php
L('message', $context, 'INFO');         // helper function
L('query', [], 'SQL', false);           // SQL log

PlumePHP::log('message', [], 'ERROR', true);   // wf file
```

Log format: `[YYYY-MM-DD HH:MM:SS][{log_id}][LEVEL]message\tcontext_json`

---

## Helper Functions (`library/common.php` → `PlumeHelper`)

Global functions in `common.php` delegate to `PlumeHelper` static methods, enabling both procedural and OOP styles.

| Function | Purpose |
|---|---|
| `C($key, $val)` | Config get/set (dot notation, 3 levels) |
| `I($path, $once)` | Conditional include |
| `L($msg, $ctx, $level, $wf)` | Write log |
| `T($e, $offset)` | Format exception trace string |
| `E($prefix, $e)` | Log error with trace |
| `DB($opts)` | Get Medoo instance (by key or options array) |
| `json_output($msg, $code, $data)` | Emit `{code,msg,data}` JSON and exit |
| `redirect($url, $time, $msg)` | HTTP redirect or meta-refresh |
| `html_filter($html)` | Strip script/iframe/onclick/style tags |
| `strcut($str, $len, $ext, $zh_len)` | UTF-8 truncate with suffix |
| `generate_nonce_str($len)` | Random alphanumeric string |
| `uuid($prefix)` | MD5-based unique ID |
| `authcode($str, $op, $key, $exp)` | Discuz-style encrypt/decrypt |
| `signature($data, $key)` | MD5 param signature |
| `curl_get_contents($url, $post, ...)` | HTTP via cURL |
| `get_client_ip($type)` | Real client IP |
| `is_weixin_browser()` | WeChat browser detection |
| `money_yuan_to_fen($price)` | Float → integer cents |
| `money_fen_to_yuan($price)` | Integer cents → float |
| `export_csv($filename, $data)` | Generate & download CSV |
| `dump($var, ...)` | Pretty-print variables |
| `dump_with_exit(...)` | Pretty-print then exit |
| `human_date($ts, $fmt)` | Relative date ("2 hours ago") |
| `str_starts_with()` / `str_ends_with()` | PHP 8.0 polyfills |

---

## Database Access

Uses bundled **Medoo** (`library/core/Plume/Libs/Medoo.php`).

```php
$db = DB();             // default connection (first in DB_CONF)
$db = DB('master');     // by config key
$db = DB(['db_server' => '127.0.0.1', 'db_name' => 'test', ...]);  // override

$rows  = $db->select('users', ['id', 'name'], ['age[>]' => 18]);
$id    = $db->insert('users', ['name' => 'John', 'age' => 30]);
$count = $db->update('users', ['age' => 31], ['id' => 1]);
$db->delete('users', ['id' => 1]);
$rows  = $db->query('SELECT * FROM users')->fetchAll(\PDO::FETCH_ASSOC);
```

DB config keys: `db_server`, `db_port`, `db_user`, `db_password`, `db_name`, `db_charset`, `db_prefix`

---

## CSRF Protection

Enabled by default on all POST/PUT/PATCH requests.

- Token stored in `plume-csrf-token` cookie; validated against `plume-csrf` HMAC cookie
- Submit via `$_POST['plume_csrf']` or `X-CSRF-TOKEN` header
- Disable per-action: `protected $csrfValidate = false;`
- Templates get `$csrf_token` and `$csrf_field` (hidden input HTML) auto-injected

---

## Bundled Libraries

| File | Purpose |
|---|---|
| `library/core/Plume/Libs/Medoo.php` | Database abstraction (MySQL-focused) |
| `library/core/Plume/Libs/Curl.php` | HTTP client wrapper |
| `library/core/Plume/Libs/JsonRpcServer.php` | JSON-RPC endpoint handler |
| `library/core/Plume/Libs/AliPhoneMsg.php` | Aliyun SMS integration |
| `library/core/Plume/Libs/Action.php` | Base action class |
