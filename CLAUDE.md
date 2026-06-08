# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests (requires PHPUnit installed globally or via vendor)
phpunit tests/

# Run a single test file
phpunit tests/RouterTest.php

# Start the built-in dev server
php -S localhost:8000 -t public/

# Install dependencies
composer install
```

> PHPUnit is not declared in `composer.json`; install globally (`composer global require phpunit/phpunit`) or add it to `require-dev`. Tests use the legacy `PHPUnit_Framework_TestCase` base class.

## Architecture

### Single-file core

The entire framework lives in **`library/PlumePHP.php`** (~4300 lines, 14 classes). There is no build step — it is included directly.

```
PlumePHP          Static facade (all public API calls go through this)
  └─ PlumeEngine  Application instance (DI container / service locator)
       ├─ PlumeRouter      URL pattern matching
       ├─ PlumeRequest     HTTP request wrapper
       ├─ PlumeResponse    HTTP response + redirect + JSON
       ├─ PlumeView        Native-PHP template rendering
       ├─ PlumeEvent       Method invocation with before/after filter chains
       ├─ PlumeLoader      Class autoloader + registry
       ├─ PlumeCollection  ArrayAccess/Iterator data container (query, data, cookies, files)
       ├─ PlumeLogger      File-based logging
       └─ PlumeCmdService  CLI command runner
```

`PlumePHP::app()` returns the singleton `PlumeEngine`. All static calls on `PlumePHP` are forwarded to it via `__callStatic`.

### Extension mechanisms

- **`PlumePHP::map('name', fn)`** — adds or overrides a method on the engine (e.g. override `notFound`, `error`)
- **`PlumePHP::register('name', 'Class', $params, $callback)`** — lazy-instantiates a class as a named service; `PlumePHP::name()` returns the shared instance, `PlumePHP::name(false)` returns a new one
- **`PlumePHP::before('method', fn)` / `::after('method', fn)`** — wraps any mapped or extensible method; returning `false` from a before-filter breaks the chain

### Request lifecycle

1. `public/index.php` includes `library/PlumePHP.php`, gets `PlumePHP::app()`
2. Routes are registered; the catch-all `*` route calls `$app->runAction()`
3. `runAction()` resolves `/{module}/{controller}/{action}` → loads `application/{module}/{module}.boot.php`, then `application/{module}/actions/{controller}.action.php`, instantiates the action class, calls `execute()`
4. `PlumePHP::start()` dispatches the request through the router

### Module / action layout

```
application/
  {module}/
    {module}.boot.php        # bootstraps module, registers paths, defines base action class
    actions/
      {controller}.action.php  # defines {module}_{controller}_action extending base action
```

Action classes extend `\Plume\Libs\Action` (in `library/core/Plume/Libs/Action.php`), which provides CSRF validation, `invoke()` dispatch, and request/response helpers.

### Helper functions (`library/common.php`)

| Function | Purpose |
|---|---|
| `C($key, $val)` | Get/set config via dot notation (3 levels) |
| `I($file)` | Conditionally include a file |
| `L($msg, $level)` | Write to log |
| `json_output($msg, $code, $data, $status)` | Emit standard JSON envelope |
| `DB($name)` | Get Medoo database instance by config key |

### Configuration

`config/config.php` returns a plain array loaded by the framework. Access via `C('key')` or `C('db.master.host')`. Environment overrides use `PLUME_PHP_ENV` and `PLUME_LOG_PATH` env vars (see `env.sample`).

### Routing syntax quick reference

```
/path/@name           named parameter
/path/@id:[0-9]+      named param with regex
/path(/@year(/@month))  optional segments
/path/*               wildcard (splat)
GET /path             method-restricted
GET|POST /path        multi-method
```

Routes are matched in declaration order; returning `true` from a handler passes to the next match.
