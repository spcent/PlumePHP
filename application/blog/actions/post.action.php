<?php

/**
 * 文章列表 & 详情
 * GET /blog/post          → 列表（带分页）
 * GET /blog/post?id=1     → 文章详情
 */
class blog_post_action extends blog_base_action
{
    protected $csrfValidate = false;

    public function invoke()
    {
        $id = (int) $this->getParam('id', 0);

        if ($id > 0) {
            $this->showDetail($id);
        } else {
            $this->showList();
        }
    }

    private function showList(): void
    {
        $page    = max(1, (int) $this->getParam('page', 1));
        $perPage = 10;
        $offset  = ($page - 1) * $perPage;

        $db    = DB();
        $total = $db->count('blog_posts', ['status' => 1]);
        $posts = $db->select('blog_posts', [
            'id', 'title', 'summary', 'author_name', 'created_at', 'view_count',
        ], [
            'status'  => 1,
            'ORDER'   => ['created_at' => 'DESC'],
            'LIMIT'   => [$offset, $perPage],
        ]);

        $this->assign('posts',     $posts ?: []);
        $this->assign('page',      $page);
        $this->assign('totalPage', (int) ceil($total / $perPage));
        $this->render('post/list', 'layout');
    }

    private function showDetail(int $id): void
    {
        $db   = DB();
        $post = $db->get('blog_posts', '*', ['id' => $id, 'status' => 1]);

        if (!$post) {
            $this->error('文章不存在', 404);
        }

        // 异步更新浏览量（不影响响应速度）
        $db->update('blog_posts', ['view_count[+]' => 1], ['id' => $id]);

        $comments = $db->select('blog_comments', [
            'id', 'author_name', 'content', 'created_at',
        ], [
            'post_id' => $id,
            'ORDER'   => ['created_at' => 'ASC'],
            'LIMIT'   => 50,
        ]);

        $this->assign('post',     $post);
        $this->assign('comments', $comments ?: []);
        $this->render('post/detail', 'layout');
    }
}
