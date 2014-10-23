<?php if ( ! defined('PLUME_PHP_PATH')) exit('No direct script access allowed');

class TestWidget extends Widget {
    public function invoke($data){
        $this->assign('data', $data);
        $this->display();
    }
}
