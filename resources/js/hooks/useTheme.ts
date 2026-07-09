import { useCallback, useEffect, useState } from 'react';

type Theme = 'light' | 'dark';

function readInitial(): Theme {
    if (typeof document === 'undefined') return 'light';
    // The pre-hydration script in app.blade.php already set `.dark` on <html>
    // if appropriate — trust it as the source of truth for the initial value.
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

export function useTheme() {
    const [theme, setThemeState] = useState<Theme>(readInitial);

    const applyTheme = useCallback((next: Theme) => {
        const root = document.documentElement;
        if (next === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
        try {
            localStorage.setItem('theme', next);
        } catch {
            // localStorage blocked — theme change is per-page-load only
        }
        setThemeState(next);
    }, []);

    const toggle = useCallback(() => {
        applyTheme(theme === 'dark' ? 'light' : 'dark');
    }, [theme, applyTheme]);

    // React to system-preference changes only when the user hasn't picked
    // an explicit theme yet.
    useEffect(() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = (e: MediaQueryListEvent) => {
            if (localStorage.getItem('theme')) return;
            applyTheme(e.matches ? 'dark' : 'light');
        };
        media.addEventListener('change', handler);
        return () => media.removeEventListener('change', handler);
    }, [applyTheme]);

    return { theme, setTheme: applyTheme, toggle };
}
