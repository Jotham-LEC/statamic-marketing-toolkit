import { fill, h, link } from '../dom.js';
import { POSITIONS, combinationOf, settings } from '../settings.js';

/**
 * Links into the control panel, "Refresh this page's cache", and the
 * toolbar's own settings for this browser: its corner, its shortcut, and
 * hiding it (the shortcut brings it back, so hiding needs one).
 */
export default function more(data, t, actions) {
    const status = h('p', { role: 'status', class: 'muted' });
    const { more, user } = data;
    const current = settings();

    const post = async (url, button, done, failed) => {
        button.disabled = true;

        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': user.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ url: location.href }),
            });
            status.textContent = response.ok ? done : failed;
        } catch {
            status.textContent = failed;
        } finally {
            button.disabled = false;
        }
    };

    // Corner: two buttons, the current one pressed.
    const corners = POSITIONS.map((position) =>
        h(
            'button',
            {
                type: 'button',
                class: 'choice',
                'aria-pressed': String(current.position === position),
                onclick: (event) => {
                    for (const button of corners) button.setAttribute('aria-pressed', String(button === event.currentTarget));
                    actions.position(position);
                },
            },
            t[position.replace('-', '_')],
        ),
    );

    // Shortcut: what it is, Change (press the new keys), Turn off.
    const said = h('p', { class: 'muted' });
    const hide = h('button', { type: 'button', class: 'choice', onclick: () => actions.hide() }, t.hide);
    const hideExplained = h('p', { class: 'muted' });
    const hideNote = h('p', { class: 'muted' }, t.hide_needs_shortcut);

    const show = (shortcut) => {
        said.textContent = shortcut ? fill(t.shortcut_is, { keys: shortcut }) : t.no_shortcut;
        hideExplained.textContent = shortcut ? fill(t.hide_explained, { keys: shortcut }) : '';
        hideExplained.hidden = !shortcut;
        hide.hidden = !shortcut;
        hideNote.hidden = Boolean(shortcut);
    };

    const change = h(
        'button',
        {
            type: 'button',
            class: 'choice',
            onclick: (event) => {
                const button = event.currentTarget;
                said.textContent = t.recording;
                button.setAttribute('aria-pressed', 'true');

                const listen = (key) => {
                    if (key.key === 'Escape' || key.key === 'Tab') {
                        // Escape stops recording only; the panel stays open.
                        if (key.key === 'Escape') key.stopPropagation();
                        stop();
                        show(settings().shortcut);
                        return;
                    }

                    const combination = combinationOf(key);
                    if (!combination) return;

                    key.preventDefault();
                    key.stopPropagation();
                    stop();
                    actions.shortcut(combination);
                    show(combination);
                };
                const stop = () => {
                    button.removeEventListener('keydown', listen);
                    button.setAttribute('aria-pressed', 'false');
                };

                button.addEventListener('keydown', listen);
            },
        },
        t.change_shortcut,
    );

    const off = h('button', { type: 'button', class: 'choice', onclick: () => (actions.shortcut(null), show(null)) }, t.remove_shortcut);

    show(current.shortcut);

    const panel = h(
        'div',
        { id: 'mt-settings', class: 'settings', hidden: true },
        h('p', { class: 'muted' }, t.settings_note),
        h('fieldset', { class: 'setting' }, h('legend', {}, t.corner), h('div', { class: 'choices' }, corners)),
        h('fieldset', { class: 'setting' }, h('legend', {}, t.shortcut), said, h('div', { class: 'choices' }, change, off)),
        h('div', { class: 'setting' }, hideExplained, h('div', { class: 'choices' }, hide), hideNote),
    );

    return [
        h(
            'ul',
            { class: 'menu' },
            h('li', {}, link(more.dashboard_url, t.dashboard)),
            more.cache
                ? h('li', {}, h('button', { type: 'button', onclick: (event) => post(more.cache_url, event.currentTarget, t.cache_refreshed, t.cache_failed) }, t.refresh_cache))
                : null,
        ),
        status,
        // The settings, folded away until asked for.
        h(
            'button',
            {
                type: 'button',
                class: 'disclosure',
                'aria-expanded': 'false',
                'aria-controls': 'mt-settings',
                onclick: (event) => {
                    const open = event.currentTarget.getAttribute('aria-expanded') !== 'true';
                    event.currentTarget.setAttribute('aria-expanded', String(open));
                    panel.hidden = !open;
                },
            },
            t.toolbar_settings,
        ),
        panel,
    ];
}
