/*
 * The toolbar's settings are kept in this browser (localStorage) and changed in
 * its More panel. They hold its corner, whether it is hidden, and whether it was
 * left open. None of them reaches the server.
 */
const KEY = 'mt-toolbar';
export const POSITIONS = ['bottom-left', 'bottom-right', 'top-left', 'top-right'];
const DEFAULTS = { position: 'bottom-left', hidden: false, open: false };

/** These are the keys that open and close the toolbar, and bring it back once hidden. */
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
 * Returns whether a keydown is the shortcut. It checks the key's place on the
 * keyboard, not the character it types, so Option+Shift+M on a Mac counts too.
 */
export const pressed = (event) => event.code === 'KeyM' && event.altKey && event.shiftKey && !event.ctrlKey && !event.metaKey;
