import { h, safe, tone } from './dom.js';
import { icon } from './icons.js';
import more from './panels/more.js';
import preview from './panels/preview.js';
import redirects from './panels/redirects.js';
import seo from './panels/seo.js';
import sites from './panels/sites.js';
import tracking from './panels/tracking.js';
import { save, settings } from './settings.js';

const PANELS = { seo, preview, redirects, tracking, sites, more };

/** The panels this data has something for, in the bar's order. */
export const panelsOf = (data) => Object.keys(PANELS).filter((name) => (name === 'sites' ? data.sites?.length : name === 'more' || data[name]));

/**
 * The user's control panel theme on the bar: each colour as a variable the
 * stylesheet reads, light and dark, with its own colours where one is missing.
 */
function paint(nav, theme) {
    for (const mode of ['light', 'dark']) {
        for (const [name, color] of Object.entries(theme?.[mode] ?? {})) {
            nav.style.setProperty(`--${mode[0]}-${name}`, color);
        }
    }
}

/**
 * The bar: a toggle in its corner and, once open, the bar along the bottom
 * with an icon for each item (its name as a tooltip and to screen readers)
 * and a button per panel. Panels are disclosures, one open at a time,
 * opening upwards. Below 640 px the open bar is a bottom sheet with every
 * panel in it, its items named in full.
 *
 * Pending (`data.pending`), it is drawn from the last page's items, disabled,
 * while this page's details load: the bar stays where it was between pages.
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

    // What the More panel changes: kept in this browser, applied at once.
    const actions = {
        position: (position) => {
            save({ position });
            nav.dataset.position = position;
        },
        shortcut: (shortcut) => {
            save({ shortcut });
            hooks.shortcut(shortcut);
        },
        hide: () => {
            save({ hidden: true, open: false });
            document.documentElement.style.setProperty('--mt-toolbar-height', '0px');
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
        h('span', { class: 'sr' }, t.open, score != null ? '. ' + t.score.replace(':score', score) : ''),
        h('span', { class: 'closing', 'aria-hidden': 'true' }, t.close_panel),
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

    /** An item of the bar: its icon, its name as a tooltip and, in the sheet, as text. */
    const item = (tag, name, label, attrs) =>
        h(tag, { 'aria-label': label, 'data-tip': label, ...attrs }, icon(name), h('span', { class: 'label', 'aria-hidden': 'true' }, label));

    for (const name of panels) {
        const id = 'mt-panel-' + name;

        buttons[name] = item('button', name, t.panels[name], pending ? { type: 'button', disabled: true } : { type: 'button', 'aria-expanded': 'false', 'aria-controls': id, onclick: () => open(name) });

        if (!pending) {
            sections[name] = h(
                'section',
                { id, class: 'panel', role: 'dialog', 'aria-labelledby': id + '-title' },
                h('header', {}, h('h2', { id: id + '-title', tabindex: '-1' }, t.panels[name]), h('button', { type: 'button', class: 'close', 'aria-label': t.close_panel, onclick: () => (open(null), buttons[name].focus()) }, '×')),
                h('div', { class: 'body' }, PANELS[name](data, t, actions)),
            );
        }
    }

    const links = [
        ['edit', page.type === 'term' ? t.edit_term : t.edit_entry, page.edit_url],
        ['seo_tab', t.seo, page.seo_url],
    ].filter(([, , url]) => (pending ? url : safe(url)));

    const tray = h(
        'div',
        { id: 'mt-tray', class: 'tray' },
        h(
            'ul',
            { class: 'items' },
            links.map(([name, label, url]) => h('li', { class: 'link' }, pending ? item('button', name, label, { type: 'button', disabled: true }) : item('a', name, label, { href: safe(url) }))),
            panels.map((name) => h('li', { class: 'tab' }, buttons[name])),
        ),
        h('div', { class: 'panels' }, panels.filter((name) => sections[name]).map((name) => sections[name])),
    );

    const nav = h('nav', { class: 'mt', 'aria-label': t.name, 'data-position': settings().position, 'data-theme': data.user.color_mode }, toggle, tray);
    paint(nav, data.user.theme);

    const narrow = matchMedia('(max-width: 639.98px)');

    const expand = (expanded, focus = true) => {
        nav.classList.toggle('expanded', expanded);
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.querySelector('.sr').firstChild.textContent = expanded ? t.close : t.open;
        document.documentElement.style.setProperty('--mt-toolbar-height', expanded && !narrow.matches ? '40px' : '0px');
        save({ open: expanded });

        if (!expanded) {
            open(null);
            if (focus) toggle.focus();
        } else if (!pending && page.missing && panels.includes('redirects') && !current && !narrow.matches) {
            open('redirects');
        }
    };

    toggle.addEventListener('click', () => expand(!nav.classList.contains('expanded')));

    // Escape closes the open panel, back to its button; then the bar.
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
    document.addEventListener('pointerdown', (event) => {
        if (current && !event.composedPath().includes(nav) && !narrow.matches) open(null);
    });

    root.append(nav);
    expand(settings().open, false);

    return {
        toggle: () => {
            expand(!nav.classList.contains('expanded'));
            toggle.focus();
        },
        focus: () => toggle.focus(),
        /** Whether focus is on the toggle, so the bar replacing this one can take it over. */
        focused: () => root.activeElement === toggle,
    };
}
