<?php
$sort = ($_GET['sort'] ?? 'latest') === 'discussed' ? 'discussed' : 'latest';
$orderClause = $sort === 'discussed'
    ? 'reply_count DESC, threads.id DESC'
    : 'threads.id DESC';

$result = db_raw_query("
    SELECT threads.*, users.username, users.role AS author_role, users.avatar AS author_avatar,
           COUNT(replies.id) AS reply_count
    FROM threads
    JOIN users ON threads.user_id = users.id
    LEFT JOIN replies ON replies.thread_id = threads.id
    GROUP BY threads.id, users.username, users.role, users.avatar
    ORDER BY $orderClause
");

$statsRow = db_raw_query("
    SELECT
      (SELECT COUNT(*) FROM threads) AS thread_count,
      (SELECT COUNT(*) FROM replies) AS reply_count,
      (SELECT COUNT(*) FROM users) AS user_count
")->fetch_assoc();

$me = current_user();
$members = $me
    ? db_raw_query("SELECT username, role, bio, avatar FROM users ORDER BY id ASC LIMIT 6")
    : null;
$flash = $_SESSION['forum_flash'] ?? null;
unset($_SESSION['forum_flash']);
?>

<?php if ($flash): ?>
  <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?>">
    <?= htmlspecialchars($flash['text']) ?>
  </div>
<?php endif; ?>

<section class="hero-banner">
  <div>
    <div class="hero-eyebrow">
      <?= vf_icon('bolt', 14) ?>
      <span>Security Lab Community</span>
    </div>
    <h1>Welcome to VulnForum</h1>
    <p>Explore threads, share snippets, and practice web security techniques in an isolated local sandbox.</p>
  </div>
  <div class="hero-actions">
    <?php if (current_user()): ?>
      <a href="/?page=pages/new_thread" class="btn btn-primary">
        <?= vf_icon('plus', 16) ?>
        <span>New Thread</span>
      </a>
    <?php else: ?>
      <a href="/?page=pages/login" class="btn btn-primary">Sign in to post</a>
      <a href="/?page=pages/register" class="btn btn-accent">Create account</a>
    <?php endif; ?>
  </div>
</section>

<div class="forum-grid">
  <section>
    <div class="toolbar">
      <div class="pill-tabs" role="tablist">
        <a href="/?page=pages/home&sort=latest" class="<?= $sort === 'latest' ? 'active' : '' ?>">Latest</a>
        <a href="/?page=pages/home&sort=discussed" class="<?= $sort === 'discussed' ? 'active' : '' ?>">Most Discussed</a>
      </div>
      <span style="font-size: 13px; color: var(--text-muted);">
        <?= (int)$statsRow['thread_count'] ?> discussions
      </span>
    </div>

    <div class="thread-list">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($t = $result->fetch_assoc()): ?>
          <article class="thread-card">
            <?= vf_avatar($t['username'], '', $t['author_avatar'] ?? null) ?>
            <div class="thread-card-body">
              <div class="thread-card-top">
                <a class="thread-title" href="/?page=pages/thread&id=<?= $t['id'] ?>">
                  <?= htmlspecialchars($t['title']) ?>
                </a>
                <div style="display: inline-flex; align-items: center; gap: 8px; flex-shrink: 0;">
                  <a class="reply-pill" href="/?page=pages/thread&id=<?= $t['id'] ?>" title="Replies">
                    <?= vf_icon('message', 14) ?>
                    <span><?= (int)$t['reply_count'] ?></span>
                  </a>
                  <?php if ($me && (($me['role'] ?? '') === 'admin' || (int)($t['user_id'] ?? 0) === (int)$me['id'])): ?>
                    <form method="post" action="/?page=pages/delete_thread" style="margin: 0;" data-confirm="<?= htmlspecialchars('Delete thread "' . $t['title'] . '" and all its replies?', ENT_QUOTES) ?>">
                      <input type="hidden" name="thread_id" value="<?= (int)$t['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-xs" title="Delete thread" aria-label="Delete thread">
                        <?= vf_icon('trash', 13) ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
              <p class="thread-snippet"><?= htmlspecialchars(vf_excerpt($t['body'])) ?></p>
              <div class="thread-meta">
                <span class="meta-item">
                  <strong><?= htmlspecialchars($t['username']) ?></strong>
                  <?php if (($t['author_role'] ?? '') === 'admin'): ?>
                    <span class="badge badge-admin">Admin</span>
                  <?php endif; ?>
                </span>
                <?php if (!empty($t['created_at'])): ?>
                  <span>&middot;</span>
                  <span class="meta-item">
                    <?= vf_icon('clock', 13) ?>
                    <span><?= vf_format_date($t['created_at']) ?></span>
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-state">No threads yet. Start the first discussion!</div>
      <?php endif; ?>
    </div>
  </section>

  <aside class="sidebar">
    <div class="widget">
      <h2 class="widget-title">
        <?= vf_icon('chart', 15) ?>
        <span>Community Stats</span>
      </h2>
      <div class="stats-mini-grid">
        <div class="stat-mini">
          <span class="stat-mini-val"><?= (int)$statsRow['thread_count'] ?></span>
          <span class="stat-mini-label">Threads</span>
        </div>
        <div class="stat-mini">
          <span class="stat-mini-val"><?= (int)$statsRow['reply_count'] ?></span>
          <span class="stat-mini-label">Replies</span>
        </div>
        <div class="stat-mini">
          <span class="stat-mini-val"><?= (int)$statsRow['user_count'] ?></span>
          <span class="stat-mini-label">Members</span>
        </div>
      </div>
    </div>

    <?php if ($me && $members): ?>
      <div class="widget">
        <h2 class="widget-title">
          <?= vf_icon('users', 15) ?>
          <span>Members</span>
        </h2>
        <div class="member-list">
          <?php while ($m = $members->fetch_assoc()): ?>
            <div class="member-row">
              <?= vf_avatar($m['username'], 'sm', $m['avatar'] ?? null) ?>
              <div class="member-info">
                <span class="member-name"><?= htmlspecialchars($m['username']) ?></span>
                <?php if (!empty($m['bio'])): ?>
                  <span class="member-bio"><?= htmlspecialchars($m['bio']) ?></span>
                <?php endif; ?>
              </div>
              <span class="badge <?= $m['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                <?= htmlspecialchars($m['role']) ?>
              </span>
            </div>
          <?php endwhile; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="widget">
      <h2 class="widget-title">
        <?= vf_icon('link', 15) ?>
        <span>Quick Access</span>
      </h2>
      <div class="quick-links">
        <a href="/?page=pages/search"><span>Search &amp; Filter</span><span>&rarr;</span></a>
        <a href="/?page=pages/forgot_password"><span>Password Reset Flow</span><span>&rarr;</span></a>
        <a href="/?page=pages/profile"><span>Profile &amp; Avatar Upload</span><span>&rarr;</span></a>
        <a href="/admin/users.php"><span>Admin Panel</span><span>&rarr;</span></a>
      </div>
    </div>
  </aside>
</div>
