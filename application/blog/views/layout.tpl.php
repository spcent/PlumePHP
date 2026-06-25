<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PlumePHP 博客</title>
<link rel="stylesheet" href="/static/blog.css">
</head>
<body>
<nav class="navbar">
  <a href="/blog/post" class="brand">PlumePHP Blog</a>
  <a href="/blog/post/create" class="btn-new">写文章</a>
</nav>
<main class="container">
  <?php echo $__content__; ?>
</main>
<footer><p>Powered by PlumePHP</p></footer>
<?php foreach ($js_files as $js): ?>
<script src="<?= htmlspecialchars($js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
