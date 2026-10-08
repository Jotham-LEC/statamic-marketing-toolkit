import { fill, h, safe, tone } from './dom.js';
import { icon } from './icons.js';
import more from './panels/more.js';
import preview from './panels/preview.js';
import redirects from './panels/redirects.js';
import seo from './panels/seo.js';
import sites from './panels/sites.js';
import tracking from './panels/tracking.js';
import { save, settings } from './settings.js';

const PANELS = { seo, preview, redirects, tracking, sites, more };

/** Returns the panels this data has something for, in the bar's order. */
export const panelsOf = (data) => Object.keys(PANELS).filter((name) => (name === 'sites' ? data.sites?.length : name === 'more' || data[name]));

/**
 * Applies the user's control panel theme to the bar. Each colour, light and
 * dark, becomes a variable the stylesheet reads, and the stylesheet uses its own
 * colours where one is missing.
 */
function paint(nav, theme) {
    for (const mode of ['light', 'dark']) {
        for (const [name, color] of Object.entries(theme?.[mode] ?? {})) {
            nav.style.setProperty(`--${mode[0]}-${name}`, color);
        }
    }
}

/**
 * Draws the bar. It has a toggle in its corner (the mark and the score) and,
 * once open, a bar as wide as its items beside it. Each item is an icon, with
 * its name as a tooltip and for screen readers: the edit links, a button per
 * panel, and Minimise. While the bar is open, the score itself opens the SEO
 * panel. Panels are disclosures, so only one is open at a time, and they open
 * away from the corner (which can be any of the four). Below 640 px, the open
 * bar is a bottom sheet with every panel in it, and its items are named in full.
 *
 * While pending (`data.pending`), the bar is drawn from the last page's items,
 * disabled, while this page's details load, so the bar stays where it was
 * between pages.
 */
