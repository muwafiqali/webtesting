(function () {
    function currentTheme() {
        return document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
    }

    function updateButtons(theme) {
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            var isDark = theme === 'dark';
            var icon = button.querySelector('.theme-icon');
            if (icon) icon.textContent = isDark ? '☀' : '☾';
            button.setAttribute('aria-label', isDark ? 'Aktifkan mode terang' : 'Aktifkan mode malam');
            button.setAttribute('title', isDark ? 'Aktifkan mode terang' : 'Aktifkan mode malam');
        });
    }

    var theme = currentTheme();
    if (!document.documentElement.dataset.theme) {
        try {
            theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        } catch (e) {
            theme = 'light';
        }
        document.documentElement.dataset.theme = theme;
    }
    updateButtons(theme);

    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var nextTheme = currentTheme() === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = nextTheme;
            try {
                localStorage.setItem('izinkantor-theme', nextTheme);
            } catch (e) {}
            updateButtons(nextTheme);
        });
    });
})();
