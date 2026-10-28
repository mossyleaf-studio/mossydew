import { useApi } from './useApi.js';
import { useSession } from './useSession.js';
import { iconSet } from '../icons.js';

export const THEMES = [
    { name: 'moss', background: '#f5f5f3', accent: '#5b7f3a' },
    { name: 'terracotta', background: '#f7f2ec', accent: '#b0532c' },
    { name: 'fjord', background: '#eef2f5', accent: '#2f6a8f' },
    { name: 'plum', background: '#f6f2f6', accent: '#7a4d84' },
    { name: 'night', background: '#1d201b', accent: '#93bb6c' },
];

export const DESIGNS = ['classic', 'cozy'];

const DARK_LUMINANCE = 0.24;

const linear = (channel) => (channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4);

function luminance(color) {
    const [red, green, blue] = [1, 3, 5].map((start) => linear(parseInt(color.slice(start, start + 2), 16) / 255));

    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

export const themeNamed = (name) => THEMES.find((theme) => theme.name === name);

export const presetOf = ({ background, accent }) => THEMES.find((theme) => theme.background === background && theme.accent === accent)?.name ?? '';

export function applyTheme({ background, accent }) {
    const root = document.documentElement.style;
    root.setProperty('--theme-background', background);
    root.setProperty('--theme-accent', accent);
    root.setProperty('color-scheme', luminance(background) < DARK_LUMINANCE ? 'dark' : 'light');
}

export function applyDesign(design) {
    if (design === 'cozy') document.documentElement.dataset.design = design;
    else delete document.documentElement.dataset.design;
}

export function useTheme() {
    const api = useApi();
    const session = useSession();
    const saved = session?.theme ?? THEMES[0];

    async function choose(theme) {
        applyTheme(theme);
        await api.put('/api/me/theme', { background: theme.background, accent: theme.accent });
    }

    async function chooseDesign(design) {
        applyDesign(design);
        await api.put('/api/me/design', { design });
    }

    async function chooseIcons(icons) {
        iconSet.value = icons;
        await api.put('/api/me/icons', { icons });
    }

    return {
        saved: { background: saved.background, accent: saved.accent },
        savedDesign: session?.design ?? DESIGNS[0],
        choose,
        chooseDesign,
        chooseIcons,
    };
}
