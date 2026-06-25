<?php

declare(strict_types=1);

/**
 * Built-in PlumeLogger handler factories.
 *
 * Each static method returns a callable that can be passed to
 * PlumeLogger::addHandler().  Handlers are only triggered for the log levels
 * that matter to them — everything else is a no-op.
 *
 * Usage:
 *   PlumePHP::logger()->addHandler(
 *       PlumeLogHandlers::dingtalk('https://oapi.dingtalk.com/robot/send?access_token=xxx')
 *   );
 *   PlumePHP::logger()->addHandler(
 *       PlumeLogHandlers::sentry('https://key@sentry.io/project')
 *   );
 *   PlumePHP::logger()->addHandler(
 *       PlumeLogHandlers::minLevel('WARNING', PlumeLogHandlers::dingtalk($webhook))
 *   );
 */
class PlumeLogHandlers
{
    /**
     * Sends ERROR / FATAL / CRITICAL / EMERGENCY / ALERT entries to a
     * DingTalk robot webhook as a markdown card.
     *
     * @param string   $webhook    Full DingTalk robot URL
     * @param string[] $atMobiles Phone numbers to @mention (optional)
     */
    public static function dingtalk(string $webhook, array $atMobiles = []): callable
    {
        static $alerted = [];

        return function (string $level, string $message, array $context) use ($webhook, $atMobiles, &$alerted) {
            $alertLevels = ['ERROR', 'FATAL', 'CRITICAL', 'EMERGENCY', 'ALERT'];
            if (!in_array(strtoupper($level), $alertLevels, true)) {
                return;
            }

            // Deduplicate: same message within one request doesn't multi-fire
            $key = md5($level . $message);
            if (isset($alerted[$key])) {
                return;
            }
            $alerted[$key] = true;

            $title   = '[' . strtoupper($level) . '] ' . (defined('SITE_DOMAIN') ? SITE_DOMAIN : 'PlumePHP');
            $text    = "### {$title}\n\n"
                     . '**Time:** ' . date('Y-m-d H:i:s') . "  \n"
                     . '**Level:** ' . strtoupper($level) . "  \n"
                     . '**Message:** ' . $message . "  \n";

            if ($context) {
                $text .= '**Context:** `' . json_encode($context, JSON_UNESCAPED_UNICODE) . "`  \n";
            }

            $payload = json_encode([
                'msgtype'  => 'markdown',
                'markdown' => ['title' => $title, 'text' => $text],
                'at'       => ['atMobiles' => $atMobiles, 'isAtAll' => false],
            ], JSON_UNESCAPED_UNICODE);

            self::asyncPost($webhook, $payload);
        };
    }

    /**
     * Captures ERROR+ events to Sentry via its Store API.
     * Requires ext-curl.  This is a minimal integration — use the official
     * sentry/sentry-php SDK for production use.
     *
     * @param string $dsn Sentry DSN, e.g. https://key@sentry.io/project_id
     */
    public static function sentry(string $dsn): callable
    {
        return function (string $level, string $message, array $context) use ($dsn) {
            $alertLevels = ['ERROR', 'FATAL', 'CRITICAL', 'EMERGENCY', 'ALERT'];
            if (!in_array(strtoupper($level), $alertLevels, true)) {
                return;
            }

            $parts = parse_url($dsn);
            if (!$parts || empty($parts['host']) || empty($parts['user'])) {
                return;
            }

            $projectId = ltrim($parts['path'] ?? '', '/');
            $endpoint  = $parts['scheme'] . '://' . $parts['host'] . '/api/' . $projectId . '/store/';
            $authHeader = 'Sentry sentry_version=7'
                        . ', sentry_key=' . $parts['user']
                        . ', sentry_client=plumephp/1.0';

            $envelope = json_encode([
                'event_id'   => str_replace('-', '', sprintf('%04x%04x-%04x-%04x-%04x-%012x',
                    random_int(0, 0xffff), random_int(0, 0xffff),
                    random_int(0, 0xffff),
                    random_int(0, 0x0fff) | 0x4000,
                    random_int(0, 0x3fff) | 0x8000,
                    random_int(0, 0xffffffffffff)
                )),
                'timestamp'  => date('Y-m-d\TH:i:s'),
                'level'      => strtolower($level),
                'platform'   => 'php',
                'message'    => ['formatted' => $message],
                'extra'      => $context,
                'server_name'=> gethostname() ?: 'unknown',
            ], JSON_UNESCAPED_UNICODE);

            self::asyncPost($endpoint, $envelope, ['X-Sentry-Auth: ' . $authHeader]);
        };
    }

    /**
     * Wraps another handler and only invokes it when the log level is at least
     * $minLevel.  Level order (low→high): DEBUG, INFO, NOTICE, WARNING, ERROR,
     * CRITICAL, ALERT, EMERGENCY, FATAL.
     *
     * @param string   $minLevel Minimum level to pass through (case-insensitive)
     * @param callable $handler  The inner handler to call
     */
    public static function minLevel(string $minLevel, callable $handler): callable
    {
        static $order = [
            'DEBUG' => 0, 'INFO' => 1, 'NOTICE' => 2, 'WARNING' => 3, 'WARN' => 3,
            'ERROR' => 4, 'CRITICAL' => 5, 'ALERT' => 6, 'EMERGENCY' => 7, 'FATAL' => 8,
        ];

        $threshold = $order[strtoupper($minLevel)] ?? 0;

        return function (string $level, string $message, array $context) use ($handler, $threshold, $order) {
            $current = $order[strtoupper($level)] ?? 0;
            if ($current >= $threshold) {
                $handler($level, $message, $context);
            }
        };
    }

    /**
     * Fire-and-forget HTTP POST using cURL.
     * Runs in the same process but with a 2s timeout so it doesn't block the response.
     *
     * @param string   $url     Target URL
     * @param string   $payload JSON body
     * @param string[] $headers Additional headers
     */
    private static function asyncPost(string $url, string $payload, array $headers = []): void
    {
        if (!function_exists('curl_init')) {
            return;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }

        $defaultHeaders = ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)];
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => array_merge($defaultHeaders, $headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
