<?php
/*
 * 	This file is part of the your PlumePHP package.
 *
 * 	The PHP Application For Code Poem For You.
 * 	(c) 2015-2035 http://plumephp.com All rights reserved.
 *
 * 	For the full copyright and license information, please view the LICENSE
 * 	file that was distributed with this source code.
 */

// Ensure the static helper class is available (idempotent).
if (!class_exists('PlumeHelper', false)) {
    require_once __DIR__ . '/PlumeHelper.php';
}

// ------------------------------------------------------------------------
// Global-function aliases — delegate to PlumeHelper for single-source logic.
// ------------------------------------------------------------------------

if (!function_exists('json_format')) {
    function json_format(mixed $json): string
    {
        return PlumeHelper::jsonFormat($json);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('json_output')) {
    function json_output(string $msg, int $code = 0, mixed $data = '', bool $is_format = false): void
    {
        PlumeHelper::jsonOutput($msg, $code, $data, $is_format);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('curl_get_contents')) {
    /**
     * @param array<string, mixed> $post_data
     */
    function curl_get_contents(
        string $url,
        array $post_data = [],
        mixed $verbose = false,
        mixed $ref_url = false,
        mixed $cookie_location = false,
        bool $return_transfer = true
    ): string|false {
        return PlumeHelper::curlGetContents($url, $post_data, $verbose, $ref_url, $cookie_location, $return_transfer);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('redirect')) {
    function redirect(string $url, int $time = 0, string $msg = ''): never
    {
        PlumeHelper::redirect($url, $time, $msg);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('is_weixin_browser')) {
    function is_weixin_browser(): bool
    {
        return PlumeHelper::isWeixinBrowser();
    }
}
// ------------------------------------------------------------------------
if (!function_exists('is_from_browser')) {
    function is_from_browser(): bool
    {
        return PlumeHelper::isFromBrowser();
    }
}
// ------------------------------------------------------------------------
if (!function_exists('get_current_url')) {
    function get_current_url(bool $is_domain = false): string
    {
        return PlumeHelper::getCurrentUrl($is_domain);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('generate_nonce_str')) {
    function generate_nonce_str(int $length = 16): string
    {
        return PlumeHelper::generateNonceStr($length);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('get_client_ip')) {
    /**
     * @param string[] $trusted_proxies
     */
    function get_client_ip(int $type = 0, array $trusted_proxies = []): string|int
    {
        return PlumeHelper::getClientIp($type, $trusted_proxies);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('signature')) {
    /**
     * @param array<string, mixed> $datas
     */
    function signature(array $datas, string $key): string
    {
        return PlumeHelper::signature($datas, $key);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('export_csv')) {
    /**
     * @param array<mixed> $data
     */
    function export_csv(string $filename, array $data): never
    {
        PlumeHelper::exportCsv($filename, $data);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('authcode')) {
    function authcode(string $string, string $operation = 'DECODE', string $key = '', int $expiry = 0): string
    {
        return PlumeHelper::authcode($string, $operation, $key, $expiry);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('array_merge_deep')) {
    /**
     * @param array<mixed> $arr1
     * @param array<mixed> $arr2
     */
    function array_merge_deep(array &$arr1, array $arr2): void
    {
        PlumeHelper::arrayMergeDeep($arr1, $arr2);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('uuid')) {
    function uuid(string $prefix = ''): string
    {
        return PlumeHelper::uuid($prefix);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('html_filter')) {
    function html_filter(string $html): string
    {
        return PlumeHelper::htmlFilter($html);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('strcut')) {
    function strcut(string $str, int $len, string $ext = '', int $zh_len = 0): string
    {
        return PlumeHelper::strcut($str, $len, $ext, $zh_len);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('str2hex')) {
    function str2hex(string $str): string
    {
        return PlumeHelper::str2hex($str);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('hex2str')) {
    function hex2str(string $hex): string
    {
        return PlumeHelper::hex2str($hex);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('human_date')) {
    function human_date(int $dateline, string $dateformat = 'Y-m-d H:i:s'): string
    {
        return PlumeHelper::humanDate($dateline, $dateformat);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('print_stack_trace')) {
    function print_stack_trace(bool $is_echo = true): mixed
    {
        return PlumeHelper::printStackTrace($is_echo);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('dump')) {
    function dump(): void
    {
        PlumeHelper::dump(...func_get_args());
    }
}
// ------------------------------------------------------------------------
if (!function_exists('dump_with_exit')) {
    function dump_with_exit(): never
    {
        PlumeHelper::dumpWithExit(...func_get_args());
    }
}
// ------------------------------------------------------------------------
if (!function_exists('fetch_from_array')) {
    function fetch_from_array(mixed &$array, mixed $index = null, mixed $default = null): mixed
    {
        return PlumeHelper::fetchFromArray($array, $index, $default);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('money_yuan_to_fen')) {
    function money_yuan_to_fen(float|int|string $price): int
    {
        return PlumeHelper::moneyYuanToFen($price);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('money_fen_to_yuan')) {
    function money_fen_to_yuan(int $price): string
    {
        return PlumeHelper::moneyFenToYuan($price);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('generate_debug_trace')) {
    function generate_debug_trace(mixed $e): string
    {
        return PlumeHelper::generateDebugTrace($e);
    }
}
