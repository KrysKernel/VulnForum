<?php
$msg = '';
$isSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $token = $_POST['token'] ?? '';
    $newpass = md5($_POST['new_password'] ?? '');
    // VULN: SQLi here too, same pattern as everywhere else in this app.
    $result = db_raw_query("SELECT * FROM users WHERE username = '$username' AND reset_token = '$token'");
    if ($result->num_rows > 0) {
        db_raw_query("UPDATE users SET password = '$newpass', reset_token = NULL WHERE username = '$username'");
        $msg = 'Password reset.';
        $isSuccess = true;
    } else {
        $msg = 'Invalid token.';
    }
}
?>
<div class="auth-wrap">
  <div class="card">
    <div class="auth-header">
      <div class="auth-icon" aria-hidden="true">
        <?= vf_icon('key', 22) ?>
      </div>
      <h1>Reset Password</h1>
      <p>Enter your username, reset token, and new password</p>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert <?= $isSuccess ? 'alert-success' : 'alert-error' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label for="rp-username">Username</label>
        <div class="input-wrap">
          <?= vf_icon('user', 16, 'input-icon') ?>
          <input id="rp-username" name="username" placeholder="Enter your username">
        </div>
      </div>

      <div class="form-group">
        <label for="rp-token">4-Digit Token</label>
        <div class="input-wrap">
          <?= vf_icon('lock', 16, 'input-icon') ?>
          <input id="rp-token" name="token" placeholder="e.g. 0421">
        </div>
      </div>

      <div class="form-group">
        <label for="rp-password">New Password</label>
        <div class="input-wrap has-toggle">
          <?= vf_icon('lock', 16, 'input-icon') ?>
          <input id="rp-password" name="new_password" type="password" placeholder="Choose a new password">
          <button type="button" class="toggle-pw" data-toggle-pw="rp-password" aria-label="Show password">
            <?= vf_icon('eye', 16) ?>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-full">Reset Password</button>
    </form>

    <div class="auth-footer">
      <a href="/?page=pages/forgot_password">Request a token</a>
      <a href="/?page=pages/login">Back to sign in</a>
    </div>
  </div>
</div>
