<?php

/**
 * 文章资源 REST API（需 Bearer Token）
 *
 * GET    /api/article         → 列表
 * GET    /api/article?id=1    → 详情
 * POST   /api/article/create  → 创建
 * POST   /api/article/update  → 更新（传 id）
 * POST   /api/article/delete  → 删除（传 id）
 */
class api_article_action extends api_base_action
{
    protected $requireAuth = true;

    // 创建/更新共用验证规则（validate() 里按方法选择）
    private array $writeRules = [
        'title'   => 'required|string|minLen:4|maxLen:200',
        'content' => 'required|string|minLen:10',
    ];

    public function invoke()
    {
        $sub = $this->getParam('action', '');

        match ($sub) {
            'create' => $this->create(),
            'update' => $this->update(),
            'delete' => $this->delete(),
            default  => $this->index(),
        };
    }

    private function index(): void
    {
        $id = (int) $this->getParam('id', 0);

        if ($id > 0) {
            $row = DB()->get('articles', '*', ['id' => $id]);
            $row ? $this->correct($row) : $this->error('Not Found', 404, true);
            return;
        }

        $page    = max(1, (int) $this->getParam('page', 1));
        $perPage = min(50, max(1, (int) $this->getParam('per_page', 20)));
        $offset  = ($page - 1) * $perPage;

        $db    = DB();
        $total = $db->count('articles', ['author_id' => $this->authUser['uid']]);
        $rows  = $db->select('articles', [
            'id', 'title', 'status', 'created_at', 'updated_at',
        ], [
            'author_id' => $this->authUser['uid'],
            'ORDER'     => ['created_at' => 'DESC'],
            'LIMIT'     => [$offset, $perPage],
        ]);

        $this->correct([
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'items'    => $rows ?: [],
        ]);
    }

    private function create(): void
    {
        $this->rules = $this->writeRules;
        $errors = $this->validate();
        if ($errors) {
            $this->error(reset($errors), 422, true);
        }

        $id = DB()->insert('articles', [
            'title'      => $this->getParam('title'),
            'content'    => $this->getParam('content'),
            'author_id'  => $this->authUser['uid'],
            'status'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->correct(['id' => $id], '创建成功');
    }

    private function update(): void
    {
        $id = (int) $this->getParam('id', 0);
        if (!$id) {
            $this->error('缺少 id', 422, true);
        }

        // 确保只能编辑自己的文章
        $db  = DB();
        $row = $db->get('articles', ['id'], ['id' => $id, 'author_id' => $this->authUser['uid']]);
        if (!$row) {
            $this->error('无权操作或文章不存在', 403, true);
        }

        $fields = array_filter([
            'title'      => $this->getParam('title'),
            'content'    => $this->getParam('content'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $db->update('articles', $fields, ['id' => $id]);
        $this->correct([], '更新成功');
    }

    private function delete(): void
    {
        $id = (int) $this->getParam('id', 0);
        if (!$id) {
            $this->error('缺少 id', 422, true);
        }

        $db  = DB();
        $row = $db->get('articles', ['id'], ['id' => $id, 'author_id' => $this->authUser['uid']]);
        if (!$row) {
            $this->error('无权操作或文章不存在', 403, true);
        }

        $db->delete('articles', ['id' => $id]);
        L("文章删除: id={$id}", ['uid' => $this->authUser['uid']], 'INFO');

        $this->correct([], '删除成功');
    }
}
