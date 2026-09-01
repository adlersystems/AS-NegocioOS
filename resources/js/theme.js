export default function themeToggle() {
    return {
        theme: 'light',

        init() {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            this.theme = stored === 'dark' || stored === 'light'
                ? stored
                : (prefersDark ? 'dark' : 'light');
            this.apply(this.theme, false);
        },

        toggle() {
            this.apply(this.theme === 'dark' ? 'light' : 'dark');
        },

        apply(mode, persist = true) {
            this.theme = mode;

            if (persist) {
                localStorage.setItem('theme', this.theme);
            }

            document.documentElement.classList.toggle('dark', this.theme === 'dark');
        },
    };
}