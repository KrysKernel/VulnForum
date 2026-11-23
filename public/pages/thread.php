<?php
// VULN: SQL injection — thread id taken directly from $_GET and concatenated.
// e.g. ?page=pages/thread&id=1 UNION SELECT id,1,username,password,role,... FROM users
$id = $_GET['id'] ?? '0';
$t = db_raw_query("SELECT threads.*, users.username, users.avatar FROM threads JOIN users ON threads.user_id = users.id WHERE threads.id = $id")->fetch_assoc();
if (!$t) {
    echo '<nav class="breadcrumb"><a href="/?page=pages/home">Threads</a><span>/</span><span>Not Found</span></nav>';
    echo '<div class="empty-state">Thread not found.</div>';
    return;
}
$replies = db_raw_query("SELECT replies.*, users.username, users.avatar FROM replies JOIN users ON replies.user_id = users.id WHERE thread_id = $id ORDER BY replies.id");
$replyCount = $replies ? $replies->num_rows : 0;
$me = current_user();
$flash = $_SESSION['forum_flash'] ?? null;
unset($_SESSION['forum_flash']);
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="/?page=pages/home">Threads</a>
  <span>/</span>
  <span><?= htmlspecialchars($t['title']) ?></span>
</nav>

<?php if ($flash): ?>
  <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?>">
    <?= htmlspecialchars($flash['text']) ?>
  </div>
<?php endif; ?>

<!-- VULN: stored XSS — thread body rendered as raw HTML, no sanitization on
     write (register.php/db insert) or read (here). -->
<article class="thread-op">
  <h1 class="thread-op-title"><?= htmlspecialchars($t['title']) ?></h1>
  <div class="thread-op-header">
    <?= vf_avatar($t['username'] ?? '?', '', $t['avatar'] ?? null) ?>
    <div>
      <div class="thread-meta" style="gap: 8px;">
        <strong style="color: var(--text); font-size: 14.5px;"><?= htmlspecialchars($t['username'] ?? '') ?></strong>
        <span class="badge badge-op">OP</span>
      </div>
      <?php if (!empty($t['created_at'])): ?>
        <div class="thread-meta" style="margin-top: 2px;">
          <span>Posted <?= vf_format_date($t['created_at']) ?></span>
          <span>&middot;</span>
          <span><?= $replyCount ?> <?= $replyCount === 1 ? 'reply' : 'replies' ?></span>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($me && (($me['role'] ?? '') === 'admin' || (int)($t['user_id'] ?? 0) === (int)$me['id'])): ?>
      <form method="post" action="/?page=pages/delete_thread" style="margin: 0 0 0 auto;" data-confirm="<?= htmlspecialchars('Delete thread "' . ($t['title'] ?? '') . '" and all its replies?', ENT_QUOTES) ?>">
        <input type="hidden" name="thread_id" value="<?= (int)($t['id'] ?? 0) ?>">
        <button type="submit" class="btn btn-danger btn-sm">
          <?= vf_icon('trash', 14) ?>
          <span>Delete Thread</span>
        </button>
      </form>
    <?php endif; ?>
  </div>
  <div class="thread-body-content"><?= $t['body'] ?></div>
</article>

<div class="section-heading">
  <h3>
    <?= vf_icon('message', 15) ?>
    <span>Replies (<?= $replyCount ?>)</span>
  </h3>
</div>

<?php if ($replyCount > 0): ?>
  <div class="reply-timeline">
    <?php $idx = 1; while ($r = $replies->fetch_assoc()): ?>
      <div class="reply">
        <?= vf_avatar($r['username'], '', $r['avatar'] ?? null) ?>
        <div class="reply-content">
          <div class="reply-header">
            <div class="thread-meta">
              <strong style="color: var(--text);"><?= htmlspecialchars($r['username']) ?></strong>
              <?php if (!empty($r['created_at'])): ?>
                <span>&middot;</span>
                <span><?= vf_format_date($r['created_at']) ?></span>
              <?php endif; ?>
            </div>
            <div style="display: inline-flex; align-items: center; gap: 8px;">
              <span class="reply-num">#<?= $idx++ ?></span>
              <?php if ($me && (($me['role'] ?? '') === 'admin' || (int)($r['user_id'] ?? 0) === (int)$me['id'])): ?>
                <form method="post" action="/?page=pages/delete_reply" style="margin: 0;" data-confirm="Delete this reply?">
                  <input type="hidden" name="reply_id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="thread_id" value="<?= (int)($t['id'] ?? 0) ?>">
                  <button type="submit" class="btn btn-danger btn-xs" title="Delete reply" aria-label="Delete reply">
                    <?= vf_icon('trash', 12) ?>
                    <span>Delete</span>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
          <div class="reply-body"><?= $r['body'] ?></div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php else: ?>
  <div class="empty-state" style="margin-bottom: 24px;">No replies yet. Be the first to join the conversation!</div>
<?php endif; ?>

<?php if (current_user()): ?>
  <div class="card">
    <h3 style="margin: 0 0 14px; font-size: 16px;">Add a Reply</h3>
    <form method="post" action="/?page=pages/reply&id=<?= (int)$id ?>">
      <div class="form-group">
        <label for="reply-body">Your message <span style="color: var(--text-dim); font-weight: 400;">(HTML supported)</span></label>
        <textarea id="reply-body" name="body" placeholder="Write a reply..."></textarea>
      </div>
      <button type="submit">Post Reply</button>
    </form>
  </div>
<?php else: ?>
  <div class="card" style="text-align: center;">
    <p style="margin: 0 0 12px; color: var(--text-muted);">Sign in to post a reply to this discussion.</p>
    <a href="/?page=pages/login" class="btn btn-primary btn-sm">Sign in</a>
  </div>
<?php endif; ?>
