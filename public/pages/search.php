<?php
$q = $_GET['q'] ?? '';
$results = [];
if ($q !== '') {
    // VULN: SQL injection — raw string concatenation into a LIKE clause.
    // e.g. ?q=x' UNION SELECT id,1,username,password,role,NULL,reset_token,NULL FROM users --
    $sql = "SELECT * FROM threads WHERE title LIKE '%$q%' OR body LIKE '%$q%'";
    $results = db_raw_query($sql);
}
?>
<div class="page-header">
  <div>
    <h1>Search Discussions</h1>
    <p class="page-subtitle">Search across thread titles and message bodies</p>
  </div>
</div>

<div class="card" style="margin-bottom: 22px;">
  <form class="search-bar" style="margin-bottom: 0;" method="get" action="/index.php">
    <input type="hidden" name="page" value="pages/search">
    <div class="input-wrap" style="flex: 1;">
      <?= vf_icon('search', 16, 'input-icon') ?>
      <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search threads...">
    </div>
    <button type="submit">Search</button>
  </form>
</div>

<?php if ($q !== ''): ?>
  <!-- VULN: reflected XSS — raw query echoed into the page as HTML.
       Try q = <img src=x onerror=alert(document.cookie)> -->
  <div class="toolbar">
    <span style="font-size: 14px; color: var(--text-muted);">
      Results for: <strong style="color: var(--text);"><?= $q ?></strong>
    </span>
    <?php if ($results): ?>
      <span class="badge badge-user"><?= (int)$results->num_rows ?> found</span>
    <?php endif; ?>
  </div>

  <?php if ($results && $results->num_rows > 0): ?>
    <div class="thread-list">
      <?php while ($r = $results->fetch_assoc()): ?>
        <article class="thread-card">
          <div class="thread-card-body">
            <a class="thread-title" href="/?page=pages/thread&id=<?= urlencode((string)$r['id']) ?>">
              <?= htmlspecialchars($r['title']) ?>
            </a>
            <?php if (!empty($r['body'])): ?>
              <p class="thread-snippet"><?= htmlspecialchars($r['body']) ?></p>
            <?php endif; ?>
          </div>
        </article>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">No matching threads found for your query.</div>
  <?php endif; ?>
<?php endif; ?>
