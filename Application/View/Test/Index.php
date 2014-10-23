<?php
$data = array('title' => '测试代码', 'body_class' => 'bs-docs-home');
View::tplInclude('Public/header', $data);
?>
<div class="bs-masthead" id="content">
  <div class="container">
    <p class="lead">单文件PHP框架，羽量级网站开发首选</p>
    <a href="index2.php?c=test&a=openid">click me</a>
  </div>
</div>
<?php View::tplInclude('Public/footer'); ?>