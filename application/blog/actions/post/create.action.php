<?php

/**
 * 新建文章
 * GET  /blog/post/create  → 显示表单
 * POST /blog/post/create  → 保存
 */
class blog_post_create_action extends blog_base_action
{
    protected $requireLogin = true;

    // 声明式参数验证 —— 框架在 invoke() 前自动执行
    protected array $rules = [
        'title'   => 'required|string|minLen:4|maxLen:200',
        'content' => 'required|string|minLen:10',
        'summary' => 'string|maxLen:500',
    ];

    public function invoke()
    {
        if (IS_POST) {
            $this->save();
        } else {
            $this->render('post/create', 'layout');
        }
    }

    private function save(): void
    {
        $user    = $this->currentUser();
        $title   = html_filter($this->getParam('title', ''));
        $content = html_filter($this->getParam('content', ''));
        $summary = html_filter($this->getParam('summary', ''));

        $db = DB();
        $id = $db->insert('blog_posts', [
            'title'       => $title,
            'content'     => $content,
            'summary'     => $summary ?: strcut(strip_tags($content), 150, '...'),
            'author_id'   => $user['id'],
            'author_name' => $user['name'],
            'status'      => 1,
            'view_count'  => 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        L("新文章发布: id={$id} title={$title}", ['user_id' => $user['id']], 'INFO');

        redirect("/blog/post?id={$id}");
    }
}
