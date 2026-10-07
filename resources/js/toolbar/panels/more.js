import { fill, h, link } from '../dom.js';

export default function more(data, t, actions) {
    const status = h('p', { role: 'status', class: 'muted' });
    const { more, user } = data;

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
            return response.ok;
        } catch {
            status.textContent = failed;
        } finally {
            button.disabled = false;
        }
    };

    return [
        h(
            'ul',
            { class: 'menu' },
            h('li', {}, link(more.preferences_url, t.preferences)),
            h('li', {}, link(more.dashboard_url, t.dashboard)),
            more.cache
                ? h('li', {}, h('button', { type: 'button', onclick: (event) => post(more.cache_url, event.currentTarget, t.cache_refreshed, t.cache_failed) }, t.refresh_cache))
                : null,
            h(
                'li',
                {},
                h('button', {
                    type: 'button',
                    onclick: async (event) => {
                        if (await post(more.hide_url, event.currentTarget, t.hidden, t.error)) actions.hidden();
                    },
                }, t.hide),
            ),
        ),
        status,
        h('p', { class: 'muted' }, user.shortcut ? fill(t.shortcut, { keys: user.shortcut }) : t.no_shortcut),
    ];
}
