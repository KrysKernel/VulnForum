(function () {
  // 1. Theme switcher (Dark / Light)
  const root = document.documentElement;
  const saved = localStorage.getItem('vf-theme');
  if (saved === 'light' || saved === 'dark') {
    root.setAttribute('data-theme', saved);
  }

  document.addEventListener('DOMContentLoaded', function () {
    const themeBtn = document.getElementById('theme-toggle');
    if (themeBtn) {
      const syncIcon = () => {
        const isLight = root.getAttribute('data-theme') === 'light';
        themeBtn.setAttribute('aria-label', isLight ? 'Switch to dark theme' : 'Switch to light theme');
        themeBtn.querySelector('.icon-sun').style.display = isLight ? 'none' : 'block';
        themeBtn.querySelector('.icon-moon').style.display = isLight ? 'block' : 'none';
      };
      syncIcon();
      themeBtn.addEventListener('click', function () {
        const next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        root.setAttribute('data-theme', next);
        localStorage.setItem('vf-theme', next);
        syncIcon();
      });
    }

    // 2. Password visibility toggles
    document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const input = document.getElementById(btn.getAttribute('data-toggle-pw'));
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      });
    });

    // 3. Upload dropzone filename preview
    const dropzone = document.querySelector('[data-dropzone]');
    if (dropzone) {
      const fileInput = dropzone.querySelector('input[type="file"]');
      const label = dropzone.querySelector('[data-dropzone-label]');
      if (fileInput && label) {
        fileInput.addEventListener('change', function () {
          if (fileInput.files && fileInput.files.length > 0) {
            label.textContent = 'Selected: ' + fileInput.files[0].name;
          }
        });
        ['dragenter', 'dragover'].forEach(function (ev) {
          dropzone.addEventListener(ev, function () { dropzone.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
          dropzone.addEventListener(ev, function () { dropzone.classList.remove('dragover'); });
        });
      }
    }

    // 4. Admin table live search filter
    const tableFilter = document.querySelector('[data-table-filter]');
    if (tableFilter) {
      const target = document.querySelector(tableFilter.getAttribute('data-table-filter'));
      if (target) {
        tableFilter.addEventListener('input', function () {
          const term = tableFilter.value.trim().toLowerCase();
          target.querySelectorAll('tbody tr').forEach(function (row) {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
          });
        });
      }
    }

    // 5. Copy-to-clipboard buttons
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const text = btn.getAttribute('data-copy');
        if (!text) return;
        navigator.clipboard.writeText(text).then(function () {
          const orig = btn.textContent;
          btn.textContent = 'Copied';
          setTimeout(function () { btn.textContent = orig; }, 1200);
        });
      });
    });

    // 6. Admin eval snippet presets
    document.querySelectorAll('[data-snippet]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const editor = document.getElementById('expr');
        if (editor) {
          editor.value = btn.getAttribute('data-snippet');
          editor.focus();
        }
      });
    });

    // 7. Confirm dialog for destructive forms
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
          e.preventDefault();
        }
      });
    });
  });
})();
