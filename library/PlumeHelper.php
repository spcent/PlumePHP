<?php

declare(strict_types=1);

/**
 * PlumeHelper — static utility class.
 *
 * Consolidates all helpers previously scattered as global functions in common.php.
 * Global-function aliases in common.php delegate here for backward compatibility.
 */
class PlumeHelper
{
    // -----------------------------------------------------------------------
    // JSON
    // -----------------------------------------------------------------------

    public static function jsonFormat(mixed $json): string
    {
        if (!is_string($json)) {
            return (string) json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $result      = '';
        $pos         = 0;
        $strLen      = strlen($json);
        $indentStr   = "\t";
        $newLine     = "\n";
        $prevChar    = '';
        $outOfQuotes = true;
        for ($i = 0; $i < $strLen; $i++) {
            $copyLen = strcspn($json, $outOfQuotes ? " \t\r\n\",:[{}]" : "\\\"", $i);
            if ($copyLen >= 1) {
                $prevChar = '';
                $result  .= substr($json, $i, $copyLen);
                $i       += $copyLen - 1;
                continue;
            }
            $char = substr($json, $i, 1);
            if (!$outOfQuotes && $prevChar === '\\') {
                $result  .= $char;
                $prevChar = '';
                continue;
            }
            if ($char === '"' && $prevChar !== '\\') {
                $outOfQuotes = !$outOfQuotes;
            } elseif ($outOfQuotes && ($char === '}' || $char === ']')) {
                $result .= $newLine;
                $pos--;
                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            } elseif ($outOfQuotes && false !== strpos(" \t\r\n", $char)) {
                continue;
            }
            $result .= $char;
            if ($outOfQuotes && $char === ':') {
                $result .= ' ';
            } elseif ($outOfQuotes && ($char === ',' || $char === '{' || $char === '[')) {
                $result .= $newLine;
                if ($char === '{' || $char === '[') {
                    $pos++;
                }
                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }
            $prevChar = $char;
        }
        return $result;
    }

    public static function jsonOutput(string $msg, int $code = 0, mixed $data = '', bool $isFormat = false): void
    {
        $res = ['code' => $code, 'msg' => $msg, 'data' => $data];
        if ($isFormat) {
            echo '<pre>' . self::jsonFormat($res) . '</pre>';
        } else {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    // -----------------------------------------------------------------------
    // HTTP
    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $postData
     */
    public static function curlGetContents(
        string $url,
        array $postData = [],
        mixed $verbose = false,
        mixed $refUrl = false,
        mixed $cookieLocation = false,
        bool $returnTransfer = true
    ): string|false {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, $returnTransfer);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.10 (KHTML, like Gecko) Chrome/8.0.552.28 Safari/534.10');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        if ($cookieLocation !== false) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieLocation);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieLocation);
            curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
        }
        if ($verbose !== false) {
            $verbosePointer = fopen($verbose, 'w');
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, $verbosePointer);
        }
        if ($refUrl !== false) {
            curl_setopt($ch, CURLOPT_REFERER, $refUrl);
        }
        if (count($postData) > 0) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unset($ch);
        return $httpCode == 404 ? false : (is_string($result) ? $result : false);
    }

    public static function redirect(string $url, int $time = 0, string $msg = ''): never
    {
        $url = str_replace(["\n", "\r", "\0"], '', $url);

        // Block javascript: and data: URIs which cannot be safe redirect targets
        if (preg_match('/^\s*(?:javascript|data|vbscript):/i', $url)) {
            $url = '/';
        }

        if (empty($msg)) {
            $escapedUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $msg        = "系统将在{$time}秒之后自动跳转到{$escapedUrl}！";
        }
        if (!headers_sent()) {
            if (0 === $time) {
                header('Location: ' . $url);
            } else {
                header("refresh:{$time};url={$url}");
                echo htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            exit();
        } else {
            $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $str     = "<meta http-equiv='Refresh' content='{$time};URL={$safeUrl}'>";
            if ($time != 0) {
                $str .= htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            exit($str);
        }
    }

    public static function isWeixinBrowser(): bool
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        $agent = $_SERVER['HTTP_USER_AGENT'];
        return strpos($agent, 'MicroMessenger') !== false
            || strpos($agent, 'icroMessenger') !== false
            || strpos($agent, 'Windows Phone') !== false;
    }

    public static function isFromBrowser(): bool
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        if (!$ua) {
            return false;
        }
        if ((strpos($ua, 'mozilla') !== false) && ((strpos($ua, 'msie') !== false) || (strpos($ua, 'gecko') !== false))) {
            return true;
        }
        return strpos($ua, 'opera') !== false;
    }

