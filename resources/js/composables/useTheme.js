import { computed, ref } from 'vue';

export const THEME_OPTIONS = [
    {
        id: 'light',
        label: 'Светлая',
        group: 'light',
        dark: false,
        swatch: '#ffffff',
        accent: '#b8e62e',
    },
    {
        id: 'ocean',
        label: 'Океан',
        group: 'light',
        dark: false,
        swatch: '#f0f7fb',
        accent: '#0d9488',
    },
    {
        id: 'dark',
        label: 'Тёмная',
        group: 'dark',
        dark: true,
        swatch: '#0a0a0a',
        accent: '#b8e62e',
    },
    {
        id: 'indigo',
        label: 'Индиго',
        group: 'dark',
        dark: true,
        swatch: '#0b1020',
        accent: '#818cf8',
    },
    {
        id: 'graphite',
        label: 'Графит',
        group: 'dark',
        dark: true,
        swatch: '#121417',
        accent: '#94a3b8',
    },
];

const THEME_IDS = new Set(THEME_OPTIONS.map((item) => item.id));

function themeMeta(themeId) {
    return THEME_OPTIONS.find((item) => item.id === themeId) ?? THEME_OPTIONS[0];
}

export function applyTheme(themeId) {
    const meta = themeMeta(themeId);

    document.documentElement.classList.toggle('dark', meta.dark);
    document.documentElement.setAttribute('data-theme', meta.id);
    localStorage.setItem('theme', meta.id);
}

function getInitialTheme() {
    const stored = localStorage.getItem('theme');

    if (THEME_IDS.has(stored)) {
        return stored;
    }

    // Удалённые палитры → ближайший аналог
    if (stored === 'mint' || stored === 'lavender') {
        return 'ocean';
    }

    if (stored === 'crimson') {
        return 'dark';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

const theme = ref(typeof window === 'undefined' ? 'light' : getInitialTheme());

if (typeof window !== 'undefined') {
    applyTheme(theme.value);
}

export function useTheme() {
    const current = computed(() => themeMeta(theme.value));
    const isDark = computed(() => current.value.dark);

    function setTheme(value) {
        if (!THEME_IDS.has(value)) {
            return;
        }

        theme.value = value;
        applyTheme(value);
    }

    function toggleTheme() {
        setTheme(isDark.value ? 'light' : 'dark');
    }

    return {
        theme,
        current,
        isDark,
        options: THEME_OPTIONS,
        toggleTheme,
        setTheme,
    };
}
