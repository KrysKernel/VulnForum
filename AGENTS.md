# AGENTS.md

Context and working instructions for AI coding agents operating in **VulnForum**.

## 1. Project Purpose & Golden Rule

**VulnForum** is an **intentionally vulnerable educational LAMP-stack forum lab** (vanilla PHP 8.2, Apache 2.4, MySQL 8.0, Docker Compose). It contains 19+ deliberate security flaws documented in `VULNERABILITIES.md` and `XSS/STORED_XSS_GUIDE.md`, marked inline with `// VULN:` or `<!-- VULN: ... -->`.

- **DO NOT fix, harden, or sanitize intentional vulnerabilities** during refactors, UI updates, or feature additions unless the user explicitly asks to remediate them.
- **Keep docs in sync:** If you add, move, or intentionally modify a vulnerability, update its `// VULN:` comment, `VULNERABILITIES.md`, and the overview table in `README.md`.
- **Safe plumbing where non-vulnerable behavior is needed:** Standard CRUD features added for lab usability (thread/reply/user deletion, avatar DB persistence) intentionally use `mysqli::prepare()` and transactions so the forum stays usable. Follow that split when adding non-vulnerable features.
- **Lab-only scope:** Never assume production deployment; never remove lab hints (`<details class="lab-hint">`) unless requested.

## 2. Tech Stack & Runtime

| Service | Stack | Container / Process | Ports (Host → Container) |
| :--- | :--- | :--- | :--- |
| Web / App | PHP 8.2 (`mod_php`) + Apache 2.4 (`mysqli`, `mod_rewrite`) | `vulnforum-web` | `8082 → 80` |
| Database | MySQL 8.0 | `vulnforum-db` | `3307 → 3306` |
| XSS Listener (optional) | Python 3 + Flask | `XSS/attacker_server.py` | `8080` (host) |

- **Zero build step & zero package managers:** No Composer, npm, bundler, or framework. Plain PHP, CSS, and vanilla JS.
- **Live editing:** `./public` is bind-mounted to `/var/www/html`. Edits to PHP, CSS, or JS take effect immediately on browser reload without rebuilding containers.
- **Rebuild required only when** modifying `apache/Dockerfile` or `apache/apache-vhost.conf`.
- **DB seed lifecycle:** `./init.sql` runs only on first initialization of the `dbdata` volume. Runtime auto-migration `db_ensure_avatar_column()` in `public/includes/db.php` adds `users.avatar` if missing on existing volumes.

## 3. Repository Structure

```text
vulnforum/
├── apache/
│   ├── Dockerfile                 # php:8.2-apache + mysqli + insecure php.ini (display_errors, expose_php, strict_mode=0)
│   └── apache-vhost.conf          # Indexes, AllowOverride All, /server-status, ServerTokens Full, TraceEnable On
├── public/                        # Mounted to /var/www/html
│   ├── index.php                  # Main router via ?page= (default: pages/home) — intentional LFI/RFI
│   ├── config.php                 # Hardcoded DB credentials, APP_SECRET, display_errors=1
│   ├── file.php                   # Standalone file reader (?name=) — intentional Path Traversal
│   ├── legacy_check.php           # Standalone JSON auth-check API — intentional PHP type juggling (==)
│   ├── admin/
│   │   ├── users.php              # Standalone admin users table, credential dump, FK-safe user delete
│   │   └── eval.php               # Standalone admin PHP eval() sandbox
│   ├── includes/
│   │   ├── db.php                 # db_conn(), db_raw_query() (intentional SQLi/error leak), db_ensure_avatar_column()
│   │   ├── auth.php               # Session start, current_user(), require_login(), require_admin(), attempt_login(), legacy_password_ok(), remember-me unserialize()
│   │   ├── header.php             # Starts ob_start(), renders global <head> and forum navbar
│   │   ├── footer.php             # Closes <main> and renders footer
│   │   └── ui.php                 # vf_avatar(), vf_format_date(), vf_excerpt(), vf_icon()
│   ├── pages/
│   │   ├── home.php               # Thread feed (latest/discussed), stats, login-gated Members sidebar
│   │   ├── thread.php             # Thread + replies view (intentional SQLi on id, Stored XSS on bodies)
│   │   ├── new_thread.php         # Create thread (POST)
│   │   ├── reply.php              # Post reply (POST, intentional SQLi on ?id=)
│   │   ├── delete_thread.php      # POST handler (author or admin, prepared + transaction)
│   │   ├── delete_reply.php       # POST handler (author or admin, prepared)
│   │   ├── search.php             # Thread search (intentional SQLi + Reflected XSS on ?q=)
│   │   ├── login.php              # Login + Remember Me (intentional SQLi via attempt_login)
│   │   ├── register.php           # Registration (intentional Mass Assignment on role + SQLi)
│   │   ├── logout.php             # Destroys session and clears remember cookie
│   │   ├── forgot_password.php    # Generates predictable 4-digit rand() token & echoes it
│   │   ├── reset_password.php     # Resets password (intentional SQLi)
│   │   └── profile.php            # Profile view + avatar upload (intentional arbitrary PHP upload to /uploads/)
│   ├── css/style.css              # Dark/light theme styles (CSS custom properties)
│   ├── js/app.js                  # Vanilla JS UI behaviors via data-* hooks
│   └── uploads/                   # Writable webroot upload dir; contains tracked README images (btc1.jpg, qr-code.png, FrontPage.png)
├── XSS/
│   ├── attacker_server.py         # Flask GET /steal?data=... listener (writes stolen_cookies.txt)
│   └── STORED_XSS_GUIDE.md        # Educational walkthrough for Stored XSS (#13)
├── docker-compose.yml
├── init.sql                       # Schema (users, threads, replies) + seed data
├── README.md                      # Lab overview & quick start
└── VULNERABILITIES.md             # Canonical catalogue of all 19 vulnerabilities (what → where → exploit → fix)
```

