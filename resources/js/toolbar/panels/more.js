import { fill, h, link } from '../dom.js';
import { combinationOf, settings } from '../settings.js';

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
    const corners = ['bottom-left', 'bottom-right'].map((position) =>
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
            position === 'bottom-left' ? t.bottom_left : t.bottom_right,
        ),
    );

    // Shortcut: what it is, Change (press the new keys), Turn off.
    const said = h('p', { class: 'muted' });
    const hide = h('button', { type: 'button', onclick: () => actions.hide() }, t.hide);
    const hideNote = h('p', { class: 'muted' }, t.hide_needs_shortcut);

    const show = (shortcut) => {
        said.textContent = shortcut ? fill(t.shortcut_is, { keys: shortcut }) : t.no_shortcut;
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
        h('h3', {}, t.toolbar_settings),
        h('p', { class: 'muted' }, t.settings_note),
        h('fieldset', { class: 'setting' }, h('legend', {}, t.corner), h('div', { class: 'choices' }, corners)),
        h('fieldset', { class: 'setting' }, h('legend', {}, t.shortcut), said, h('div', { class: 'choices' }, change, off)),
        h('ul', { class: 'menu' }, h('li', {}, hide)),
        hideNote,
    ];
}
