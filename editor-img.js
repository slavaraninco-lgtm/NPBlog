(function() {
        var wrap = document.getElementById('editorMenuWrap');
        var btn = document.getElementById('editorMenuBtn');
        if (!wrap || !btn) return;
        function toggleMenu() {
            if (wrap.classList.contains('is-open')) {
                closeMenu();
            } else {
                wrap.classList.remove('is-closing');
                wrap.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
            }
        }
        function closeMenu() {
            wrap.classList.add('is-closing');
            wrap.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            setTimeout(() => {
                wrap.classList.remove('is-closing');
            }, 300);
        }
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMenu();
        });
        document.addEventListener('click', function(e) {
            var tutOverlay = document.getElementById('tutorialOverlay');
            if (tutOverlay && tutOverlay.classList.contains('show')) return;
            if (wrap.classList.contains('is-open')) closeMenu();
        });
        var dropdown = wrap.querySelector('.editor-menu-dropdown');
        if (dropdown) {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
                var tutOverlay = document.getElementById('tutorialOverlay');
                if (tutOverlay && tutOverlay.classList.contains('show')) return;
                closeMenu();
            });
        }
    })();

    const themeToggle = document.getElementById('theme-toggle');
    const docEl = document.documentElement;

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            if (typeof openThemeManager === 'function') {
                openThemeManager();
            }
        });
    }