## 4. Architecture & Code Conventions

### Routing & Output Buffering
- Forum pages live under `public/pages/` and are accessed as `/?page=pages/<name>` through `public/index.php`.
- `public/index.php` includes `includes/header.php`, then `$page . '.php'`, then `includes/footer.php`.
- `includes/header.php` calls `ob_start()` on line 2 so pages under `public/pages/` can call `header('Location: ...'); exit;` even after header markup has been buffered. **Do not remove `ob_start()`.**
- Standalone endpoints (`public/admin/users.php`, `public/admin/eval.php`, `public/file.php`, `public/legacy_check.php`) do **not** go through `index.php`. Both admin pages duplicate the HTML `<head>` and `<header>` markup instead of including `header.php`/`footer.php` — keep navigation/asset changes synchronized across `includes/header.php`, `admin/users.php`, and `admin/eval.php`.

### Database Patterns
- **Schema (`init.sql`):** `users`, `threads`, `replies`. Foreign keys (`threads.user_id`, `replies.thread_id`, `replies.user_id`) do **not** declare `ON DELETE CASCADE`. Any deletion of a thread or user must explicitly delete child rows first (`replies` → `threads` → `users`) inside a transaction (see `delete_thread.php` and `admin/users.php`).
- **Two query helpers in `public/includes/db.php`:**
  - `db_raw_query($sql)` — intentionally raw SQL that `die()`s with full MySQL error + query string. Keep using it only for intentional SQLi surfaces or simple static queries; note it opens a fresh `mysqli` connection per call.
  - `db_conn()` — returns a `mysqli` connection for prepared statements and transactions. Always close statements and connections (`$stmt->close(); $conn->close();`).
- **Seeded credentials (`init.sql`, unsalted MD5):**
  - `admin` / `admin123` (`role=admin`)
  - `alice` / `password123` (`role=user`)
  - `bob` / `letmein` (`role=user`)
- **DB credentials (`docker-compose.yml` & `public/config.php`):** DB `vulnforum`, app user `vulnforum` / `forum123`, root password `rootpass123`.

### UI, CSS & JS Conventions
- **PHP UI helpers (`public/includes/ui.php`):** Use `vf_avatar($name, $size, $avatar)`, `vf_format_date($ts)`, `vf_excerpt($html, $limit)`, and `vf_icon($name, $size, $extraClass, $extraStyle)` instead of duplicating SVG/avatar markup.
- **CSS (`public/css/style.css`):** Theme variables are defined in `:root` (dark default) and `[data-theme="light"]`. Assets are cache-busted with `?v=<?= @filemtime(...) ?: 2 ?>`.
- **JS (`public/js/app.js`):** Attach behaviors declaratively using existing hooks rather than inline JS:
  - `#theme-toggle` (persists `vf-theme` in `localStorage`)
  - `[data-toggle-pw="<input-id>"]` (show/hide password)
  - `[data-table-filter="<table-selector>"]` (live client-side table search)
  - `[data-copy="<value>"]` (clipboard copy button)
  - `[data-snippet="<php-code>"]` (populates `#expr` in `admin/eval.php`)
  - `form[data-confirm="<message>"]` (confirmation dialog before submit)