export function bar(root, data, hooks) {
    const t = data.user.labels;
    const page = data.page;
    const pending = Boolean(data.pending);
    const score = data.seo?.score;
    const panels = pending ? data.panels : panelsOf(data);
    const buttons = {};
    const sections = {};
    let current = null;
    // This ends the listeners this bar puts on the page, once another bar replaces it.
    const listening = new AbortController();

    // These are the changes the More panel makes. They are kept in this browser and applied at once.
    const actions = {
        position: (position) => {
            save({ position });
            nav.dataset.position = position;
        },
        hide: () => {
            save({ hidden: true, open: false });
            hooks.hidden();
        },
    };

    const badge = pending
        ? h('span', { class: 'badge', 'aria-hidden': 'true' }, '…')
        : page.missing
          ? h('span', { class: 'badge poor' }, t.missing)
          : score != null
            ? h('span', { class: 'badge ' + tone(score), 'aria-hidden': 'true' }, String(score))
            : null;

    const toggle = h(
        'button',
        { type: 'button', class: 'toggle' + (page.missing ? ' missing' : ''), 'aria-controls': 'mt-tray', 'aria-expanded': 'false', 'aria-busy': pending ? 'true' : null },
        icon('mark'),
        badge,
        h('span', { class: 'sr' }, t.open, score != null ? '. ' + fill(t.score, { score }) : ''),
        h('span', { class: 'closing', 'aria-hidden': 'true' }, t.minimise),
    );

    const open = (name) => {
        if (current) {
            buttons[current].setAttribute('aria-expanded', 'false');
            sections[current].classList.remove('open');
        }

        current = name === current ? null : name;

        if (current) {
            buttons[current].setAttribute('aria-expanded', 'true');
            sections[current].classList.add('open');
            sections[current].querySelector('h2').focus();
        }
    };

    /** Creates an item of the bar: its icon, and its name as a tooltip and, in the sheet, as text. */
    const item = (tag, name, label, attrs) =>
        h(tag, { 'aria-label': label, 'data-tip': label, ...attrs }, icon(name), h('span', { class: 'label', 'aria-hidden': 'true' }, label));

    for (const name of panels) {
        const id = 'mt-panel-' + name;

        // The score badge opens the SEO panel, so the panel has no button of its own.
        buttons[name] = name === 'seo' ? toggle : item('button', name, t.panels[name], pending ? { type: 'button', disabled: true } : { type: 'button', 'aria-expanded': 'false', 'aria-controls': id, onclick: () => open(name) });

        if (!pending) {
            sections[name] = h(
                'section',
                { id, class: 'panel', role: 'region', 'aria-labelledby': id + '-title' },
                h('header', {}, h('h2', { id: id + '-title', tabindex: '-1' }, t.panels[name]), h('button', { type: 'button', class: 'close', 'aria-label': t.close_panel, onclick: () => (open(null), buttons[name].focus()) }, '×')),
                h('div', { class: 'body' }, PANELS[name](data, t, actions)),
            );
        }
    }

    const links = [
        ['edit', page.type === 'term' ? t.edit_term : t.edit_entry, page.edit_url],
        ['seo_tab', t.seo, page.seo_url],
    ].filter(([, , url]) => (pending ? url : safe(url)));

    // This shows a short answer beside the bar, which is read out, saying what Refresh this page's cache did.
    const toast = h('p', { class: 'toast', role: 'status' });
    let quiet;
    const say = (text) => {
        toast.textContent = text;
        clearTimeout(quiet);
        quiet = setTimeout(() => (toast.textContent = ''), 5000);
    };

    const refresh = async (event) => {
        const button = event.currentTarget;
        button.disabled = true;

        try {
            const response = await fetch(data.more.cache_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': data.user.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ url: location.href }),
            });
            say(response.ok ? t.cache_refreshed : t.cache_failed);
        } catch {
            say(t.cache_failed);
        } finally {
            button.disabled = false;
        }
    };

    // The items are ordered by how often each is wanted, from the score outwards: the control panel
    // and this page's edit screens, the panels about the page, then refreshing its cache and the settings.
    const dashboard = data.more?.dashboard_url
        ? h('li', { class: 'link' }, pending ? item('button', 'dashboard', t.dashboard, { type: 'button', disabled: true }) : item('a', 'dashboard', t.dashboard, { href: safe(data.more.dashboard_url) }))
        : null;
    const cache = data.more?.cache ? h('li', { class: 'link' }, item('button', 'cache', t.refresh_cache, pending ? { type: 'button', disabled: true } : { type: 'button', onclick: refresh })) : null;

    const tray = h(
        'div',
        { id: 'mt-tray', class: 'tray' },
        h(
            'ul',
            { class: 'items' },
            dashboard,
            links.map(([name, label, url]) => h('li', { class: 'link' }, pending ? item('button', name, label, { type: 'button', disabled: true }) : item('a', name, label, { href: safe(url) }))),
            panels.filter((name) => name !== 'seo' && name !== 'more').map((name) => h('li', { class: 'tab' }, buttons[name])),
            cache,
            panels.includes('more') ? h('li', { class: 'tab' }, buttons.more) : null,
        ),
        // Minimise folds the bar back to its corner button, which stays on every page. Hide, in More,
        // takes that button away too.
        h('button', { type: 'button', class: 'minimise', 'aria-label': t.close, 'data-tip': t.minimise, onclick: () => expand(false) }, icon('minimise')),
        h('div', { class: 'panels' }, panels.filter((name) => sections[name]).map((name) => sections[name])),
    );

    const nav = h('nav', { class: 'mt', 'aria-label': t.name, 'data-position': settings().position, 'data-theme': data.user.color_mode }, toggle, tray, toast);
    paint(nav, data.user.theme);

    const narrow = matchMedia('(max-width: 639.98px)');

    // While the bar is open, the toggle is the SEO panel's button (on a wide screen, where there is one).
    const scoreButton = () => Boolean(sections.seo) && !narrow.matches;

    const expand = (expanded, focus = true) => {
        if (!expanded) open(null);

        nav.classList.toggle('expanded', expanded);
        const score = expanded && scoreButton();
        toggle.setAttribute('aria-controls', score ? 'mt-panel-seo' : 'mt-tray');
        toggle.setAttribute('aria-expanded', String(score ? current === 'seo' : expanded));
        toggle.querySelector('.sr').firstChild.textContent = score ? t.panels.seo : expanded ? t.close : t.open;
        score ? toggle.setAttribute('data-tip', t.panels.seo) : toggle.removeAttribute('data-tip');
        save({ open: expanded });

        if (!expanded) {
            if (focus) toggle.focus();
        } else if (!pending && page.missing && panels.includes('redirects') && !current && !narrow.matches) {
            open('redirects');
        }
    };

    toggle.addEventListener('click', () => {
        if (!nav.classList.contains('expanded')) expand(true);
        else if (scoreButton()) open('seo');
        else expand(false);
    });

    // Escape closes the open panel and returns focus to its button. Pressed again, it closes the bar.
    nav.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        if (current && !narrow.matches) {
            const name = current;
            open(null);
            buttons[name].focus();
        } else if (nav.classList.contains('expanded')) {
            expand(false);
        }

        event.stopPropagation();
    });

    // A click elsewhere on the page closes the open panel.
    document.addEventListener(
        'pointerdown',
        (event) => {
            if (current && !event.composedPath().includes(nav) && !narrow.matches) open(null);
        },
        { signal: listening.signal },
    );

    root.append(nav);
    expand(settings().open, false);

    return {
        toggle: () => {
            expand(!nav.classList.contains('expanded'));
            toggle.focus();
        },
        focus: () => toggle.focus(),
        /** Returns whether focus is on the toggle, so the bar replacing this one can take it over. */
        focused: () => root.activeElement === toggle,
        /** Takes the bar's listeners off the page, before the bar is removed. */
        destroy: () => listening.abort(),
    };
}
