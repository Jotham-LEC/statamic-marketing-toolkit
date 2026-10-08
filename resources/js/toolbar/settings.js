/*
 * The toolbar's settings, kept in this browser (localStorage) and changed in
 * its More panel: its corner, whether it is hidden, and whether it was left
 * open. Nothing of them reaches the server.
 */
const KEY = 'mt-toolbar';
export const POSITIONS = ['bottom-left', 'bottom-right', 'top-left', 'top-right'];
const DEFAULTS = { position: 'bottom-left', hidden: false, open: false };

/** The keys that open and close the toolbar, and bring it back once hidden. */
export const SHORTCUT = 'Alt+Shift+M';

export function settings() {
    let saved = {};

    try {
        saved = JSON.parse(localStorage.getItem(KEY) ?? '{}') ?? {};
    } catch {}

    const merged = { ...DEFAULTS, ...saved };

    return {
        position: POSITIONS.includes(merged.position) ? merged.position : DEFAULTS.position,
        hidden: merged.hidden === true,
        open: merged.open === true,
    };
}

export function save(changes) {
    try {
        localStorage.setItem(KEY, JSON.stringify({ ...settings(), ...changes }));
    } catch {}
}

/**
 * Whether a keydown is the shortcut. By the key's place on the keyboard, not
 * the character it types, so Option+Shift+M on a Mac counts too.
 */
export const pressed = (event) => event.code === 'KeyM' && event.altKey && event.shiftKey && !event.ctrlKey && !event.metaKey;