- **Session flash messages:** Stored as `['type' => 'success'|'error', 'text' => '...']` and unset on read:
  - `$_SESSION['forum_flash']` (`home.php`, `thread.php`, `delete_thread.php`, `delete_reply.php`)
  - `$_SESSION['profile_flash']` (`profile.php`)
  - `$_SESSION['admin_users_flash']` (`admin/users.php`)

## 5. Intentional Vulnerability Surfaces (Quick Reference)

See `VULNERABILITIES.md` for full details on all 19 items. Do not silently "fix" these:
- **SQL Injection (#1, #2):** `includes/auth.php` (`attempt_login`, `try_remember_me`), `pages/thread.php`, `pages/search.php`, `pages/reply.php`, `pages/new_thread.php`, `pages/register.php`, `pages/forgot_password.php`, `pages/reset_password.php`, `legacy_check.php`.
- **Auth / Session / Deserialization (#3, #4, #5, #6, #11, #12):** MD5 hashing, `legacy_password_ok()` (`==`), unsigned `unserialize()` remember-me cookie, `register.php` mass-assigning `role`, session-trusted `require_admin()`, predictable 4-digit `rand()` reset token echoed to user, no `session_regenerate_id()` + `session.use_strict_mode = 0`.
- **RCE / File Bugs (#7, #8, #9, #10):** `admin/eval.php` (`eval()`), `index.php` (`?page=` LFI/RFI), `pages/profile.php` (unrestricted upload into executable `/uploads/`), `file.php` (`?name=` path traversal).
- **XSS / Data Exposure / CSRF / Misconfig (#13–#19):** Unescaped `$t['body']` and `$r['body']` in `pages/thread.php`, unescaped `$q` in `pages/search.php`, password hashes + reset tokens dumped in `admin/users.php`, `display_errors=1` + verbose `db_raw_query()` errors, no CSRF tokens on any form, hardcoded secrets in `config.php`, insecure Apache/PHP config in `apache/`.

## 6. Common Commands & Verification

There is no automated test suite. Verify changes with PHP linting and manual/HTTP checks against the running container.

```bash
# Start or rebuild containers (http://localhost:8082)
docker compose up -d --build

# Stop containers (keeps MySQL volume)
docker compose down

# Reset MySQL database back to init.sql seed state
docker compose down -v && docker compose up -d

# PHP syntax check across all PHP files inside the container
docker exec vulnforum-web bash -c 'find /var/www/html -name "*.php" -print0 | xargs -0 -n1 php -l'

# Open MySQL shell
docker exec -it vulnforum-db mysql -u vulnforum -pforum123 vulnforum

# Run optional XSS cookie-exfiltration listener (http://localhost:8080/steal?data=...)
python3 XSS/attacker_server.py
```

## 7. Known Quirks & Pitfalls

1. **`require_login()` redirect path (`public/includes/auth.php`):** Redirects to `/?page=login` instead of `/?page=pages/login` (which causes a failed include in `index.php` because the file is `public/pages/login.php`). Notice `delete_thread.php` and `delete_reply.php` redirect manually to `/?page=pages/login` to avoid this. Be aware of this before relying on `require_login()` for unauthenticated flows (only fix it if asked).
2. **Tracked images in `public/uploads/`:** `public/uploads/FrontPage.png`, `public/uploads/btc1.jpg`, and `public/uploads/qr-code.png` are committed in Git (or referenced/embedded in `README.md`). Do not delete them when cleaning uploads.
3. **Untracked lab artifacts:** `.gitignore` only ignores OS/editor files and `*.log`. Uploaded test files in `public/uploads/` (e.g., `shell.php`) and `stolen_cookies.txt` created by `XSS/attacker_server.py` are **not** gitignored — clean them up before committing.
4. **`README.md` listener path:** `README.md` shows `python3 attacker_server.py`, but the script actually lives at `XSS/attacker_server.py`.
5. **Login-gated Members sidebar:** In `public/pages/home.php`, the sidebar Members list is intentionally hidden for anonymous visitors (`$me && $members`) per commit `4ed1321`.
