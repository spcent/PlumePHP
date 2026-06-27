<div class="post-list">
  <h1>最新文章</h1>

  <?php if (empty($posts)): ?>
    <p class="empty">还没有文章，<a href="/blog/post/create">来写第一篇吧</a></p>
  <?php else: ?>
    <?php foreach ($posts as $post): ?>
    <article class="post-card">
      <h2><a href="/blog/post?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
      <p class="meta">
        <?= htmlspecialchars($post['author_name']) ?>
        &middot; <?= human_date($post['created_at']) ?>
        &middot; <?= (int)$post['view_count'] ?> 阅读
      </p>
      <p class="summary"><?= htmlspecialchars($post['summary']) ?></p>
    </article>
    <?php endforeach; ?>

    <nav class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>">上一页</a>
      <?php endif; ?>
      <span>第 <?= $page ?> / <?= $totalPage ?> 页</span>
      <?php if ($page < $totalPage): ?>
        <a href="?page=<?= $page + 1 ?>">下一页</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
