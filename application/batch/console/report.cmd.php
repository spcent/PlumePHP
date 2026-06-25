<?php

/**
 * 周报导出命令 —— 适合配合 crontab 自动执行
 *
 * 用法:
 *   php public/index.php -m batch -c report
 *   php public/index.php -m batch -c report --days=30    # 近30天（默认7）
 *   php public/index.php -m batch -c report --output=csv # 输出到 CSV 文件
 *
 * crontab 示例（每周一00:05执行）:
 *   5 0 * * 1 php /var/www/public/index.php -m batch -c report >> /var/log/plume/report.log 2>&1
 */
class batch_report_cmd extends batch_base_cmd
{
    public function run(array $opts): void
    {
        $days   = max(1, (int)($opts['days'] ?? 7));
        $output = $opts['output'] ?? 'console';
        $since  = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

        $this->log("生成近 {$days} 天报表（自 {$since}）");

        $db = DB();

        // 新增用户
        $newUsers = $db->count('users', ['created_at[>=]' => $since]);

        // 活跃用户（近期有登录记录）
        $activeUsers = $db->count('users', [
            'last_login_at[>=]' => $since,
            'status'            => 1,
        ]);

        // 发布文章
        $newPosts = $db->count('blog_posts', [
            'created_at[>=]' => $since,
            'status'         => 1,
        ]);

        // 评论数
        $newComments = $db->count('blog_comments', ['created_at[>=]' => $since]);

        // 每日明细
        $dailyDetail = $db->query(
            "SELECT
               DATE(created_at)              AS day,
               COUNT(*)                      AS new_users,
               SUM(last_login_at >= :since)  AS active
             FROM users
             WHERE created_at >= :since2
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            [':since' => $since, ':since2' => $since]
        )->fetchAll(\PDO::FETCH_ASSOC);

        // 阅读量 Top5 文章
        $topPosts = $db->select('blog_posts', ['id', 'title', 'view_count', 'author_name'], [
            'status' => 1,
            'ORDER'  => ['view_count' => 'DESC'],
            'LIMIT'  => 5,
        ]);

        if ($output === 'csv') {
            $this->saveAsCsv($days, $dailyDetail, $topPosts);
        } else {
            $this->printReport($days, $newUsers, $activeUsers, $newPosts, $newComments, $dailyDetail, $topPosts);
        }
    }

    private function printReport(
        int $days,
        int $newUsers,
        int $activeUsers,
        int $newPosts,
        int $newComments,
        array $daily,
        array $topPosts
    ): void {
        echo PHP_EOL;
        echo "========== 近 {$days} 天数据报表 ==========\n";
        echo "新增用户:   {$newUsers}\n";
        echo "活跃用户:   {$activeUsers}\n";
        echo "新增文章:   {$newPosts}\n";
        echo "新增评论:   {$newComments}\n";

        echo "\n--- 每日明细 ---\n";
        printf("%-12s %-10s %-10s\n", '日期', '新增用户', '活跃用户');
        foreach ($daily as $row) {
            printf("%-12s %-10d %-10d\n", $row['day'], $row['new_users'], $row['active']);
        }

        echo "\n--- 阅读量 Top5 ---\n";
        foreach ($topPosts as $i => $post) {
            printf("%d. [%5d 次] %s (%s)\n", $i + 1, $post['view_count'], $post['title'], $post['author_name']);
        }
        echo "==========================================\n";
    }

    private function saveAsCsv(int $days, array $daily, array $topPosts): void
    {
        $filename = 'report_' . date('Ymd') . "_{$days}d.csv";
        $path     = LOG_PATH . DS . $filename;

        $fp = fopen($path, 'w');
        fputs($fp, "\xEF\xBB\xBF");   // UTF-8 BOM，Excel 兼容

        fputcsv($fp, ['日期', '新增用户', '活跃用户']);
        foreach ($daily as $row) {
            fputcsv($fp, [$row['day'], $row['new_users'], $row['active']]);
        }

        fputcsv($fp, []);
        fputcsv($fp, ['排名', '文章标题', '作者', '阅读量']);
        foreach ($topPosts as $i => $post) {
            fputcsv($fp, [$i + 1, $post['title'], $post['author_name'], $post['view_count']]);
        }

        fclose($fp);
        $this->log("CSV 已保存: {$path}");
    }
}
