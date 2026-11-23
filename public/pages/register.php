<?php
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VULN: mass assignment — "role" taken straight from the POST body and
    // inserted as-is, so submitting role=admin via a raw HTTP request
    // (bypassing this form, which doesn't even show the field) self-promotes.
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = md5($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'user';
    db_raw_query("INSERT INTO users (username, email, password, role) VALUES ('$username','$email','$password','$role')");
    $msg = 'Registered. You can log in now.';
}
?>
<div class="auth-wrap">
  <div class="card">
    <div class="auth-header">
      <div class="auth-icon" aria-hidden="true">
        <?= vf_icon('user', 22) ?>
      </div>
      <h1>Create your account</h1>
      <p>Join VulnForum to publish threads and replies</p>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label for="reg-username">Username</label>
        <div class="input-wrap">
          <?= vf_icon('user', 16, 'input-icon') ?>
          <input id="reg-username" name="username" placeholder="Choose a username">
        </div>
      </div>

      <div class="form-group">
        <label for="reg-email">Email</label>
        <div class="input-wrap">
          <?= vf_icon('mail', 16, 'input-icon') ?>
          <input id="reg-email" name="email" placeholder="you@example.com">
        </div>
      </div>

      <div class="form-group">
        <label for="reg-password">Password</label>
        <div class="input-wrap has-toggle">
          <?= vf_icon('lock', 16, 'input-icon') ?>
          <input id="reg-password" name="password" type="password" placeholder="Choose a password">
          <button type="button" class="toggle-pw" data-toggle-pw="reg-password" aria-label="Show password">
            <?= vf_icon('eye', 16) ?>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-full">Create Account</button>
    </form>

    <div class="auth-footer">
      <span>Already have an account?</span>
      <a href="/?page=pages/login">Sign in</a>
    </div>

    <details class="lab-hint">
      <summary>
        <span>Lab Hint: Mass Assignment</span>
        <span>&#9662;</span>
      </summary>
      <div class="lab-hint-body">
        This form doesn't expose a <code>role</code> field &mdash; but the backend accepts one from any raw POST (try <code>curl</code> with <code>role=admin</code>).
      </div>
    </details>
  </div>
</div>
