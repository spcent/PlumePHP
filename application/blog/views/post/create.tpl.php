<div class="editor-page">
  <h1>写新文章</h1>
  <form method="POST" action="/blog/post/create">
    <?= $csrfField ?? '' ?>
    <div class="field">
      <label>标题</label>
      <input name="title" maxlength="200" placeholder="给文章起个好标题" required>
    </div>
    <div class="field">
      <label>摘要（可选，留空自动截取）</label>
      <input name="summary" maxlength="500" placeholder="一句话描述文章内容">
    </div>
    <div class="field">
      <label>正文</label>
      <textarea name="content" rows="20" placeholder="开始写作..." required></textarea>
    </div>
    <button type="submit" class="btn-primary">发布文章</button>
    <a href="/blog/post" class="btn-cancel">取消</a>
  </form>
</div>
