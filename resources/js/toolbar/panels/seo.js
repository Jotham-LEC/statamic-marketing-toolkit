import { fill, h, link, say, tone } from '../dom.js';

export default function seo(data, t) {
    const { seo } = data;

    return [
        seo.score != null
            ? h('p', { class: 'score' }, h('span', { class: 'badge big ' + tone(seo.score), 'aria-hidden': 'true' }, String(seo.score)), h('span', { class: 'sr' }, fill(t.score, { score: seo.score })))
            : null,
        say(seo.messages),
        seo.issues.length
            ? h(
                  'ul',
                  { class: 'issues' },
                  seo.issues.map((issue) =>
                      h(
                          'li',
                          { class: issue.status },
                          h('span', { class: 'status' }, issue.status === 'fail' ? t.fail : t.warn),
                          ' ',
                          link(issue.url, issue.label) ?? h('strong', {}, issue.label),
                          h('span', { class: 'message' }, issue.message),
                      ),
                  ),
              )
            : null,
        seo.search ? h('p', { class: 'muted' }, seo.search) : null,
        h('p', { class: 'actions' }, link(seo.report_url, t.open_report), link(seo.run_url, t.run_report)),
    ];
}
