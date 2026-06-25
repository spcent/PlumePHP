<?php

declare(strict_types=1);

/**
 * FrankenPHP Worker Mode entry point.
 *
 * This file keeps PHP alive between requests so each request pays no cold-start
 * cost. Config loading, timezone setup, and autoloading happen once at startup;
 * the per-request state (router, request/response objects, session) is reset at
 * the top of every iteration via PlumePHP::resetForWorker().
 *
 * Usage
 * -----
 * Development (traditional mode, zero worker overhead):
 *   frankenphp php-server --root public/
 *
 * Development / production (worker mode, persistent processes):
 *   frankenphp php-server --worker public/worker.php --root public/
 *
 * Or via the Caddyfile:
 *   {
 *       frankenphp
 *   }
 *   localhost {
 *       root * /path/to/PlumePHP/public
 *       php_server {
 *           worker worker.php
 *       }
 *   }
 *
 * Caveats
 * -------
 * - Do NOT store mutable request-specific state in static variables or
 *   module-level singletons — they persist between requests in worker mode.
 * - If you introduce new global/static state in application code, reset it
 *   inside the handler closure before PlumePHP::start().
 * - $C() config is intentionally preserved across requests (same config for
 *   all requests in the same worker process).
 */

if (PHP_SAPI !== 'frankenphp') {
    fwrite(STDERR, "worker.php must be run under FrankenPHP worker mode.\n");
    exit(1);
}

if (!isset($_SERVER['HTTP_MOD_REWRITE'])) {
    $_SERVER['HTTP_MOD_REWRITE'] = 'Off';
}

define('DS', DIRECTORY_SEPARATOR);
define('PLUME_PHP_PATH', dirname(__DIR__));

// ── Boot once ────────────────────────────────────────────────────────────────
// Loads the framework, reads config, sets timezone, initialises autoloaders.
// This block runs ONCE when the worker process starts.
require PLUME_PHP_PATH . DS . 'library' . DS . 'PlumePHP.php';
PlumePHP::app();

// ── Request loop ─────────────────────────────────────────────────────────────
// frankenphp_handle_request() blocks until an HTTP request arrives, calls the
// handler, then loops back. Return false from the handler to stop the loop.
$handler = static function (): void {
    // Reset per-request state: clears router, loader instances, dispatcher
    // filters, engine vars. Preserves boot-time config and timezone.
    PlumePHP::resetForWorker();

    $app = PlumePHP::app();

    // ── Register routes ───────────────────────────────────────────────────────
    // Routes must be re-registered each iteration because the router is cleared
    // by resetForWorker(). Keep this section identical to public/index.php.

    $app->route('GET /api', function () {
        json_output('success', 0, 'api', true);
        return false;
    });

    $app->route('GET /', function () {
        echo 'Hello World!';
        return false;
    });

    // Catch-all: resolves /{module}/{controller}/{action} via the file system.
    $app->route('*', function () use ($app) {
        $app->runAction();
        return false;
    });

    // ── Dispatch ──────────────────────────────────────────────────────────────
    $app->start();
};

while (frankenphp_handle_request($handler));
