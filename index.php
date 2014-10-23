<?php
/*
 *---------------------------------------------------------------
 * 应用程序环境
 *---------------------------------------------------------------
 *
 * 应用程序在不同的环境下，将加载不同的配置文件。默认在不同的环境
 * 的错误提示级别也不一样
 *
 * 可以设置成任何值, 以下是默认值:
 *
 *     development（开发）
 *     testing（测试）
 *     production（生产）
 *
 */
define('ENVIRONMENT', 'development');
include __DIR__.'/Application/PlumePHP.class.php';

// 这里可以动态增加全局配置信息
$config = array('APP_PATH' => 'Application');
PlumePHP::getInstance($config)->run();