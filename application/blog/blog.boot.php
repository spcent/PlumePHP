<?php

PlumePHP::app()->path(PLUME_PHP_PATH . DS . 'library' . DS . 'core');

abstract class blog_base_action extends \Plume\Libs\Action
{
    protected $csrfValidate = true;
    protected $requireLogin = false;

    public function init()
    {
        if ($this->requireLogin && empty($_SESSION['user_id'])) {
            if (IS_AJAX) {
                $this->error('请先登录', 401, true);
            }
            redirect('/blog/auth/login');
        }
        return true;
    }

    public function execute()
    {
        if ($this->init()) {
            return $this->invoke();
        }
    }

    abstract public function invoke();

    protected function currentUser(): array
    {
        return [
            'id'   => $_SESSION['user_id'] ?? 0,
            'name' => $_SESSION['user_name'] ?? '',
        ];
    }
}
