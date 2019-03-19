<?php
/**
 * 阿里云短信接口
 * @link      http://www.phpgrace.com
 * @copyright Copyright (c) 2010-2015 phpWit.
 * @license   http://www.phpgrace.com/license
 * @package   phpWit/tools
 * @author    haijun liu mail:5213606@qq.com
 * @version   1.1 Beta
 */
namespace Plume\Libs;

class AliPhoneMsg {
	// accessKeyId 阿里账户中心获取
	private $accessKeyId     = "******";
	// accessKeySecret 阿里账户中心获取
	private $accessKeySecret = "******";

	/**
	 * 发送短信
	 * @param $phoneNumber 接收手机号
	 * @param $SignName    短信签名 阿里云管理中心【短信服务】获取，如: "阿里云短信测试专用"
	 * @param $TemplateCode 短信模板 id 阿里云管理中心【短信服务】获取
	 * @param $TemplateParam 模板变量，数组形式根据模板信息设置
	 * 
	 */
	public function sendMsg($phoneNumber, $SignName, $TemplateCode, $TemplateParam) {
		$params = array ();
		$params["PhoneNumbers"] = $phoneNumber;
	    $params["SignName"] = $SignName;
		$params["TemplateCode"] = $TemplateCode;
	    $params['TemplateParam'] = $TemplateParam;
	    if(!empty($params["TemplateParam"]) && is_array($params["TemplateParam"])) {
	        $params["TemplateParam"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
        }

	    return $this->request(
	        $this->accessKeyId,
	        $this->accessKeySecret,
	        "dysmsapi.aliyuncs.com",
	        array_merge($params, array(
	            "RegionId" => "cn-hangzhou",
	            "Action" => "SendSms",
	            "Version" => "2017-05-25",
	        ))
	    );
	}
	
    public function request($accessKeyId, $accessKeySecret, $domain, $params, $security = false){
        $apiParams = array_merge(array (
            "SignatureMethod" => "HMAC-SHA1",
            "SignatureNonce" => uniqid(mt_rand(0,0xffff), true),
            "SignatureVersion" => "1.0",
            "AccessKeyId" => $accessKeyId,
            "Timestamp" => gmdate("Y-m-d\TH:i:s\Z"),
            "Format" => "JSON",
        ), $params);
        ksort($apiParams);
        $sortedQueryStringTmp = "";
        foreach ($apiParams as $key => $value) {
            $sortedQueryStringTmp .= "&" . $this->encode($key) . "=" . $this->encode($value);
        }
        $stringToSign = "GET&%2F&" . $this->encode(substr($sortedQueryStringTmp, 1));
        $sign = base64_encode(hash_hmac("sha1", $stringToSign, $accessKeySecret . "&",true));
        $signature = $this->encode($sign);
        $url = ($security ? 'https' : 'http')."://{$domain}/?Signature={$signature}{$sortedQueryStringTmp}";
        try {
            $content = $this->fetchContent($url);
            return json_decode($content);
        } catch( \Exception $e) {
            return false;
        }
    }

    private function encode($str){
        $res = urlencode($str);
        $res = preg_replace("/\+/", "%20", $res);
        $res = preg_replace("/\*/", "%2A", $res);
        $res = preg_replace("/%7E/", "~", $res);
        return $res;
    }

    private function fetchContent($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "x-sdk-client" => "php/2.0.0"
        ));
        if(substr($url, 0,5) == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $rtn = curl_exec($ch);
        if($rtn === false) {
            trigger_error("[CURL_" . curl_errno($ch) . "]: " . curl_error($ch), E_USER_ERROR);
        }
        curl_close($ch);
        return $rtn;
    }
}