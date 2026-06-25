<?php

PlumePHP::app()->path(PLUME_PHP_PATH . DS . 'library' . DS . 'core');

abstract class admin_base_action extends \Plume\Libs\Action
{
    protected $csrfValidate = true;
    protected $requireAdmin = true;

    public function init(): bool
    {
        if (!$this->requireAdmin) {
            return true;
        }

        if (empty($_SESSION['admin_id'])) {
            if (IS_AJAX) {
                $this->error('请先登录', 401, true);
            }
            redirect('/admin/auth/login');
        }

        // 超级简单的角色检查
        if (empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
            $this->error('权限不足', 403, IS_AJAX);
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

    protected function adminUser(): array
    {
        return [
            'id'   => $_SESSION['admin_id']   ?? 0,
            'name' => $_SESSION['admin_name'] ?? '',
        ];
    }
}

class admin_base_cmd
{
    protected function log(string $msg): void
    {
        echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
        L($msg, [], 'INFO');
    }
}
