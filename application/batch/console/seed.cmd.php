<?php

/**
 * 数据库播种命令 —— 用于开发环境快速初始化演示数据
 *
 * 用法:
 *   php public/index.php -m batch -c seed
 *   php public/index.php -m batch -c seed --truncate   # 先清空再插入
 *   php public/index.php -m batch -c seed --count=50   # 指定生成条数
 */
class batch_seed_cmd extends batch_base_cmd
{
    public function run(array $opts): void
    {
        $truncate = isset($opts['truncate']);
        $count    = max(1, (int)($opts['count'] ?? 20));

        if ($truncate && !$this->confirm("将清空 users/blog_posts 表，确认？")) {
            $this->log('已取消', 'WARN');
            return;
        }

        $db = DB();

        if ($truncate) {
            $db->query('TRUNCATE TABLE blog_comments');
            $db->query('TRUNCATE TABLE blog_posts');
            $db->query('TRUNCATE TABLE users');
            $this->log('表已清空');
        }

        // 生成用户
        $this->log("开始生成 {$count} 个用户...");
        $userIds = [];
        for ($i = 1; $i <= $count; $i++) {
            $uid = $db->insert('users', [
                'username'   => 'user_' . generate_nonce_str(6),
                'email'      => 'user' . $i . '_' . generate_nonce_str(4) . '@example.com',
                'password'   => password_hash('password123', PASSWORD_BCRYPT),
                'status'     => 1,
                'created_at' => date('Y-m-d H:i:s', time() - rand(0, 86400 * 30)),
            ]);
            $userIds[] = $uid;
        }
        $this->log("用户生成完成: " . count($userIds) . " 条");

        // 生成博客文章
        $this->log("开始生成 {$count} 篇博客文章...");
        $postIds = [];
        $topics  = ['PHP开发', 'MySQL优化', 'Linux运维', 'Nginx配置', '设计模式', '前端实践'];
        foreach ($userIds as $uid) {
            $topic  = $topics[array_rand($topics)];
            $title  = "{$topic}：第" . rand(1, 100) . "篇实战笔记";
            $postId = $db->insert('blog_posts', [
                'title'       => $title,
                'content'     => "这是关于 {$topic} 的一篇详细文章，内容充实，涵盖了从基础到进阶的各个方面...\n\n" . str_repeat("示例段落内容。", rand(3, 10)),
                'summary'     => "一篇关于 {$topic} 的实战笔记",
                'author_id'   => $uid,
                'author_name' => 'user_' . $uid,
                'status'      => 1,
                'view_count'  => rand(0, 5000),
                'created_at'  => date('Y-m-d H:i:s', time() - rand(0, 86400 * 60)),
            ]);
            $postIds[] = $postId;
        }
        $this->log("文章生成完成: " . count($postIds) . " 条");

        // 生成评论
        $this->log("开始生成评论...");
        $commentCount = 0;
        foreach ($postIds as $postId) {
            $n = rand(0, 5);
            for ($j = 0; $j < $n; $j++) {
                $db->insert('blog_comments', [
                    'post_id'     => $postId,
                    'author_name' => '访客' . generate_nonce_str(4),
                    'content'     => '写得不错，学到了！' . generate_nonce_str(8),
                    'ip'          => rand(1, 255) . '.' . rand(0, 255) . '.0.1',
                    'created_at'  => date('Y-m-d H:i:s', time() - rand(0, 86400 * 30)),
                ]);
                $commentCount++;
            }
        }
        $this->log("评论生成完成: {$commentCount} 条");

        $this->log('全部播种完成！');
    }
}
