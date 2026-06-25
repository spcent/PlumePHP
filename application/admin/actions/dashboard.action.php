<?php

/**
 * 管理后台首页：数据看板
 * GET /admin/dashboard
 */
class admin_dashboard_action extends admin_base_action
{
    protected $csrfValidate = false;

    public function invoke()
    {
        $db    = DB();
        $today = date('Y-m-d');
        $week  = date('Y-m-d', strtotime('-7 days'));

        // 多项统计并行查询（Medoo 本身不支持 async，但写法整洁）
        $stats = [
            'total_users'    => $db->count('users', ['status' => 1]),
            'new_users_week' => $db->count('users', ['created_at[>=]' => $week]),
            'total_posts'    => $db->count('blog_posts', ['status' => 1]),
            'new_posts_today'=> $db->count('blog_posts', ['created_at[>=]' => $today . ' 00:00:00']),
            'total_comments' => $db->count('blog_comments'),
        ];

        // 近7天每日新用户趋势（用原生 SQL）
        $trend = $db->query(
            "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
               FROM users
              WHERE created_at >= :week
           GROUP BY DATE(created_at)
           ORDER BY day ASC",
            [':week' => $week]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $this->assign('stats', $stats);
        $this->assign('trend', $trend);
        $this->assign('admin', $this->adminUser());
        $this->render('dashboard/index', 'layout');
    }
}
