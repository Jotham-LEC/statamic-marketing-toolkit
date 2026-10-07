import { h, link, tone } from './dom.js';
import more from './panels/more.js';
import preview from './panels/preview.js';
import redirects from './panels/redirects.js';
import seo from './panels/seo.js';
import sites from './panels/sites.js';
import tracking from './panels/tracking.js';

const PANELS = { seo, preview, redirects, tracking, sites, more };
const OPEN = 'mt-toolbar-open';

// Lucide's "trending-up" (ISC licence), the toolbar's mark.
const mark = () => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.innerHTML = '<path d="M22 7 13.5 15.5l-5-5L2 17M16 7h6v6"/>';
    return svg;
};

const remember = (open) => {
    try {
        localStorage.setItem(OPEN, open ? '1' : '0');
    } catch {}
};

const remembered = () => {
    try {
        return localStorage.getItem(OPEN) === '1';
    } catch {
        return false;
    }
};

/**
 * The bar: a toggle in its corner and, once open, the bar along the bottom
 * with the edit links and a button per panel. Panels are disclosures, one
 * open at a time, opening upwards. Below 640 px the open bar is a bottom
 * sheet with every panel in it.
 */
export function bar(root, data, onRemove) {
    const t = data.user.labels;
    const page = data.page;
    const score = data.seo?.score;
    const panels = Object.keys(PANELS).filter((name) => (name === 'sites' ? data.sites?.length : name === 'more' || data[name]));
    const buttons = {};
    const sections = {};
    let current = null;

    const toggle = h(
        'button',
        { type: 'button', class: 'toggle' + (page.missing ? ' missing' : ''), 'aria-controls': 'mt-tray', 'aria-expanded': 'false' },
        mark(),
        page.missing ? h('span', { class: 'badge poor' }, t.missing) : score != null ? h('span', { class: 'badge ' + tone(score), 'aria-hidden': 'true' }, String(score)) : null,
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

    for (const name of panels) {
        const id = 'mt-panel-' + name;
        const heading = h('h2', { id: id + '-title', tabindex: '-1' }, t.panels[name]);

        buttons[name] = h('button', { type: 'button', 'aria-expanded': 'false', 'aria-controls': id, onclick: () => open(name) }, t.tabs[name]);
        sections[name] = h(
            'section',
            { id, class: 'panel', role: 'dialog', 'aria-labelledby': id + '-title' },
            h('header', {}, heading, h('button', { type: 'button', class: 'close', 'aria-label': t.close_panel, onclick: () => (open(null), buttons[name].focus()) }, '×')),
            h('div', { class: 'body' }, PANELS[name](data, t, { hidden: () => setTimeout(onRemove, 4000) })),
        );
    }

    const edit = page.edit_url ? link(page.edit_url, page.type === 'term' ? t.edit_term : t.edit_entry, { class: 'edit' }) : null;
    const tray = h(
        'div',
        { id: 'mt-tray', class: 'tray' },
        h(
            'ul',
            { class: 'items' },
            edit ? h('li', { class: 'link' }, edit) : null,
            page.seo_url ? h('li', { class: 'link' }, link(page.seo_url, t.seo)) : null,
            panels.map((name) => h('li', { class: 'tab' }, buttons[name])),
        ),
        h(
            'div',
            { class: 'panels' },
            panels.map((name) => sections[name]),
        ),
    );

    const nav = h('nav', { class: 'mt', 'aria-label': t.name, 'data-position': data.user.position, 'data-theme': data.user.color_mode }, toggle, tray);

    const expand = (expanded, focus = true) => {
        nav.classList.toggle('expanded', expanded);
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.querySelector('.sr').firstChild.textContent = expanded ? t.close : t.open;
        document.documentElement.style.setProperty('--mt-toolbar-height', expanded && !narrow.matches ? '40px' : '0px');
        remember(expanded);

        if (!expanded) {
            open(null);
            if (focus) toggle.focus();
        } else if (page.missing && panels.includes('redirects') && !current && !narrow.matches) {
            open('redirects');
        }
    };

    const narrow = matchMedia('(max-width: 639.98px)');
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

    if (remembered()) expand(true, false);
    else document.documentElement.style.setProperty('--mt-toolbar-height', '0px');

    return {
        toggle: () => {
            expand(!nav.classList.contains('expanded'));
            toggle.focus();
        },
    };
}
