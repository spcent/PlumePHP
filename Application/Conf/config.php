<?php if ( ! defined('PLUME_PHP_PATH')) exit('No direct script access allowed');

return array(
    'DB_HOST'     => '',                #数据库主机地址
    'DB_PORT'     => '',                #数据库端口，默认为3306
    'DB_USER'     => '',                #数据库用户名
    'DB_PWD'      => '',                #数据库密码
    'DB_NAME'     => '',                #数据库名
    'DB_CHARSET'  => 'utf8',            #数据库编码，默认utf8
    'PATH_MOD'    => 'PATHINFO',        #路由方式，支持NORMAL和PATHINFO，默认NORMAL
    'USE_SESSION' => true,              #是否开启session，默认false
    'WEIXIN_APPID' => '',
    'WEIXIN_APPSECRET' => '',
);