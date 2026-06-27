<?php

namespace Plume\Libs;

/**
 * Curl封装
 */
class Curl
{
    public static $errno = 0;
    public static $error = '';
    public static $httpCode = 0;
    public static $cost = 0;

    /**
     * curl get请求
     *
     * @param string $url GET请求地址
     * @return mixed
     */
    public static function get($url, $timeout = 10)
    {
        if (empty($url)) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min($timeout, 10));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $startTime = microtime(true);
        $result = curl_exec($ch);

        self::$cost = round(microtime(true) - $startTime, 3);
        self::$errno = curl_errno($ch);
        self::$error = curl_error($ch);
        self::$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $result;
    }

    /**
     * curl post 请求
     * @param string $url
     * @param array $param
     */
    public static function post($url, $param = [], $headers = [], $timeout = 10)
    {
        if (empty($url)) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        $isMultipartFormData = false;
        foreach ($headers as $header) {
            if (stripos($header, 'multipart/form-data') !== false) {
                $isMultipartFormData = true;
            }
        }

        if ($isMultipartFormData) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
        }

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min($timeout, 10));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 设置Header信息
        !is_array($headers) && $headers = [];
        $headers[] = 'Expect:';
        // disable 100-continue
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $startTime = microtime(true);
        $result = curl_exec($ch);
        self::$cost = round(microtime(true) - $startTime, 3);
        self::$errno = curl_errno($ch);
        self::$error = curl_error($ch);
        self::$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        return $result;
    }

    /**
     * curl post json 请求
     * @param string $url
     * @param array|string $data Array will be JSON-encoded; pass a pre-encoded string to skip encoding.
     */
    public static function postJson($url, $data, $timeout = 10)
    {
        if (empty($url)) {
            return false;
        }

        $body = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min($timeout, 10));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $startTime = microtime(true);
        $result = curl_exec($ch);
        self::$cost = round(microtime(true) - $startTime, 3);
        self::$errno = curl_errno($ch);
        self::$error = curl_error($ch);
        self::$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $result;
    }
}
