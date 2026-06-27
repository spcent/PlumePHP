<?php

/**
 * 后台登录/登出
 * GET|POST /admin/auth/login   → 登录页
 * GET      /admin/auth/logout  → 登出
 */
class admin_auth_action extends admin_base_action
{
    protected $requireAdmin = false;
    protected $csrfValidate = false;

    public function invoke()
    {
        $sub = $this->getParam('action', 'login');

        if ($sub === 'logout') {
            session_destroy();
            redirect('/admin/auth/login');
        }

        if (IS_POST) {
            $this->doLogin();
        } else {
            $this->assign('error', '');
            $this->render('auth/login', 'auth_layout');
        }
    }

    private function doLogin(): void
    {
        $username = $this->getParam('username', '');
        $password = $this->getParam('password', '');

        $db   = DB();
        $user = $db->get('admins', ['id', 'username', 'password', 'role'], ['username' => $username]);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->assign('error', '账号或密码错误');
            $this->render('auth/login', 'auth_layout');
            return;
        }

        $_SESSION['admin_id']   = $user['id'];
        $_SESSION['admin_name'] = $user['username'];
        $_SESSION['admin_role'] = $user['role'];

        $db->update('admins', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

        L("管理员登录: id={$user['id']} name={$user['username']}", ['ip' => get_client_ip()], 'INFO');

        redirect('/admin/dashboard');
    }
}
