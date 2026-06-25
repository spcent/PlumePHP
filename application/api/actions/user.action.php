<?php

/**
 * 用户 API
 *
 * POST /api/user/register  注册
 * POST /api/user/login     登录
 * GET  /api/user/profile   当前用户信息（需 Token）
 */
class api_user_action extends api_base_action
{
    protected $requireAuth = false;

    protected array $registerRules = [
        'username' => 'required|string|minLen:3|maxLen:30',
        'email'    => 'required|email',
        'password' => 'required|string|minLen:8|maxLen:64',
    ];

    public function invoke()
    {
        $controller = $this->getParam('action', '') ?: 'profile';

        // 手动路由到子方法
        match ($controller) {
            'register' => $this->register(),
            'login'    => $this->login(),
            'profile'  => $this->profile(),
            default    => $this->error('Not Found', 404, true),
        };
    }

    private function register(): void
    {
        $this->rules = $this->registerRules;
        $errors = $this->validate();
        if ($errors) {
            $this->error(reset($errors), 422, true);
        }

        $username = $this->getParam('username');
        $email    = $this->getParam('email');
        $password = $this->getParam('password');

        $db = DB();
        if ($db->has('users', ['OR' => ['username' => $username, 'email' => $email]])) {
            $this->error('用户名或邮箱已存在', 409, true);
        }

        $uid = $db->insert('users', [
            'username'   => $username,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_BCRYPT),
            'status'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $token = $this->makeToken((int) $uid, $username);

        L("用户注册: uid={$uid} username={$username}", [], 'INFO');

        $this->correct(['uid' => $uid, 'token' => $token], '注册成功');
    }

    private function login(): void
    {
        $login    = $this->getParam('login', '');    // username 或 email
        $password = $this->getParam('password', '');

        if (!$login || !$password) {
            $this->error('账号和密码不能为空', 422, true);
        }

        $db   = DB();
        $user = $db->get('users', ['id', 'username', 'password', 'status'], [
            'OR' => ['username' => $login, 'email' => $login],
        ]);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->error('账号或密码错误', 401, true);
        }

        if ($user['status'] !== 1) {
            $this->error('账号已被禁用', 403, true);
        }

        $db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

        $token = $this->makeToken((int) $user['id'], $user['username']);

        $this->correct([
            'uid'      => $user['id'],
            'username' => $user['username'],
            'token'    => $token,
        ], '登录成功');
    }

    private function profile(): void
    {
        // 需要 Token 鉴权，临时覆盖 requireAuth 并重新校验
        $this->requireAuth = true;
        if (!$this->init()) {
            return;
        }

        $uid  = $this->authUser['uid'];
        $db   = DB();
        $user = $db->get('users', ['id', 'username', 'email', 'created_at', 'last_login_at'], ['id' => $uid]);

        if (!$user) {
            $this->error('用户不存在', 404, true);
        }

        $this->correct($user);
    }
}
