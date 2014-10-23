<?php if ( ! defined('PLUME_PHP_PATH')) exit('No direct script access allowed');

class TestController extends Controller {
    public function IndexAction() {
        $this->display();
    }
    
    public function DbAction() {
        // 获取数据库对象，前提是在入口文件配好数据库相关的配置
        $db = M();
        // 转义字符
        $name = $db->escape($_GET['name']);
        // 查询，失败返回false，否则返回数据
        $ret = $db->query("select * from employee where first_name = '$name'");
        var_dump($ret);
        // 获得返回的行数
        echo $db->getRows();
        // 获得上一次执行的sql
        echo $db->getLastSql();
    }
}