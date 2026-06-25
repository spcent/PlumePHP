<?php

/**
 * 过期数据清理命令
 *
 * 用法:
 *   php public/index.php -m batch -c cleanup
 *   php public/index.php -m batch -c cleanup --days=90  # 清理90天前的日志（默认30）
 *   php public/index.php -m batch -c cleanup --dry-run  # 只统计，不实际删除
 *
 * crontab 示例（每天凌晨3点自动执行）:
 *   0 3 * * * php /var/www/public/index.php -m batch -c cleanup --days=30
 */
class batch_cleanup_cmd extends batch_base_cmd
{
    public function run(array $opts): void
    {
        $days   = max(7, (int)($opts['days'] ?? 30));
        $dryRun = isset($opts['dry-run']) || isset($opts['dry_run']);
        $before = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $this->log($dryRun ? "[DRY-RUN] 模拟清理，不会实际删除数据" : "开始清理 {$days} 天前数据（早于 {$before}）");

        $db = DB();

        // 1. 清理被删文章下的孤儿评论
        $orphanCount = $db->count('blog_comments', [
            'post_id' => $db->select('blog_posts', 'id', ['status' => -1]),
        ]);
        $this->log("孤儿评论（已删文章的评论）: {$orphanCount} 条");
        if (!$dryRun && $orphanCount > 0) {
            $db->delete('blog_comments', [
                'post_id' => $db->select('blog_posts', 'id', ['status' => -1]),
            ]);
            $this->log("孤儿评论删除完成");
        }

        // 2. 清理 N 天前的旧日志文件
        $logDir  = LOG_PATH;
        $cleaned = 0;
        foreach (glob($logDir . DS . '*.log*') as $file) {
            if (filemtime($file) < strtotime("-{$days} days")) {
                $this->log("日志文件: " . basename($file));
                if (!$dryRun) {
                    unlink($file);
                }
                $cleaned++;
            }
        }
        $this->log($dryRun ? "可清理日志文件: {$cleaned} 个" : "日志文件清理完成: {$cleaned} 个");

        // 3. 清理长期未登录的禁用账号（超过 N 天未登录 + 状态为禁用）
        $staleCount = $db->count('users', [
            'status'            => 0,
            'last_login_at[<]'  => $before,
        ]);
        $this->log("长期禁用账号: {$staleCount} 条");
        if (!$dryRun && $staleCount > 0) {
            $db->update('users', ['status' => -1], [
                'status'           => 0,
                'last_login_at[<]' => $before,
            ]);
            $this->log("已将 {$staleCount} 个长期禁用账号标记为已删除");
        }

        L("数据清理完成: orphan={$orphanCount} logs={$cleaned} stale={$staleCount} dry={$dryRun}", [], 'INFO');
        $this->log($dryRun ? '[DRY-RUN] 模拟结束，未做任何修改' : '清理完成！');
    }
}
