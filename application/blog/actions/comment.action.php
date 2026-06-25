<?php

/**
 * 评论提交（JSON API，AJAX Only）
 * POST /blog/comment
 */
class blog_comment_action extends blog_base_action
{
    protected array $rules = [
        'post_id'     => 'required|int|min:1',
        'author_name' => 'required|string|minLen:2|maxLen:30',
        'content'     => 'required|string|minLen:5|maxLen:1000',
    ];

    public function invoke()
    {
        if (!IS_POST) {
            $this->error('Method Not Allowed', 405, true);
        }

        $postId     = (int) $this->getParam('post_id');
        $authorName = html_filter($this->getParam('author_name', ''));
        $content    = html_filter($this->getParam('content', ''));

        $db   = DB();
        $post = $db->get('blog_posts', ['id'], ['id' => $postId, 'status' => 1]);
        if (!$post) {
            $this->error('文章不存在', 404, true);
        }

        $id = $db->insert('blog_comments', [
            'post_id'     => $postId,
            'author_name' => $authorName,
            'content'     => $content,
            'ip'          => get_client_ip(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->correct([
            'id'         => $id,
            'author'     => $authorName,
            'content'    => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ], '评论成功');
    }
}
