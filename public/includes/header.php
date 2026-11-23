<?php
ob_start();
require_once __DIR__ . '/ui.php';
$user = current_user();
$currentPage = $_GET['page'] ?? 'pages/home';
$cssVer = @filemtime(__DIR__ . '/../css/style.css') ?: 2;
$jsVer  = @filemtime(__DIR__ . '/../js/app.js') ?: 2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>VulnForum</title>
  <link rel="stylesheet" href="/css/style.css?v=<?= $cssVer ?>">
  <script src="/js/app.js?v=<?= $jsVer ?>"></script>
</head>
<body>
<header class="site-header">
  <nav class="nav-inner" aria-label="Main navigation">
    <a class="brand" href="/?page=pages/home">
      <span class="brand-mark" aria-hidden="true">
        <?= vf_icon('shield', 18) ?>
      </span>
      <span>VulnForum</span>
    </a>

    <form class="nav-search" method="get" action="/index.php" role="search">
      <input type="hidden" name="page" value="pages/search">
      <?= vf_icon('search', 15, 'search-icon') ?>
      <input name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search discussions..." aria-label="Search discussions">
    </form>

    <div class="nav-links">
      <a href="/?page=pages/home" class="<?= $currentPage === 'pages/home' ? 'active' : '' ?>">
        <?= vf_icon('message', 15) ?>
        <span>Threads</span>
      </a>
      <a href="/?page=pages/search" class="<?= $currentPage === 'pages/search' ? 'active' : '' ?>">
        <?= vf_icon('search', 15) ?>
        <span>Search</span>
      </a>
      <?php if ($user): ?>
        <a href="/?page=pages/new_thread" class="<?= $currentPage === 'pages/new_thread' ? 'active' : '' ?>">
          <?= vf_icon('plus', 15) ?>
          <span>New Post</span>
        </a>
        <?php if ($user['role'] === 'admin'): ?>
          <a href="/admin/users.php" class="nav-admin">
            <?= vf_icon('shield', 15) ?>
            <span>Admin</span>
          </a>
        <?php endif; ?>
        <span class="nav-divider" aria-hidden="true"></span>
        <a href="/?page=pages/profile" class="user-pill <?= $currentPage === 'pages/profile' ? 'active' : '' ?>">
          <?= vf_avatar($user['username'], 'sm', $user['avatar'] ?? null) ?>
          <span><?= htmlspecialchars($user['username']) ?></span>
        </a>
        <a href="/?page=pages/logout" class="icon-btn" title="Logout" aria-label="Logout">
          <?= vf_icon('logout', 16) ?>
        </a>
      <?php else: ?>
        <span class="nav-divider" aria-hidden="true"></span>
        <a href="/?page=pages/login" class="<?= $currentPage === 'pages/login' ? 'active' : '' ?>">Sign in</a>
        <a href="/?page=pages/register" class="btn btn-primary btn-sm">Sign up</a>
      <?php endif; ?>

      <button type="button" id="theme-toggle" class="icon-btn" title="Toggle theme" aria-label="Toggle theme">
        <?= vf_icon('sun', 16, 'icon-sun') ?>
        <?= vf_icon('moon', 16, 'icon-moon', 'display:none;') ?>
      </button>
    </div>
  </nav>
</header>
<main class="container">
