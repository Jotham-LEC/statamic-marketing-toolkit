import { bar, panelsOf } from './bar.js';
import styles from './styles.css?inline';

/*
 * Loaded by the guard <s:mt:body /> prints, only while the mt_toolbar cookie
 * says a control panel user is signed in. Asks the endpoint about this page,
 * then draws the toolbar in a Shadow DOM, so the site's CSS and the
 * toolbar's never meet.
 *
 * Every page load starts afresh, so the bar is drawn at once from what the
 * last page kept (its theme, corner, open state and items, nothing about a
 * page), and replaced by this page's as soon as the endpoint answers.
 */
const script = document.currentScript;
const endpoint = script?.dataset.endpoint;
const SHELL = 'mt-toolbar-shell';

const store = (value) => {
    try {
        value === null ? localStorage.removeItem(SHELL) : localStorage.setItem(SHELL, JSON.stringify(value));
    } catch {}
};

const stored = () => {
    try {
        return JSON.parse(localStorage.getItem(SHELL) ?? 'null');
    } catch {
        return null;
    }
};

/** What the next page draws while it loads: the user's settings and labels, and which items there were. */
const shellOf = (data) => ({
    user: { ...data.user, csrf: null },
    page: { type: data.page.type, edit_url: Boolean(data.page.edit_url), seo_url: Boolean(data.page.seo_url) },
    panels: panelsOf(data),
});

const forget = () => {
    document.cookie = 'mt_toolbar=; Max-Age=0; Path=/; SameSite=Lax';
    store(null);
};

function host() {
    const element = document.createElement('div');
    element.id = 'mt-toolbar';
    const root = element.attachShadow({ mode: 'open' });
    const style = document.createElement('style');
    style.textContent = styles;
    root.append(style);
    document.body.append(element);

    return { element, root };
}

/** A short message in the corner, read out, that goes away by itself. */
function notice(text) {
    const { element, root } = host();
    const p = document.createElement('p');
    p.className = 'notice';
    p.setAttribute('role', 'status');
    root.append(p);
    // Filled after it is in the page, so screen readers announce it.
    setTimeout(() => (p.textContent = text), 50);
    setTimeout(() => element.remove(), 6000);
}

/** "Alt+Shift+M" → a test for a keydown, by the key's place on the keyboard (so Option on a Mac works). */
function shortcut(combination) {
    if (!combination) return () => false;

    const parts = combination.split('+');
    const key = parts.pop();
    const code = /^\d$/.test(key) ? 'Digit' + key : 'Key' + key;
    const wants = { Ctrl: 'ctrlKey', Alt: 'altKey', Shift: 'shiftKey', Meta: 'metaKey' };

    return (event) => event.code === code && Object.entries(wants).every(([name, flag]) => event[flag] === parts.includes(name));
}

const typing = (event) => {
    const target = event.composedPath()[0];

    return target instanceof HTMLElement && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName));
};

let drawn = null;
let pressed = () => false;

/** Draws a bar, then takes the one before it away, so nothing flickers between them. */
function draw(data) {
    const previous = drawn;
    const { element, root } = host();
    const toolbar = bar(root, data, () => element.remove());

    if (previous) {
        if (previous.toolbar.focused()) toolbar.focus();
        previous.element.remove();
    }

    drawn = { element, toolbar };
}

document.addEventListener('keydown', (event) => {
    if (drawn && pressed(event) && !typing(event)) {
        event.preventDefault();
        drawn.toolbar.toggle();
    }
});

async function boot() {
    if (!endpoint || window.mtToolbar) return;
    window.mtToolbar = true;

    const status = performance.getEntriesByType?.('navigation')[0]?.responseStatus;
    const query = new URLSearchParams({ url: location.href });
    if (status) query.set('status', status);

    // Asked first, then the bar drawn while the answer is on its way.
    const answer = fetch(endpoint + '?' + query, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const shell = stored();

    if (shell) {
        pressed = shortcut(shell.user.shortcut);
        draw({ ...shell, pending: true });
    }

    let response;

    try {
        response = await answer;
    } catch {
        drawn?.element.remove();
        return;
    }

    // Signed out (or the toolbar was switched off): nothing more on this browser until the next sign-in.
    if (response.status === 401 || response.status === 404) {
        drawn?.element.remove();
        forget();
        if (response.status === 401) notice((await response.json().catch(() => ({}))).message ?? '');
        return;
    }

    let data;

    try {
        if (!response.ok) throw new Error();
        data = await response.json();
    } catch {
        drawn?.element.remove();
        notice(shell?.user.labels.error ?? 'The toolbar couldn’t load this page’s details. Reload the page to try again.');
        return;
    }

    pressed = shortcut(data.user.shortcut);
    draw(data);
    store(shellOf(data));
}

// The guard runs inside <body>, so the bar can be drawn before the rest of the page has loaded.
if (document.body) boot();
else document.addEventListener('DOMContentLoaded', boot);
