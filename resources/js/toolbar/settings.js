/*
 * The toolbar's settings, kept in this browser (localStorage) and changed in
 * its More panel: its corner, its shortcut, whether it is hidden, and
 * whether it was left open. Nothing of them reaches the server.
 */
const KEY = 'mt-toolbar';
export const POSITIONS = ['bottom-left', 'bottom-right', 'top-left', 'top-right'];
const DEFAULTS = { position: 'bottom-left', shortcut: 'Alt+Shift+M', hidden: false, open: false };

/** A key combination: one or more modifiers, then a letter or a digit. */
export const COMBINATION = /^((Ctrl|Alt|Shift|Meta)\+)+[A-Z0-9]$/;

export function settings() {
    let saved = {};

    try {
        saved = JSON.parse(localStorage.getItem(KEY) ?? '{}') ?? {};
    } catch {}

    const merged = { ...DEFAULTS, ...saved };

    return {
        position: POSITIONS.includes(merged.position) ? merged.position : DEFAULTS.position,
        // null: no shortcut. Anything that isn't a combination is the default.
        shortcut: merged.shortcut === null ? null : COMBINATION.test(merged.shortcut) ? merged.shortcut : DEFAULTS.shortcut,
        hidden: merged.hidden === true && merged.shortcut !== null,
        open: merged.open === true,
    };
}

export function save(changes) {
    try {
        localStorage.setItem(KEY, JSON.stringify({ ...settings(), ...changes }));
    } catch {}
}

/** "Alt+Shift+M" → a test for a keydown, by the key's place on the keyboard (so Option on a Mac works). */
export function matcher(combination) {
    if (!combination) return () => false;

    const parts = combination.split('+');
    const key = parts.pop();
    const code = /^\d$/.test(key) ? 'Digit' + key : 'Key' + key;
    const flags = { Ctrl: 'ctrlKey', Alt: 'altKey', Shift: 'shiftKey', Meta: 'metaKey' };

    return (event) => event.code === code && Object.entries(flags).every(([name, flag]) => event[flag] === parts.includes(name));
}

/** The combination a keydown makes, or null while it is only modifiers, or has none. */
export function combinationOf(event) {
    const key = /^Key([A-Z])$/.exec(event.code)?.[1] ?? /^Digit(\d)$/.exec(event.code)?.[1];
    const modifiers = [['Ctrl', event.ctrlKey], ['Alt', event.altKey], ['Shift', event.shiftKey], ['Meta', event.metaKey]].filter(([, on]) => on).map(([name]) => name);

    return key && modifiers.length ? [...modifiers, key].join('+') : null;
}
