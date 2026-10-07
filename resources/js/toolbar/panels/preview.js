import { h, link, say } from '../dom.js';

/** The Google result and the share card, as the SEO tab's preview draws them. */
export default function preview(data, t) {
    const { preview, page } = data;
    let address = null;

    try {
        address = new URL(preview.url);
    } catch {}

    const crumbs = address ? [address.origin, ...address.pathname.split('/').filter(Boolean)].join(' › ') : preview.url;
    const image = preview.image?.url;

    return [
        h('h3', {}, t.google),
        h(
            'div',
            { class: 'google' },
            h('p', { class: 'site' }, preview.site_name),
            h('p', { class: 'crumbs' }, crumbs),
            h('p', { class: 'title' }, preview.title),
            preview.description ? h('p', { class: 'description' }, preview.description) : null,
        ),
        h('h3', {}, t.share),
        h(
            'div',
            { class: 'card' },
            image ? h('img', { src: image, alt: preview.image.alt ?? '', loading: 'lazy' }) : h('p', { class: 'noimage' }, t.no_image),
            h('div', {}, h('p', { class: 'host' }, address?.host ?? ''), h('p', { class: 'title' }, preview.og_title), preview.description ? h('p', { class: 'description' }, preview.description) : null),
        ),
        say(preview.facts),
        h('p', { class: 'actions' }, link(page.seo_url, t.edit_seo)),
    ];
}