    public static function getCurrentUrl(bool $isDomain = false): string
    {
        $url = 'http://';
        if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || $_SERVER['SERVER_PORT'] == '443') {
            $url = 'https://';
        }
        if ($_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443') {
            $url .= $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'];
        } else {
            $url .= $_SERVER['HTTP_HOST'];
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        // Encode non-ASCII and URI-unsafe bytes while preserving valid percent-encoded sequences.
        $uri = preg_replace_callback(
            '/[^\x21-\x7E]|[<>"\'\\\\]/',
            fn(array $m): string => rawurlencode($m[0]),
            $uri
        );
        return $isDomain ? $url : $url . $uri;
    }

    // -----------------------------------------------------------------------
    // Security / Crypto
    // -----------------------------------------------------------------------

    public static function generateNonceStr(int $length = 16): string
    {
        $chars   = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str     = '';
        $charLen = strlen($chars);
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, $charLen - 1)];
        }
        return $str;
    }

    /**
     * @param string[] $trustedProxies
     */
    public static function getClientIp(int $type = 0, array $trustedProxies = []): string|int
    {
        $type       = $type ? 1 : 0;
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!empty($trustedProxies) && in_array($remoteAddr, $trustedProxies, true)) {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $candidates = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
                $candidates = array_filter($candidates, fn(string $ip): bool => $ip !== 'unknown' && filter_var($ip, FILTER_VALIDATE_IP) !== false);
                $ip         = reset($candidates) ?: $remoteAddr;
            } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } else {
                $ip = $remoteAddr;
            }
        } else {
            $ip = $remoteAddr;
        }
        $long     = ip2long((string) $ip);
        $resolved = $long ? [(string) $ip, $long] : ['0.0.0.0', 0];
        return $resolved[$type];
    }

    /**
     * @param array<string, mixed> $datas
     */
    public static function signature(array $datas, string $key): string
    {
        ksort($datas);
        $tmp = [];
        foreach ($datas as $k => $v) {
            if ($v !== '' && $v !== null && $k !== 'signature' && $k !== 'sign') {
                $tmp[] = $k . '=' . $v;
            }
        }
        return hash_hmac('sha256', implode('&', $tmp), $key);
    }

    public static function htmlFilter(string $html): string
    {
        $filter = [
            "/\s/",
            "/<(\/?)(script|i?frame|style|html|body|title|link|\?|\%)([^>]*?)>/isU",
            "/(<[^>]*)on[a-zA-Z]\s*=([^>]*>)/isU",
        ];
        $replace = [' ', '&lt;\\1\\2\\3&gt;', '\\1\\2'];
        return (string) preg_replace($filter, $replace, $html);
    }

    /**
     * Encrypt or decrypt a string using AES-256-GCM (authenticated encryption).
     *
     * Encoding: base64url(nonce[12] + tag[16] + ciphertext) for ENCODE,
     * or the same layout for DECODE.
     *
     * The $expiry parameter is embedded as a 10-digit Unix timestamp prefix
     * inside the plaintext (0 = no expiry), preserving backward-compatible
     * semantics with the legacy RC4-based implementation this replaced.
     *
     * @param string $string    Plaintext (ENCODE) or ciphertext (DECODE)
     * @param string $operation 'ENCODE' or 'DECODE'
     * @param string $key       Secret key (min 16 chars); falls back to APP_SECRET
     * @param int    $expiry    Seconds until the token expires (0 = forever)
     * @return string Ciphertext on ENCODE, plaintext on DECODE, '' on failure
     */
    public static function authcode(string $string, string $operation = 'DECODE', string $key = '', int $expiry = 0): string
    {
        $rawKey = $key ?: (string) getenv('APP_SECRET') ?: 'plumephp-insecure-fallback-key!!';
        // Derive a 256-bit key via SHA-256 so any key length is accepted safely
        $derivedKey = hash('sha256', $rawKey, true);

        if (strtoupper($operation) === 'ENCODE') {
            $expireTs  = $expiry > 0 ? sprintf('%010d', time() + $expiry) : '0000000000';
            $plaintext = $expireTs . $string;
            $nonce     = random_bytes(12);
            $tag       = '';
            $cipher    = openssl_encrypt($plaintext, 'aes-256-gcm', $derivedKey, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
            if ($cipher === false) {
                return '';
            }
            return rtrim(strtr(base64_encode($nonce . $tag . $cipher), '+/', '-_'), '=');
        }

        // DECODE
        $raw = base64_decode(strtr($string, '-_', '+/') . str_repeat('=', (4 - strlen($string) % 4) % 4), true);
        if ($raw === false || strlen($raw) < 29) { // 12 nonce + 16 tag + 1 min payload
            return '';
        }
        $nonce  = substr($raw, 0, 12);
        $tag    = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain  = openssl_decrypt($cipher, 'aes-256-gcm', $derivedKey, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plain === false) {
            return '';
        }
        $expireTs = (int) substr($plain, 0, 10);
        if ($expireTs !== 0 && $expireTs < time()) {
            return '';
        }
        return substr($plain, 10);
    }

    // -----------------------------------------------------------------------
    // Data / String
    // -----------------------------------------------------------------------

    /**
     * @param array<mixed> $arr1
     * @param array<mixed> $arr2
     */
    public static function arrayMergeDeep(array &$arr1, array $arr2): void
    {
        foreach ($arr2 as $k => $v) {
            if (!isset($arr1[$k])) {
                $arr1[$k] = $v;
            } elseif (!is_array($arr1[$k]) || !is_array($v)) {
                $arr1[$k] = $v;
            } else {
                self::arrayMergeDeep($arr1[$k], $v);
            }
        }
    }

    public static function uuid(string $prefix = ''): string
    {
        $bytes = random_bytes(16);
        // Set version 4 (random) and variant bits per RFC 4122
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
        return $prefix ? $prefix . '-' . $uuid : $uuid;
    }

    public static function strcut(string $str, int $len, string $ext = '', int $zhLen = 0): string
    {
        $count  = 0;
        $output = '';
        preg_match_all('/./us', $str, $match);
        foreach ($match[0] as $v) {
            $vLen    = strlen($v);
            $count  += ($zhLen == 0) ? $vLen : $zhLen;
            $output .= $v;
            if ($count >= $len) {
                break;
            }
        }
        if (strlen($output) < strlen($str)) {
            $output .= $ext;
        }
        return $output;
    }

    public static function str2hex(string $str): string
    {
        $hex = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $hex .= substr('0' . dechex(ord($str[$i])), -2);
        }
        return $hex;
    }

    public static function hex2str(string $hex): string
    {
        $string = '';
        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
            $string .= chr((int) hexdec($hex[$i] . $hex[$i + 1]));
        }
        return $string;
    }

    public static function humanDate(int $dateline, string $dateformat = 'Y-m-d H:i:s'): string
    {
        $now    = defined('PLUME_CURRENT_TIME') ? PLUME_CURRENT_TIME : time();
        $second = $now - $dateline;
        return match(true) {
            $second > 31536000 => date($dateformat, $dateline),
            $second > 2592000  => floor($second / 2592000) . '月前',
            $second > 86400    => floor($second / 86400) . '天前',
            $second > 3600     => floor($second / 3600) . '小时前',
            $second > 60       => floor($second / 60) . '分钟前',
            default            => $second . '秒前',
        };
    }

    // -----------------------------------------------------------------------
    // Money
    // -----------------------------------------------------------------------

    public static function moneyYuanToFen(float|int|string $price): int
    {
        if (function_exists('bcmul')) {
            return intval(bcmul((string) $price, '100', 2));
        }
        return (int) round((float) $price * 100);
    }

    public static function moneyFenToYuan(int $price): string
    {
        if (function_exists('bcdiv')) {
            return bcdiv((string) $price, '100', 2);
        }
        return number_format($price / 100, 2, '.', '');
    }

    // -----------------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------------

    /**
     * @param array<mixed> $data
     */
    public static function exportCsv(string $filename, array $data): never
    {
        header('Cache-Control: public');
        header('Pragma: public');
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment; filename={$filename}.csv");
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Type: application/force-download');
        $handle = fopen('php://output', 'w');
        if ($handle === false) {
            exit;
        }
        foreach ($data as $v) {
            if (is_array($v)) {
                fputcsv($handle, $v);
            }
        }
        exit;
    }

    public static function fetchFromArray(mixed &$array, mixed $index = null, mixed $default = null): mixed
    {
        if (is_null($index)) {
            return $array;
        }
        if (isset($array[$index])) {
            return $array[$index];
        }
        if (strpos((string) $index, '/')) {
            $keys = explode('/', $index);
            return match(count($keys)) {
                1 => $array[$keys[0]] ?? $default,
                2 => $array[$keys[0]][$keys[1]] ?? $default,
                3 => $array[$keys[0]][$keys[1]][$keys[2]] ?? $default,
                4 => $array[$keys[0]][$keys[1]][$keys[2]][$keys[3]] ?? $default,
                default => $default,
            };
        }
        return $default;
    }

    // -----------------------------------------------------------------------
    // Debug
    // -----------------------------------------------------------------------

    public static function printStackTrace(bool $isEcho = true): mixed
    {
        $array = debug_backtrace();
        $html  = '';
        foreach ($array as $row) {
            // @phpstan-ignore-next-line (isset on 'function' is always true but used with optional keys for brevity)
            if (isset($row['file'], $row['line'], $row['function'])) {
                $html .= '<p>' . $row['file'] . ':' . $row['line'] . ' call: ' . $row['function'] . '</p>';
            }
        }
        if ($isEcho) {
            echo $html;
            return null;
        }
        return $html;
    }

    public static function dump(mixed ...$vars): void
    {
        $isCli = defined('IS_CLI') ? IS_CLI : (PHP_SAPI === 'cli');
        if (!$isCli) {
            echo '<pre style="font-size:12px; color:#0000FF">' . PHP_EOL;
        }
        foreach ($vars as $var) {
            if (is_array($var)) {
                print_r($var);
            } elseif (is_object($var)) {
                echo get_class($var) . ' Object';
            } elseif (is_resource($var)) {
                echo (string) $var;
            } else {
                var_dump($var);
            }
        }
        if (!$isCli) {
            echo '</pre>' . PHP_EOL;
        }
    }

    public static function dumpWithExit(mixed ...$vars): never
    {
        foreach ($vars as $var) {
            self::dump($var);
        }
        exit;
    }

    public static function generateDebugTrace(mixed $e): string
    {
        $removeThisCall = false;
        if (empty($e) || !is_a($e, 'Exception')) {
            $e              = new \Exception();
            $removeThisCall = true;
        }
        $trace = explode("\n", $e->getTraceAsString());
        $trace = array_reverse($trace);
        array_shift($trace);
        if ($removeThisCall) {
            array_pop($trace);
        }
        $length = count($trace);
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $pos = strpos($trace[$i], ' ');
            $result[] = ($i + 1) . ')' . ($pos !== false ? substr($trace[$i], $pos) : $trace[$i]);
        }
        return "\t" . implode("\n\t", $result);
    }
}
