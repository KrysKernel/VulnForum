<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';
require_admin();
$user = current_user();

$cssVer = @filemtime(__DIR__ . '/../css/style.css') ?: 2;
$jsVer  = @filemtime(__DIR__ . '/../js/app.js') ?: 2;

$output = '';
$code = $_POST['expr'] ?? '';
if ($code !== '') {
    // VULN: eval() on user-controlled input — this "template preview" tool
    // was meant to let admins test small PHP snippets for thread signatures,
    // but it hands anyone who reaches this page (and remember, require_admin()
    // trusts a session role that can be forged via several bugs elsewhere in
    // this app — see VULNERABILITIES.md) direct remote code execution.
    ob_start();
    eval($code);
    $output = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - Template Preview | VulnForum</title>
  <link rel="stylesheet" href="/css/style.css?v=<?= $cssVer ?>">
  <script src="/js/app.js?v=<?= $jsVer ?>"></script>
</head>
<body>
<header class="site-header">
  <nav class="nav-inner" aria-label="Admin navigation">
    <a class="brand" href="/?page=pages/home">
      <span class="brand-mark" aria-hidden="true">
        <?= vf_icon('shield', 18) ?>
      </span>
      <span>VulnForum</span>
      <span class="badge badge-admin">Admin</span>
    </a>
    <div class="nav-links">
      <a href="/?page=pages/home">&larr; Back to Forum</a>
      <a href="/admin/users.php" class="nav-admin">Users</a>
      <a href="/admin/eval.php" class="nav-admin active">Template Preview</a>
      <span class="nav-divider" aria-hidden="true"></span>
      <a href="/?page=pages/profile" class="user-pill">
        <?= vf_avatar($user['username'], 'sm', $user['avatar'] ?? null) ?>
        <span><?= htmlspecialchars($user['username']) ?></span>
      </a>
      <button type="button" id="theme-toggle" class="icon-btn" title="Toggle theme" aria-label="Toggle theme">
        <?= vf_icon('sun', 16, 'icon-sun') ?>
        <?= vf_icon('moon', 16, 'icon-moon', 'display:none;') ?>
      </button>
    </div>
  </nav>
</header>

<main class="container">
  <div class="page-header">
    <div>
      <h1>Template Preview Sandbox</h1>
      <p class="page-subtitle">Test dynamic PHP expressions for forum thread signatures</p>
    </div>
    <a href="/admin/users.php" class="btn btn-secondary btn-sm">&larr; Users Directory</a>
  </div>

  <form method="post">
    <div class="ide-window">
      <div class="ide-titlebar">
        <div class="ide-dots" aria-hidden="true"><span></span><span></span><span></span></div>
        <span class="ide-filename">signature-preview.php</span>
        <span class="badge badge-admin">eval()</span>
      </div>
      <textarea id="expr" class="ide-editor" name="expr" rows="6" placeholder="echo 'Hello ' . current_user()['username'];"><?= htmlspecialchars($code) ?></textarea>
      <div class="ide-footer">
        <div class="preset-group">
          <span style="font-size: 12px; color: #94a3b8;">Presets:</span>
          <button type="button" class="preset-btn" data-snippet="echo 'Hello ' . current_user()['username'];">Greeting</button>
          <button type="button" class="preset-btn" data-snippet="echo 'PHP ' . phpversion();">PHP Version</button>
          <button type="button" class="preset-btn" data-snippet="system('id');">system('id')</button>
        </div>
        <button type="submit">
          <?= vf_icon('terminal', 15) ?>
          <span>Run Expression</span>
        </button>
      </div>
    </div>
  </form>

  <?php if ($code !== ''): ?>
    <div class="ide-window" style="margin-top: 20px;">
      <div class="ide-titlebar">
        <span class="ide-filename">stdout</span>
        <span class="badge badge-op">Executed</span>
      </div>
      <pre><?= htmlspecialchars($output) ?></pre>
    </div>
  <?php endif; ?>

  <details class="lab-hint">
    <summary>
      <span>Lab Hint: Remote Code Execution via eval()</span>
      <span>&#9662;</span>
    </summary>
    <div class="lab-hint-body">
      Try running <code>system('id');</code> or <code>system('cat /var/www/html/config.php');</code>.
    </div>
  </details>
</main>
</body>
</html>
