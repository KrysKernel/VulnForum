<?php
require_login();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $body = $_POST['body'] ?? '';
    $uid = current_user()['id'];
    db_raw_query("INSERT INTO threads (user_id, title, body) VALUES ($uid, '" . addslashes($title) . "', '" . addslashes($body) . "')");
    header('Location: /?page=pages/home');
    exit;
}
?>
<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="/?page=pages/home">Threads</a>
  <span>/</span>
  <span>New Thread</span>
</nav>

<div class="card" style="max-width: 760px;">
  <div class="page-header" style="margin-bottom: 18px;">
    <div>
      <h1>Create a New Thread</h1>
      <p class="page-subtitle">Start a discussion with the VulnForum community</p>
    </div>
    <span class="badge badge-op">HTML Allowed</span>
  </div>

  <form method="post">
    <div class="form-group">
      <label for="thread-title">Title</label>
      <input id="thread-title" name="title" placeholder="Give your discussion a clear title">
    </div>
    <div class="form-group">
      <label for="thread-body">Body</label>
      <textarea id="thread-body" name="body" rows="7" placeholder="Write your post content here..."></textarea>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
      <button type="submit">Publish Thread</button>
      <a href="/?page=pages/home" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>
