<?php if (!defined('PLUME_PHP_PATH')) exit('No direct script access allowed');

/**
 * abstract class for wx
 */
abstract class web_base_action extends \Plume\Libs\Action {
    /**
     * csrf验证
     * @var bool
     */
    protected $csrfValidate = false;
    public function init() {return true;}
    /**
     * execute方法
     */
    public function execute() {
        if ($this->init()) {
            return $this->invoke();
        }
    }

    /**
     * Execute the action
     *
     * @abstract
     * @access public
     * @return bool True if the action was executed or false when not executed
     */
    abstract public function invoke();
}

class web_base_cmd {
    /**
     * 日志记录
     */
    protected function output($msg, $isEcho = true) {
        if ($isEcho) {
            echo $msg, PHP_EOL;
        }

        PlumeLog::info($msg);
    }
}