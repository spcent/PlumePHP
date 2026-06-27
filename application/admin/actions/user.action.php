<?php

/**
 * 用户管理
 * GET /admin/user             → 列表（支持 keyword/page 参数）
 * GET /admin/user?export=csv  → 导出 CSV
 * POST /admin/user            → 禁用/启用用户（传 id + status）
 */
class admin_user_action extends admin_base_action
{
    public function invoke()
    {
        if ($this->getParam('export') === 'csv') {
            $this->exportCsv();
            return;
        }

        if (IS_POST) {
            $this->toggleStatus();
            return;
        }

        $this->listUsers();
    }

    private function listUsers(): void
    {
        $keyword = trim($this->getParam('keyword', ''));
        $page    = max(1, (int) $this->getParam('page', 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $db    = DB();
        $where = ['status[!]' => -1];   // 排除已删除

        if ($keyword) {
            $where['OR'] = [
                'username[~]' => $keyword,
                'email[~]'    => $keyword,
            ];
        }

        $total = $db->count('users', $where);
        $users = $db->select('users', [
            'id', 'username', 'email', 'status', 'created_at', 'last_login_at',
        ], array_merge($where, [
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [$offset, $perPage],
        ]));

        $this->assign('users',     $users ?: []);
        $this->assign('keyword',   $keyword);
        $this->assign('page',      $page);
        $this->assign('totalPage', (int) ceil($total / $perPage));
        $this->assign('total',     $total);
        $this->render('user/list', 'layout');
    }

    private function toggleStatus(): void
    {
        $id     = (int) $this->getParam('id', 0);
        $status = (int) $this->getParam('status', 0);

        if (!$id || !in_array($status, [0, 1], true)) {
            $this->error('参数错误', 422, true);
        }

        DB()->update('users', ['status' => $status], ['id' => $id]);

        L("管理员修改用户状态: uid={$id} status={$status}", ['by' => $this->adminUser()['id']], 'INFO');

        $this->correct([], '操作成功');
    }

    private function exportCsv(): void
    {
        $db    = DB();
        $users = $db->select('users', ['id', 'username', 'email', 'status', 'created_at'], [
            'ORDER' => ['id' => 'ASC'],
        ]);

        $rows = [['ID', '用户名', '邮箱', '状态', '注册时间']];
        foreach ($users as $u) {
            $rows[] = [
                $u['id'],
                $u['username'],
                $u['email'],
                $u['status'] === 1 ? '正常' : '禁用',
                $u['created_at'],
            ];
        }

        export_csv('users_' . date('YmdHis') . '.csv', $rows);  // 自动下载并 exit
    }
}
