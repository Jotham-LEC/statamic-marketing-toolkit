import { h, link, say } from '../dom.js';

export default function redirects(data, t) {
    const { redirects } = data;

    return [
        say(redirects.messages),
        redirects.to_here.length
            ? h(
                  'ul',
                  { class: 'rows' },
                  redirects.to_here.map((redirect) => h('li', {}, link(redirect.url, redirect.source), h('span', { class: 'muted' }, String(redirect.hits)))),
              )
            : null,
        redirects.ignored ? h('p', {}, link(redirects.ignored.url, redirects.ignored.source)) : null,
        h('p', { class: 'actions' }, link(redirects.create_url, t.add_redirect, { class: 'button' })),
    ];
}
