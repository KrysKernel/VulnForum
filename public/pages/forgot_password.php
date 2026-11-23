<?php
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    // VULN: predictable, low-entropy reset token generated with rand()
    // (not cryptographically secure), and returned directly in the page
    // response instead of only being emailed to the account owner.
    $token = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    db_raw_query("UPDATE users SET reset_token = '$token' WHERE username = '" . addslashes($username) . "'");
    $msg = "Reset token (would normally be emailed): $token";
}
?>
<div class="auth-wrap">
  <div class="card">
    <div class="auth-header">
      <div class="auth-icon" aria-hidden="true">
        <?= vf_icon('key', 22) ?>
      </div>
      <h1>Forgot Password</h1>
      <p>Generate a 4-digit recovery token for your account</p>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label for="fp-username">Username</label>
        <div class="input-wrap">
          <?= vf_icon('user', 16, 'input-icon') ?>
          <input id="fp-username" name="username" placeholder="Enter your username">
        </div>
      </div>
      <button type="submit" class="btn-full">Request Reset Token</button>
    </form>

    <div class="auth-footer">
      <a href="/?page=pages/reset_password">Already have a token?</a>
      <a href="/?page=pages/login">Back to sign in</a>
    </div>
  </div>
</div>
