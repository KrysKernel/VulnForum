<?php
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VULN: no CSRF token on this state-changing form.
    if (attempt_login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        if (isset($_POST['remember'])) set_remember_me($_POST['username']);
        header('Location: /?page=pages/home');
        exit;
    }
    $msg = 'Invalid credentials';
}
?>
<div class="auth-wrap">
  <div class="card">
    <div class="auth-header">
      <div class="auth-icon" aria-hidden="true">
        <?= vf_icon('lock', 22) ?>
      </div>
      <h1>Sign in to VulnForum</h1>
      <p>Enter your credentials to access the community</p>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-error"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label for="username">Username</label>
        <div class="input-wrap">
          <?= vf_icon('user', 16, 'input-icon') ?>
          <input id="username" name="username" placeholder="Enter your username">
        </div>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap has-toggle">
          <?= vf_icon('lock', 16, 'input-icon') ?>
          <input id="password" name="password" type="password" placeholder="Enter your password">
          <button type="button" class="toggle-pw" data-toggle-pw="password" aria-label="Show password">
            <?= vf_icon('eye', 16) ?>
          </button>
        </div>
      </div>

      <label class="checkbox-label">
        <input type="checkbox" name="remember">
        <span>Keep me signed in (Remember me)</span>
      </label>

      <button type="submit" class="btn-full">Sign in</button>
    </form>

    <div class="auth-footer">
      <a href="/?page=pages/forgot_password">Forgot password?</a>
      <a href="/?page=pages/register">Create an account</a>
    </div>

    <details class="lab-hint">
      <summary>
        <span>Lab Hint: SQL Injection Login Bypass</span>
        <span>&#9662;</span>
      </summary>
      <div class="lab-hint-body">
        Try username <code>admin' -- </code> with any password.
      </div>
    </details>
  </div>
</div>
