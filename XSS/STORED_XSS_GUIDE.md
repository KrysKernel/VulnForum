# Walkthrough Guide: Stored XSS in VulnForum

> **For local lab use only.** This guide accompanies item **13** in `VULNERABILITIES.md` and explains what a *Stored Cross-Site Scripting (Stored XSS)* vulnerability is, how to locate it in the **VulnForum** codebase, how to demonstrate it educationally, and how to remediate it securely.

---

## 1. Core Concept

### Why It Happens
A web browser receives an HTML document from the server and interprets it as a mix of structure (HTML tags), styling (CSS), and behavior (JavaScript). When an application reads user-supplied text and inserts it directly into HTML without escaping special characters, the browser **cannot distinguish** between the site's legitimate markup and the user-entered text.

### Why It Is Called *Stored*
- **XSS (Cross-Site Scripting)**: injection of client-side code into pages viewed by other users.
- **Stored (Persistent)**: the payload is **saved in the MySQL database** (in the `threads` or `replies` tables defined in `init.sql`). It is not limited to the submitter's single request: from that point on, **anyone who opens the thread** (including the administrator) receives that content from the database, and their browser interprets it as active code.

---

## 2. Data Flow Analysis (*Source → Storage → Sink*)

To explain the vulnerability in a lab setting, it helps to trace the exact path of data across the project files:

1. **Source & Storage (Input and Database Persistence)**
   - `public/pages/new_thread.php` (lines 5–8): reads `$_POST['body']` and saves it to the `threads` table using only `addslashes()` (which escapes quotes for SQL syntax, but **does not** neutralize HTML tags such as `<` or `>`).
   - `public/pages/reply.php` (lines 4–7): does the same, saving `$_POST['body']` to the `replies` table.

2. **Sink (HTML Page Rendering Point)**
   - In `public/pages/thread.php`, you can directly compare one **protected** line and two **vulnerable** lines:
     - **Protected title (line 24)**:
       ```php
       <h1 class="thread-op-title"><?= htmlspecialchars($t['title']) ?></h1>
       ```
       Here `htmlspecialchars()` converts HTML symbols into safe text entities (`&lt;`, `&gt;`, `&quot;`, `&amp;`), so the browser prints the text verbatim.
     - **Vulnerable thread body (line 41)**:
       ```php
       <div class="thread-body-content"><?= $t['body'] ?></div>
       ```
     - **Vulnerable reply body (line 67)**:
       ```php
       <div class="reply-body"><?= $r['body'] ?></div>
       ```
     In both places, `$t['body']` and `$r['body']` are printed **without encoding**.

---

## 3. Explanation of Item 13 in `VULNERABILITIES.md`

Item 13 of `VULNERABILITIES.md` summarizes the mechanism using an `<img>` tag example:

1. **Injected HTML tag (`<img src=x ...>`)**: inserts an image element pointing to a nonexistent resource (`x`).
2. **Event handler (`onerror="..."`)**: a standard HTML attribute that instructs the browser to execute JavaScript when loading the image fails. Because `x` does not exist, the event fires automatically on page load with no interaction required from the user reading the thread.
3. **Access to `document.cookie`**: in JavaScript, `document.cookie` exposes cookies that are not protected by the `HttpOnly` flag. In VulnForum, the `PHPSESSID` session cookie (which identifies the authenticated user) does not have `session.cookie_httponly = 1`, so it is readable from the page context.

---

## 4. Lab Demonstration Walkthrough

### Step 1 — Observe HTML Interpretation Using the Initial Seed
The initial database (`init.sql`, lines 42–43) already contains a harmless example in the first thread (*"Welcome to VulnForum"*):

```sql
(1, 'Welcome to VulnForum', 'Post anything here. <b>HTML renders fine.</b>')
```

1. Start the lab and open thread #1 in your browser.
2. Open Developer Tools (**DevTools → Elements / Inspect**) on the text *"HTML renders fine."*
3. Point out that `<b>...</b>` has become an actual DOM node in the document and is rendered in bold, whereas in the thread title any `<` or `>` would be displayed as plain text.

### Step 2 — Compare *Stored XSS* and *Reflected XSS*
Compare the two cases present in the project:
- **Stored XSS (`public/pages/thread.php`)**: the content lives in the MySQL database and is automatically served to every visitor of the page.
- **Reflected XSS (`public/pages/search.php`, line 33)**: the `$_GET['q']` parameter is immediately reflected in the response (`Results for: <strong><?= $q ?></strong>`), so it only affects users who open a specially crafted URL.

### Step 3 — Architectural Factors That Amplify Impact
Connect the XSS flaw to other configuration choices in the lab:
- **Cookies without `HttpOnly`**: `apache/Dockerfile` does not set `session.cookie_httponly = 1`, leaving `PHPSESSID` readable by JavaScript.
- **Missing Content Security Policy (CSP)**: `apache/apache-vhost.conf` does not define a `Content-Security-Policy` header, so the browser does not restrict inline scripts or external resources.
- **Missing CSRF tokens**: forms (`new_thread.php`, `reply.php`, `profile.php`, `admin/eval.php`) rely solely on the session cookie; any requests triggered from an authenticated victim's browser will be accepted by the server.

---

## 5. Remediation (Live Fix)

### Primary Fix: Contextual Output Encoding
In `public/pages/thread.php`, apply `htmlspecialchars()` with `ENT_QUOTES` and `UTF-8` encoding to all HTML output points:

```php
// Line 41 — before (vulnerable)
<div class="thread-body-content"><?= $t['body'] ?></div>

// Line 41 — after (safe)
<div class="thread-body-content"><?= htmlspecialchars($t['body'], ENT_QUOTES, 'UTF-8') ?></div>
```

```php
// Line 67 — before (vulnerable)
<div class="reply-body"><?= $r['body'] ?></div>

// Line 67 — after (safe)
<div class="reply-body"><?= htmlspecialchars($r['body'], ENT_QUOTES, 'UTF-8') ?></div>
```

**Live verification**: reloading thread #1 after the change, *"HTML renders fine."* will no longer appear in bold, and will instead be displayed literally as `<b>HTML renders fine.</b>` because the browser receives `&lt;b&gt;HTML renders fine.&lt;/b&gt;`.

> **Note**: if a real-world application needs to support rich HTML formatting (bold, links, lists), manually filtering keywords like `<script>` is not enough: you must use a dedicated allow-list-based HTML parser/sanitizer (e.g., *HTML Purifier*).

### Defense-in-Depth
Even after fixing the PHP code, it is good practice to add two infrastructure-level protections:
1. **Protect session cookies** in `apache/Dockerfile`:
   ```ini
   session.cookie_httponly = 1
   session.cookie_samesite = Lax
   ```
2. **Configure a Content Security Policy (CSP)** in `apache/apache-vhost.conf` (with `mod_headers` enabled) to block inline scripts and unauthorized execution:
   ```apache
   Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; object-src 'none';"
   ```
