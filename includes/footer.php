        </main>
    </div>
</div>
<script>
(function () {
    const toggle = document.getElementById('themeToggle');
    const root = document.documentElement;

    const updateButton = (theme) => {
        if (!toggle) return;
        const icon = toggle.querySelector('.theme-toggle__icon');
        const label = toggle.querySelector('.theme-toggle__label');
        if (icon) icon.textContent = theme === 'dark' ? '☀️' : '🌙';
        if (label) label.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
        toggle.setAttribute('aria-pressed', String(theme === 'dark'));
    };

    const currentTheme = root.getAttribute('data-theme') || 'light';
    updateButton(currentTheme);

    if (toggle) {
        toggle.addEventListener('click', () => {
            const nextTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', nextTheme);
            localStorage.setItem('lms-theme', nextTheme);
            updateButton(nextTheme);
        });
    }
})();

// Page loading UI: full-screen brand loader on first paint + a slim
// top progress bar that also reappears on internal navigation, since
// this app reloads a full page for every click. Purely presentational.
(function () {
    var loader = document.getElementById('pageLoader');
    var bar = document.getElementById('pageProgressBar');
    var html = document.documentElement;
    var progressTimer = null;

    function startBar() {
        if (!bar) return;
        clearInterval(progressTimer);
        bar.classList.add('is-active');
        var w = 0;
        bar.style.width = '0%';
        progressTimer = setInterval(function () {
            w += (90 - w) / 10;
            bar.style.width = Math.min(w, 90).toFixed(1) + '%';
        }, 150);
    }

    function finishLoad() {
        clearInterval(progressTimer);
        html.classList.add('is-loaded');
        if (loader) loader.classList.add('is-hidden');
        if (bar) {
            bar.style.width = '100%';
            setTimeout(function () {
                bar.classList.remove('is-active');
                bar.style.width = '0%';
            }, 350);
        }
    }

    startBar();

    if (document.readyState === 'complete') {
        setTimeout(finishLoad, 200);
    } else {
        window.addEventListener('load', function () { setTimeout(finishLoad, 200); });
    }
    // Safety net: never let the loader get stuck on a slow/blocked event.
    setTimeout(finishLoad, 4000);

    function isInternalNavigableLink(a) {
        if (a.target === '_blank' || a.hasAttribute('download')) return false;
        var href = a.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('javascript:') ||
            href.startsWith('mailto:') || href.startsWith('tel:')) return false;
        try {
            var url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) return false;
            var samePage = url.pathname === window.location.pathname && url.search === window.location.search;
            if (samePage && url.hash) return false;
        } catch (e) { return false; }
        return true;
    }

    function replayLoader() {
        html.classList.remove('is-loaded');
        if (loader) loader.classList.remove('is-hidden');
        startBar();
    }

    document.querySelectorAll('a[href]').forEach(function (a) {
        if (isInternalNavigableLink(a)) {
            a.addEventListener('click', replayLoader);
        }
    });

    document.querySelectorAll('form').forEach(function (f) {
        f.addEventListener('submit', replayLoader);
    });
})();

// Mobile off-canvas sidebar. Purely presentational — toggles CSS
// classes only, does not read/write any session or form data.
(function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar || !sidebarToggle) return;

    const openSidebar = () => {
        sidebar.classList.add('is-open');
        if (overlay) overlay.classList.add('is-visible');
        sidebarToggle.setAttribute('aria-expanded', 'true');
    };
    const closeSidebar = () => {
        sidebar.classList.remove('is-open');
        if (overlay) overlay.classList.remove('is-visible');
        sidebarToggle.setAttribute('aria-expanded', 'false');
    };

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
    });
    if (overlay) overlay.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('.nav-link, .logout-link').forEach((link) => {
        link.addEventListener('click', closeSidebar);
    });
})();
</script>
</body>
</html>
