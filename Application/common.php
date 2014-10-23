<?php if ( ! defined('PLUME_PHP_PATH')) exit('No direct script access allowed');

// 基于curl的file_get_contents
function curl_get_contents(
        $url, 
        array $post_data = array(),
        $verbose = false,
        $ref_url = false,
        $cookie_location = false,
        $return_transfer = true) {
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

/**
 * URL重定向
 * @param string $url 重定向的URL地址
 * @param integer $time 重定向的等待时间（秒）
 * @param string $msg 重定向前的提示信息
 * @return void
 */
function redirect($url, $time = 0, $msg = '') {
    //多行URL地址支持
    $url = str_replace(array("\n", "\r"), '', $url);
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

// 判断是否是在微信浏览器里
function is_weixin_browser() {
    $agent = $_SERVER ['HTTP_USER_AGENT'];
    if (strpos($agent, 'MicroMessenger') !== false
        || strpos($agent, 'icroMessenger') !== false
        || strpos($agent, 'Windows Phone' !== false)) {
        return true;
    }
    
    return false;
}

// php获取当前访问的完整url地址
function get_current_url() {
    $url = 'http://';
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
        $url = 'https://';
    }
    if ($_SERVER['SERVER_PORT'] != '80') {
        $url .= $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
    } else {
        $url .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    // 兼容后面的参数组装
    if (stripos($url, '?') === false) {
        $url .= '?t=' . time();
    }
    return $url;
}

// 生成随机字符串
function generate_nonce_str($length = 16) {
    // 密码字符集，可任意添加你需要的字符
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    $length = strlen($chars);
    for($i = 0; $i < $length; $i++) {
        $str .= $chars[mt_rand(0,  $length - 1)];
    }
    return $str;
}


