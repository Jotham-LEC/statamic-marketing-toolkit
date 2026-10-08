import { bar, panelsOf } from './bar.js';
import { fill } from './dom.js';
import { SHORTCUT, pressed, save, settings } from './settings.js';
import styles from './styles.css?inline';

/*
 * The guard that <s:mt:body /> prints loads this script, but only while the
 * mt_toolbar cookie says a control panel user is signed in. The script asks the
 * endpoint about this page, then draws the toolbar in a Shadow DOM, so the
 * site's CSS and the toolbar's never meet.
 *
 * Every page load starts afresh, so the bar is drawn at once from what the last
 * page kept (its theme, labels and items, but nothing about a page). It is then
 * replaced by this page's bar as soon as the endpoint answers. When the toolbar
 * is hidden (with More → Hide the toolbar), it asks nothing and draws nothing
 * until its shortcut is pressed.
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

/** Returns what the next page draws while it loads: the user's settings and labels, and which items there were. */
const shellOf = (data) => ({
    user: { ...data.user, csrf: null },
    page: { type: data.page.type, edit_url: Boolean(data.page.edit_url), seo_url: Boolean(data.page.seo_url) },
    more: { dashboard_url: Boolean(data.more.dashboard_url), cache: data.more.cache },
    panels: panelsOf(data),
});

const forget = () => {
    document.cookie = 'mt_toolbar=; Max-Age=0; Path=/; SameSite=Lax';
    store(null);
};

let sheet = null;

/*
 * Gives the shadow root the styles as a constructed stylesheet. The sheet is
 * built through the CSSOM, which a Content Security Policy doesn't govern, so a
 * `style-src` without 'unsafe-inline' (a nonce-only one) still lets the toolbar
 * be drawn. Every bar drawn on the page shares the one sheet.
 */
function styled(root) {
    if (!sheet) {
        sheet = new CSSStyleSheet();
        sheet.replaceSync(styles);
    }

    root.adoptedStyleSheets = [sheet];
}

function host() {
    const element = document.createElement('div');
    element.id = 'mt-toolbar';
    const root = element.attachShadow({ mode: 'open' });
    styled(root);
    document.body.append(element);

    return { element, root };
}

/** Shows a short message in the corner, which is read out and goes away by itself. */
function notice(text) {
    const { element, root } = host();
    const p = document.createElement('p');
    p.className = 'notice';
    p.setAttribute('role', 'status');
    root.append(p);
    // The text is filled in after the element is in the page, so screen readers announce it.
    setTimeout(() => (p.textContent = text), 50);
    setTimeout(() => element.remove(), 6000);
}

const typing = (event) => {
    const target = event.composedPath()[0];

    return target instanceof HTMLElement && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName));
};

let drawn = null;

/** Takes the drawn bar away, with the listeners it put on the page. */
function undraw() {
    drawn?.toolbar.destroy();
    drawn?.element.remove();
    drawn = null;
}

/** Draws a bar, then takes the one before it away, so nothing flickers between them. */
function draw(data) {
    const previous = drawn;
    const { element, root } = host();
    const toolbar = bar(root, data, {
        hidden: () => {
            undraw();
            notice(fill(data.user.labels.hidden, { keys: SHORTCUT }));
        },
    });

    if (previous) {
        if (previous.toolbar.focused()) toolbar.focus();
        previous.toolbar.destroy();
        previous.element.remove();
    }

    drawn = { element, toolbar };
}

function boot() {
    if (!endpoint || window.mtToolbar) return;
    window.mtToolbar = true;

    // Added here, after the check above, so a script loaded twice listens once.
    document.addEventListener('keydown', (event) => {
        if (!pressed(event) || typing(event)) return;

        event.preventDefault();

        if (drawn) {
            drawn.toolbar.toggle();
        } else if (settings().hidden) {
            // When the toolbar is hidden, the shortcut brings it back, open.
            save({ hidden: false, open: true });
            load();
        }
    });

    if (!settings().hidden) load();
}

async function load() {
    const status = performance.getEntriesByType?.('navigation')[0]?.responseStatus;
    const query = new URLSearchParams({ url: location.href });
    if (status) query.set('status', status);

    // The request is sent first, and the bar is drawn while the answer is on its way.
    const answer = fetch(endpoint + '?' + query, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const shell = stored();

    if (shell) draw({ ...shell, pending: true });

    let response;

    try {
        response = await answer;
    } catch {
        undraw();
        return;
    }

    // When the user is signed out (or the toolbar was switched off), this browser shows nothing more
    // until the next sign-in.
    if (response.status === 401 || response.status === 404) {
        undraw();
        forget();
        if (response.status === 401) notice((await response.json().catch(() => ({}))).message ?? '');
        return;
    }

    let data;

    try {
        if (!response.ok) throw new Error();
        data = await response.json();
    } catch {
        undraw();
        notice(shell?.user.labels.error ?? 'The toolbar couldn’t load this page’s details. Reload the page to try again.');
        return;
    }

    draw(data);
    store(shellOf(data));
}

// The guard runs inside <body>, so the bar can be drawn before the rest of the page has loaded.
if (document.body) boot();
else document.addEventListener('DOMContentLoaded', boot);
