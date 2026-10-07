import { bar } from './bar.js';
import styles from './styles.css?inline';

/*
 * Loaded by the guard <s:mt:body /> prints, only while the mt_toolbar cookie
 * says a control panel user is signed in. Asks the endpoint about this page,
 * then draws the toolbar in a Shadow DOM, so the site's CSS and the
 * toolbar's never meet.
 */
const script = document.currentScript;
const endpoint = script?.dataset.endpoint;

const forget = () => {
    document.cookie = 'mt_toolbar=; Max-Age=0; Path=/; SameSite=Lax';
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

async function boot() {
    if (!endpoint || document.getElementById('mt-toolbar')) return;

    const status = performance.getEntriesByType?.('navigation')[0]?.responseStatus;
    const query = new URLSearchParams({ url: location.href });
    if (status) query.set('status', status);

    let response;

    try {
        response = await fetch(endpoint + '?' + query, { credentials: 'same-origin', headers: { Accept: 'application/json' }, priority: 'low' });
    } catch {
        return;
    }

    // Signed out (or the toolbar was switched off): nothing more on this browser until the next sign-in.
    if (response.status === 401 || response.status === 404) {
        forget();
        if (response.status === 401) notice((await response.json().catch(() => ({}))).message ?? '');
        return;
    }

    let data;

    try {
        if (!response.ok) throw new Error();
        data = await response.json();
    } catch {
        notice('The toolbar couldn’t load this page’s details. Reload the page to try again.');
        return;
    }

    const { element, root } = host();
    const toolbar = bar(root, data, () => element.remove());
    const pressed = shortcut(data.user.shortcut);

    document.addEventListener('keydown', (event) => {
        if (pressed(event) && !typing(event)) {
            event.preventDefault();
            toolbar.toggle();
        }
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
