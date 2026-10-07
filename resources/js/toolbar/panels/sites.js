import { h, link } from '../dom.js';

export default function sites(data, t) {
    return h(
        'ul',
        { class: 'rows' },
        data.sites.map((site) =>
            site.missing
                ? h('li', {}, h('span', {}, site.missing))
                : h(
                      'li',
                      {},
                      h('span', {}, h('strong', {}, site.site), ' ', h('span', { class: 'muted' }, site.status)),
                      h('span', { class: 'links' }, link(site.url, t.view, { 'aria-label': t.view + ' ' + site.site }), link(site.edit_url, t.edit, { 'aria-label': t.edit + ' ' + site.site })),
                  ),
        ),
    );
}
