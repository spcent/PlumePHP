-- PlumePHP 示例场景建表脚本
-- 执行: mysql -u root -p your_db < scripts/schema.sql

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(30)  NOT NULL UNIQUE,
  `email`         VARCHAR(120) NOT NULL UNIQUE,
  `password`      VARCHAR(255) NOT NULL,
  `status`        TINYINT      NOT NULL DEFAULT 1 COMMENT '1正常 0禁用 -1已删除',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME     NULL,
  INDEX idx_status (`status`),
  INDEX idx_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(30)  NOT NULL UNIQUE,
  `password`      VARCHAR(255) NOT NULL,
  `role`          VARCHAR(30)  NOT NULL DEFAULT 'admin',
  `last_login_at` DATETIME     NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(200) NOT NULL,
  `summary`     VARCHAR(500) NOT NULL DEFAULT '',
  `content`     MEDIUMTEXT   NOT NULL,
  `author_id`   INT UNSIGNED NOT NULL,
  `author_name` VARCHAR(30)  NOT NULL,
  `status`      TINYINT      NOT NULL DEFAULT 1 COMMENT '1已发布 0草稿 -1已删除',
  `view_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status_created (`status`, `created_at`),
  INDEX idx_author (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='博客文章';

CREATE TABLE IF NOT EXISTS `blog_comments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `post_id`     INT UNSIGNED NOT NULL,
  `author_name` VARCHAR(30)  NOT NULL,
  `content`     TEXT         NOT NULL,
  `ip`          VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章评论';

CREATE TABLE IF NOT EXISTS `articles` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title`      VARCHAR(200) NOT NULL,
  `content`    MEDIUMTEXT   NOT NULL,
  `author_id`  INT UNSIGNED NOT NULL,
  `status`     TINYINT      NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_author (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API文章资源';

-- 初始管理员账号（密码: admin123）
INSERT IGNORE INTO `admins` (`username`, `password`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
