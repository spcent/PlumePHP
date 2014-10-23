<?php if ( ! defined('PLUME_PHP_PATH')) exit('No direct script access allowed');

return array(
    'DB_HOST'     => '127.0.0.1',       #数据库主机地址
    'DB_PORT'     => '3306',            #数据库端口，默认为3306
    'DB_USER'     => 'root',            #数据库用户名
    'DB_PWD'      => '',                #数据库密码
    'DB_NAME'     => '',                #数据库名
    'DB_PREFIX'	  => '',
    'PATH_MOD'    => 'PATHINFO',		#路由方式，支持NORMAL和PATHINFO，默认NORMAL
);