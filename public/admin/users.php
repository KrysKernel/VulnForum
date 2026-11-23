<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';
require_admin();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $deleteId = (int)$_POST['delete_user_id'];
    if ($deleteId <= 0) {
        $_SESSION['admin_users_flash'] = ['type' => 'error', 'text' => 'Invalid user ID.'];
    } elseif ($deleteId === (int)$user['id']) {
        $_SESSION['admin_users_flash'] = ['type' => 'error', 'text' => 'You cannot delete your own account.'];
    } else {
        $conn = db_conn();
        $inTransaction = false;
        try {
            $conn->begin_transaction();
            $inTransaction = true;

            $stmt = $conn->prepare('SELECT username FROM users WHERE id = ?');
            $stmt->bind_param('i', $deleteId);
            $stmt->execute();
            $target = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$target) {
                $conn->rollback();
                $inTransaction = false;
                $_SESSION['admin_users_flash'] = ['type' => 'error', 'text' => 'User not found.'];
            } else {
                $stmt = $conn->prepare('DELETE FROM replies WHERE user_id = ? OR thread_id IN (SELECT id FROM threads WHERE user_id = ?)');
                $stmt->bind_param('ii', $deleteId, $deleteId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare('DELETE FROM threads WHERE user_id = ?');
                $stmt->bind_param('i', $deleteId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
                $stmt->bind_param('i', $deleteId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $inTransaction = false;
                $_SESSION['admin_users_flash'] = [
                    'type' => 'success',
                    'text' => 'User "' . $target['username'] . '" (#' . $deleteId . ') has been deleted.'
                ];
            }
        } catch (Throwable $e) {
            if ($inTransaction) {
                $conn->rollback();
            }
            $_SESSION['admin_users_flash'] = ['type' => 'error', 'text' => 'Failed to delete user: ' . $e->getMessage()];
        }
        $conn->close();
    }
    header('Location: /admin/users.php');
    exit;
}

$flash = $_SESSION['admin_users_flash'] ?? null;
unset($_SESSION['admin_users_flash']);

$cssVer = @filemtime(__DIR__ . '/../css/style.css') ?: 2;
$jsVer  = @filemtime(__DIR__ . '/../js/app.js') ?: 2;

// VULN: dumps password hashes and reset tokens straight into the page.
$result = db_raw_query("SELECT * FROM users ORDER BY id ASC");
$users = [];
$adminCount = 0;
$tokenCount = 0;
while ($u = $result->fetch_assoc()) {
    $users[] = $u;
    if ($u['role'] === 'admin') $adminCount++;
    if (!empty($u['reset_token'])) $tokenCount++;
}
$threadCount = (int)db_raw_query("SELECT COUNT(*) AS c FROM threads")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - Users | VulnForum</title>
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
      <a href="/admin/users.php" class="nav-admin active">Users</a>
      <a href="/admin/eval.php" class="nav-admin">Template Preview</a>
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
      <h1>Users Directory</h1>
      <p class="page-subtitle">Manage registered accounts and inspect credentials</p>
    </div>
    <a href="/admin/eval.php" class="btn btn-primary btn-sm">Open Template Preview &rarr;</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?>">
      <?= htmlspecialchars($flash['text']) ?>
    </div>
  <?php endif; ?>

  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-label">Total Users</div>
      <div class="kpi-value"><?= count($users) ?></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Administrators</div>
      <div class="kpi-value"><?= $adminCount ?></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Total Threads</div>
      <div class="kpi-value"><?= $threadCount ?></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Active Reset Tokens</div>
      <div class="kpi-value"><?= $tokenCount ?></div>
    </div>
  </div>

  <div class="table-wrap">
    <div class="table-toolbar">
      <strong style="font-size: 14px;">All Accounts</strong>
      <input type="text" data-table-filter="#users-table" placeholder="Filter by username, email, role...">
    </div>
    <table id="users-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>User</th>
          <th>Email</th>
          <th>Role</th>
          <th>Password Hash</th>
          <th>Reset Token</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td class="mono-cell">#<?= (int)$u['id'] ?></td>
          <td>
            <div style="display: flex; align-items: center; gap: 10px;">
              <?= vf_avatar($u['username'], 'sm', $u['avatar'] ?? null) ?>
              <strong><?= htmlspecialchars($u['username']) ?></strong>
            </div>
          </td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <span class="badge <?= $u['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
              <?= htmlspecialchars($u['role']) ?>
            </span>
          </td>
          <td class="mono-cell">
            <span class="copy-inline">
              <span><?= htmlspecialchars($u['password']) ?></span>
              <button type="button" class="copy-btn" data-copy="<?= htmlspecialchars($u['password']) ?>">Copy</button>
            </span>
          </td>
          <td class="mono-cell">
            <?php if (!empty($u['reset_token'])): ?>
              <span class="copy-inline">
                <code><?= htmlspecialchars($u['reset_token']) ?></code>
                <button type="button" class="copy-btn" data-copy="<?= htmlspecialchars($u['reset_token']) ?>">Copy</button>
              </span>
            <?php else: ?>
              <span style="color: var(--text-dim);">&mdash;</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ((int)$u['id'] === (int)$user['id']): ?>
              <span style="color: var(--text-dim); font-size: 12px;">Current session</span>
            <?php else: ?>
              <form method="post" style="margin: 0;" data-confirm="<?= htmlspecialchars('Delete user "' . $u['username'] . '" and all their threads and replies?', ENT_QUOTES) ?>">
                <input type="hidden" name="delete_user_id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">
                  <?= vf_icon('trash', 14) ?>
                  <span>Delete</span>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
