<?php if (!defined('PLUME_PHP_PATH')) exit('No direct script access allowed');
/**
 * PlumePHP是一款开源免费、轻量级的PHP框架。具有低耦合、轻量级、基于VBD模型等特点，
 * 加速高性能现代WEB网站及WebApp应用的开发。
 */
// ------------------------------------------------------------------------
if (!function_exists('json_format')) {
    /**
     * Format a flat JSON string to make it more human-readable
     *
     * @param string $json The original JSON string to process
     *        When the input is not a string it is assumed the input is RAW
     *        and should be converted to JSON first of all.
     * @return string Indented version of the original JSON string
     */
    function json_format($json)
    {
        if (!is_string($json)) {
            if (phpversion() && phpversion() >= 5.4) {
                return json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            }
            $json = json_encode($json, JSON_UNESCAPED_UNICODE);
        }
        $result      = '';
        $pos         = 0;               // indentation level
        $strLen      = strlen($json);
        $indentStr   = "\t";
        $newLine     = "\n";
        $prevChar    = '';
        $outOfQuotes = true;
        for ($i = 0; $i < $strLen; $i++) {
            // Speedup: copy blocks of input which don't matter re string detection and formatting.
            $copyLen = strcspn($json, $outOfQuotes ? " \t\r\n\",:[{}]" : "\\\"", $i);
            if ($copyLen >= 1) {
                $copyStr = substr($json, $i, $copyLen);
                // Also reset the tracker for escapes: we won't be hitting any right now
                // and the next round is the first time an 'escape' character can be seen again at the input.
                $prevChar = '';
                $result .= $copyStr;
                $i += $copyLen - 1;      // correct for the for(;;) loop
                continue;
            }

            // Grab the next character in the string
            $char = substr($json, $i, 1);

            // Are we inside a quoted string encountering an escape sequence?
            if (!$outOfQuotes && $prevChar === '\\') {
                // Add the escaped character to the result string and ignore it for the string enter/exit detection:
                $result .= $char;
                $prevChar = '';
                continue;
            }
            // Are we entering/exiting a quoted string?
            if ($char === '"' && $prevChar !== '\\') {
                $outOfQuotes = !$outOfQuotes;
            }
            // If this character is the end of an element,
            // output a new line and indent the next line
            else if ($outOfQuotes && ($char === '}' || $char === ']')) {
                $result .= $newLine;
                $pos--;
                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }
            // eat all non-essential whitespace in the input as we do our own here and it would only mess up our process
            else if ($outOfQuotes && false !== strpos(" \t\r\n", $char)) {
                continue;
            }
            // Add the character to the result string
            $result .= $char;
            // always add a space after a field colon:
            if ($outOfQuotes && $char === ':') {
                $result .= ' ';
            }
            // If the last character was the beginning of an element,
            // output a new line and indent the next line
            else if ($outOfQuotes && ($char === ',' || $char === '{' || $char === '[')) {
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
}
// ------------------------------------------------------------------------
if (!function_exists('json_output')) {
    // 异步输出结果,0表示成功，非0表示失败
    function json_output($msg, $code = 0, $data = '', $is_format = false)
    {
        $res = ['code'=>$code, 'msg'=>$msg, 'data'=>$data];
        if ($is_format) {
            echo '<pre>'.json_format($res).'</pre>';
        } else {
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
        }
    }
}
// ------------------------------------------------------------------------
if (!function_exists('curl_get_contents')) {
    // 基于curl的file_get_contents
    function curl_get_contents(
            $url,
            array $post_data = [],
            $verbose = false,
            $ref_url = false,
            $cookie_location = false,
            $return_transfer = true)
    {
    	$ch = curl_init();
    	curl_setopt($ch, CURLOPT_URL, $url);
    	curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, $return_transfer);
    	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.10 (KHTML, like Gecko) Chrome/8.0.552.28 Safari/534.10");
    	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    	curl_setopt($ch, CURLOPT_HEADER, false);
    	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    	curl_setopt($ch, CURLOPT_AUTOREFERER, true);

    	if ($cookie_location !== false) {
    		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_location);
    		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_location);
    		curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    	}

    	if ($verbose !== false) {
    		$verbose_pointer = fopen($verbose, 'w');
    		curl_setopt($ch, CURLOPT_VERBOSE, true);
    		curl_setopt($ch, CURLOPT_STDERR, $verbose_pointer);
    	}

    	if ($ref_url !== false) {
    	    curl_setopt($ch, CURLOPT_REFERER, $ref_url);
    	}

    	if (count($post_data) > 0) {
    	    curl_setopt($ch, CURLOPT_POST, true);
    	    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    	}

    	$result = curl_exec($ch);
    	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    	if ($http_code == 404) {
    		return false;
    	}

    	curl_close($ch);
    	unset($ch);
    	return $result;
    }
}
// ------------------------------------------------------------------------
if (!function_exists('redirect')) {
    /**
     * URL重定向
     * @param string $url 重定向的URL地址
     * @param integer $time 重定向的等待时间（秒）
     * @param string $msg 重定向前的提示信息
     * @return void
     */
    function redirect($url, $time = 0, $msg = '')
    {
        //多行URL地址支持
        $url = str_replace(["\n", "\r"], '', $url);
        if (empty($msg))  $msg = "系统将在{$time}秒之后自动跳转到{$url}！";
        if (!headers_sent()) {
            // redirect
            if (0 === $time) {
                header('Location: ' . $url);
            } else {
                header("refresh:{$time};url={$url}");
                echo($msg);
            }
            exit();
        } else {
            $str = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
            if ($time != 0) $str .= $msg;
            exit($str);
        }
    }
}
// ------------------------------------------------------------------------
if (!function_exists('is_weixin_browser')) {
    // 判断是否是在微信浏览器里
    function is_weixin_browser()
    {
        $agent = $_SERVER ['HTTP_USER_AGENT'];
        if (strpos($agent, 'MicroMessenger') !== false
            || strpos($agent, 'icroMessenger') !== false
            || strpos($agent, 'Windows Phone' !== false)) {
            return true;
        }

        return false;
    }
}
// ------------------------------------------------------------------------
if (!function_exists('is_from_browser')) {
    /**
     * 返回是否是通过浏览器访问的页面
     *
     * @author wj
     * @param  void
     * @return boolen
     */
    function is_from_browser()
    {
        static $ret_val = null;
        if ($ret_val === null) {
            $ret_val = false;
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
            if ($ua) {
                if ((strpos($ua, 'mozilla') !== false) && ((strpos($ua, 'msie') !== false) || (strpos($ua, 'gecko') !== false))) {
                    $ret_val = true;
                } elseif (strpos($ua, 'opera')) {
                    $ret_val = true;
                }
            }
        }
        return $ret_val;
    }
}
// ------------------------------------------------------------------------
if (!function_exists('get_current_url')) {
    // php获取当前访问的完整url地址
    function get_current_url($isDomain = false)
    {
        $url = 'http://';
        if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
            || $_SERVER['SERVER_PORT'] == '443') {
            $url = 'https://';
        }
        if ($_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443') {
            $url .= $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'];
        } else {
            $url .= $_SERVER['HTTP_HOST'];
        }

        return $isDomain ? $url : $url.$_SERVER['REQUEST_URI'];
    }
}
// ------------------------------------------------------------------------
if (!function_exists('generate_nonce_str')) {
    // 生成随机字符串
    function generate_nonce_str($length = 16)
    {
        // 密码字符集，可任意添加你需要的字符
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        $length = strlen($chars);
        for($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0,  $length - 1)];
        }
        return $str;
    }
}
// ------------------------------------------------------------------------
if (!function_exists('get_client_ip')) {
    /**
     * 获取客户端IP地址
     * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @return mixed
     */
    function get_client_ip($type = 0)
    {
        $type = $type ? 1 : 0;
        static $ip = NULL;

        if ($ip !== NULL) return $ip[$type];
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos    =   array_search('unknown',$arr);
            if(false !== $pos) unset($arr[$pos]);
            $ip     =   trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip     =   $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip     =   $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = ip2long($ip);
        $ip = $long ? [$ip, $long] : ['0.0.0.0', 0];
        return $ip[$type];
    }
}
// ------------------------------------------------------------------------
if (!function_exists('signature')) {
    function signature($datas, $key = 'afjd32t4-#of=2a;2fd#c@ff')
    {
        // 数据类型检测
        if (!is_array($datas)) {
            $datas = (array)$datas;
        }

        ksort($datas);
        $tmp = [];
        foreach ($datas as $k => $v) {
            if ("" !== $v && null != $v && ($k !== 'signature' && $k !== 'sign')) {
              $tmp[] = $k ."=". $v;
            }
        }

        $query_string = implode($tmp, '&');
        return md5($query_string."&key=".$key);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('export_csv')) {
    /**
     * 导出数据到csv文件提供下载
     *
     * @param string $filename 下载文件名
     * @param array $data 数据
     */
    function export_csv($filename, array $data)
    {
        header("Cache-Control: public");
        header("Pragma: public");
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename={$filename}.csv");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Type: application/force-download");
        $handle = fopen("php://output", "w");
        foreach ($data as $v) {
            if (is_array($v)) {
                fputcsv($handle, $v);
            }
        }
        exit;
    }
}
// ------------------------------------------------------------------------
if (!function_exists('authcode')) {
    /**
     * 字符串加密/解密
     *
     * 修改自discuz(http://www.discuz.net)
     *
     * @param string $string 字符串，明文 或 密文
     * @param string $operation 操作，DECODE表示解密,其它表示加密
     * @param string $key 密匙
     * @param int $expiry 密文有效期
     * @return string
     */
    function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {
        // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
        $ckeyLength = 4;
        // 密匙
        $key = md5($key ? $key : 'plumephp');
        // 密匙a会参与加解密
        $keya = md5(substr($key, 0, 16));
        // 密匙b会用来做数据完整性验证
        $keyb = md5(substr($key, 16, 16));
        // 密匙c用于变化生成的密文
        $keyc = $ckeyLength
            ? ($operation == 'DECODE' ? substr($string, 0, $ckeyLength) : substr(md5(microtime()), -$ckeyLength))
            : '';

        // 参与运算的密匙
        $cryptkey = $keya . md5($keya . $keyc);
        $keyLength = strlen($cryptkey);

        // 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，解密时会通过这个密匙验证数据完整性
        // 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
        $string = $operation == 'DECODE'
            ? base64_decode(substr($string, $ckeyLength))
            : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;

        $stringLength = strlen($string);

        $result = '';
        $box = range(0, 255);

        // 产生密匙簿
        $rndkey = [];
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $keyLength]);
        }

        // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        // 核心加解密部分
        for ($a = $j = $i = 0; $i < $stringLength; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            // 从密匙簿得出密匙进行异或，再转成字符
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if ($operation == 'DECODE') {
            // substr($result, 0, 10) == 0 验证数据有效性
            // substr($result, 0, 10) - time() > 0 验证数据有效性
            // substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16) 验证数据完整性
            // 验证数据有效性，请看未加密明文的格式
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0)
                && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
            // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
            return $keyc . str_replace('=', '', base64_encode($result));
        }
    }
}
// ------------------------------------------------------------------------
if (!function_exists('array_merge_deep')) {
    /**
     * 数组递归合并
     * @param array $arr1 数组1
     * @param array $arr2 数组2
     */
    function array_merge_deep(array &$arr1, array $arr2)
    {
        foreach ($arr2 as $k => $v) {
            if (!isset($arr1[$k])) {
                $arr1[$k] = $v;
            } else {
                if (!is_array($arr1[$k]) || !is_array($v)) {
                    $arr1[$k] = $v;
                } else {
                    array_merge_deep($arr1[$k], $v);
                }
            }
        }
    }
}
// ------------------------------------------------------------------------
if (!function_exists('uuid')) {
    /**
     * 构造唯一ID
     * @param string $prefix 指定前缀参数
     * @return string
     */
    function uuid($prefix = "")
    {
        $time = md5(microtime());
        $rand1 = md5(substr($time, rand(0, 10), rand(22, 32)));
        $rand2 = md5(substr($rand1, rand(0, 10), rand(22, 32)));
        return strtolower(md5($prefix . uniqid($prefix) . $time . $rand1 . $rand2));
    }
}
// ------------------------------------------------------------------------
if (!function_exists('html_filter')) {
    /**
     * 危险 HTML代码过滤器
     * @param string $html 需要过滤的html代码
     * @return string
     */
    function html_filter($html)
    {
        $filter = [
            "/\s/",
            "/<(\/?)(script|i?frame|style|html|body|title|link|\?|\%)([^>]*?)>/isU",//object|meta|
            "/(<[^>]*)on[a-zA-Z]\s*=([^>]*>)/isU",
        ];

        $replace = [
            " ",
            "&lt;\\1\\2\\3&gt;",
            "\\1\\2",
        ];

        $str = preg_replace($filter,$replace,$html);
        return $str;
    }
}
// ------------------------------------------------------------------------
if (!function_exists('strcut')) {
    /**
     * 字符串截取
     * @param string $str 字符串
     * @param int $len 截取长度
     * @param string $ext 多余内容替换字符串
     * @param int $zhLen 中文字符长度
     * @return string
     */
    function strcut($str, $len, $ext = "", $zhLen = 0)
    {
        $count = 0;
        $output = "";
        preg_match_all("/./us", $str, $match);
        foreach ($match[0] as $v) {
            $vLen = strlen($v);
            $count += ($zhLen == 0) ? $vLen : $zhLen;
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
}
// ------------------------------------------------------------------------
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        return (substr($haystack, -$length) === $needle);
    }
}
// ------------------------------------------------------------------------
if (!function_exists('str2hex')) {
    /**
     * 字符串转16进制
     * @param $str
     * @return string
     */
    function str2hex($str)
    {
        $hex = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $ord = ord($str[$i]);
            $hexCode = dechex($ord);
            $hex .= substr('0' . $hexCode, -2);
        }
        return $hex;
    }
}
// ------------------------------------------------------------------------
if (!function_exists('hex2str')) {
    /**
     * 16进制转字符串
     * @param $hex
     * @return string
     */
    function hex2str($hex)
    {
        $string = '';
        for ($i = 0; $i < strlen( $hex ) - 1; $i += 2) {
            $string .= chr(hexdec($hex[$i] . $hex[$i + 1]));
        }
        return $string;
    }
}
// ------------------------------------------------------------------------
if (!function_exists('human_date')) {
    // 转换为人性化时间
    function human_date($dateline, $dateformat = 'Y-m-d H:i:s')
    {
        if (!is_numeric($dateline)) {
            return $dateline;
        }

        $second = PLUME_CURRENT_TIME - $dateline;
        if ($second > 31536000) {
            return date($dateformat, $dateline);
        } elseif($second > 2592000) {
            return floor($second / 2592000).'月前';
        } elseif($second > 86400) {
            return floor($second / 86400).'天前';
        } elseif($second > 3600) {
            return floor($second / 3600).'小时前';
        } elseif($second > 60) {
            return floor($second / 60).'分钟前';
        } else {
            return $second.'秒前';
        }
    }
}
// ------------------------------------------------------------------------
if (!function_exists('print_stack_trace')) {
    // 输出调用堆栈
    function print_stack_trace($isEcho = true)
    {
        $array = debug_backtrace();
        $html = '';
        foreach ($array as $row) {
            if (isset($row['file']) && isset($row['line']) && isset($row['function'])) {
                $html .= '<p>'.$row['file'].':'.$row['line'].'行,调用方法:'.$row['function']."</p>";
            }
        }

        if ($isEcho) {
            echo $html;
        } else {
            return $html;
        }
    }
}
// ------------------------------------------------------------------------
if (!function_exists('dump')) {
    /**
     * 任意多个变量的调试输出
     * @param mixed [$var1,$var2,$var3,...]
     */
    function dump()
    {
        if (!IS_CLI) echo '<pre style="font-size:12px; color:#0000FF">'.PHP_EOL;
        $vars = func_get_args();
        foreach ($vars as $var) {
            if (is_array($var)) {
                print_r($var);
            } else if(is_object($var)) {
                echo get_class($var)." Object";
            } else if(is_resource($var)) {
                echo (string)$var;
            } else {
                echo var_dump($var);
            }
        }
        if (!IS_CLI) echo '</pre>'.PHP_EOL;
    }
}
// ------------------------------------------------------------------------
if (!function_exists('dump_with_exit')) {
    /**
     * 与 dump() 方法一样,但会终于程序
     * @param mixed [$var1,$var2,$var3,...]
     */
    function dump_with_exit()
    {
        foreach (func_get_args() as $var) {
            dump($var);
        }
        exit;
    }
}
// ------------------------------------------------------------------------
if (!function_exists('fetch_from_array')) {
    function fetch_from_array(&$array, $index = null, $default = null)
    {
        if (is_null($index)) {
            return $array;
        } elseif (isset($array[$index])) {
            return $array[$index];
        } elseif (strpos($index, '/')) {
            $keys = explode('/', $index);
            switch(count($keys)) {
            case 1:
                if (isset($array[$keys[0]])) {
                    return $array[$keys[0]];
                }
                break;
            case 2:
                if (isset($array[$keys[0]][$keys[1]])) {
                    return $array[$keys[0]][$keys[1]];
                }
                break;
            case 3:
                if (isset($array[$keys[0]][$keys[1]][$keys[2]])) {
                    return $array[$keys[0]][$keys[1]][$keys[2]];
                }
                break;
            case 4:
                if (isset($array[$keys[0]][$keys[1]][$keys[2]][$keys[3]])) {
                    return $array[$keys[0]][$keys[1]][$keys[2]][$keys[3]];
                }
                break;
            }
        }
        return $default;
    }
}
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
        curl_setopt($ch, CURLOPT_USERAGENT, '');
        curl_setopt($ch, CURLOPT_REFERER, '');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (substr($url, 0, 5) === 'https') {
            // 信任任何证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // 检查证书中是否设置域名
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

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
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 设置Header信息
        !is_array($headers) && $headers = [];
        $headers[] = 'Expect:';
        // disable 100-continue
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if (substr($url, 0, 5) === 'https') {
            // 信任任何证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // 检查证书中是否设置域名
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

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
     * @param array $data
     */
    public static function postJson($url, $data, $timeout = 10)
    {
        if (empty($url)) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','charset=utf-8']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, 1);
        if (substr($url, 0, 5) === 'https') {
            // 信任任何证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // 检查证书中是否设置域名
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $startTime = microtime(true);
        $result = curl_exec($ch);
        self::$cost = round(microtime(true) - $startTime, 3);
        self::$errno = curl_errno($ch);
        self::$error = curl_error($ch);
        self::$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $result;
    }
}
