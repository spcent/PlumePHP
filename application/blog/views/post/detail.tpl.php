<article class="post-full">
  <h1><?= htmlspecialchars($post['title']) ?></h1>
  <p class="meta">
    <?= htmlspecialchars($post['author_name']) ?>
    &middot; <?= human_date($post['created_at']) ?>
    &middot; <?= (int)$post['view_count'] ?> 阅读
  </p>
  <div class="content"><?= $post['content'] ?></div>
</article>

<section class="comments">
  <h3>评论（<?= count($comments) ?>）</h3>

  <?php foreach ($comments as $c): ?>
  <div class="comment">
    <strong><?= htmlspecialchars($c['author_name']) ?></strong>
    <span class="time"><?= human_date($c['created_at']) ?></span>
    <p><?= htmlspecialchars($c['content']) ?></p>
  </div>
  <?php endforeach; ?>

  <form id="comment-form" class="comment-form">
    <?= $csrfField ?? '' ?>
    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
    <input name="author_name" placeholder="您的昵称" required>
    <textarea name="content" rows="4" placeholder="说点什么..." required></textarea>
    <button type="submit">发布评论</button>
  </form>
</section>

<script>
document.getElementById('comment-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const form = new FormData(this);
  const res  = await fetch('/blog/comment', { method: 'POST', body: form });
  const json = await res.json();
  if (json.code === 0) {
    location.reload();
  } else {
    alert(json.msg);
  }
});
</script>
