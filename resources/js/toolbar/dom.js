/**
 * The few DOM helpers the toolbar needs. Text always goes in as text, never
 * as HTML: titles, addresses and messages come from the site's content.
 */
export function h(tag, attrs = {}, ...children) {
    const el = document.createElement(tag);

    for (const [key, value] of Object.entries(attrs)) {
        if (key.startsWith('on')) el.addEventListener(key.slice(2), value);
        else if (value === true) el.setAttribute(key, '');
        else if (value !== false && value != null) el.setAttribute(key, value);
    }

    el.append(...children.flat(Infinity).filter((child) => child != null && child !== false));

    return el;
}

/** An address the toolbar may link to: the site's or the control panel's, over http(s). */
export const safe = (url) => (typeof url === 'string' && /^(https?:\/\/|\/(?!\/))/i.test(url) ? url : null);

export const link = (url, text, attrs = {}) => (safe(url) ? h('a', { href: safe(url), ...attrs }, text) : null);

export const p = (text, attrs = {}) => h('p', attrs, text);

/** Sentences, one paragraph each. */
export const say = (messages, attrs = {}) => (messages ?? []).map((message) => p(message, attrs));

/** Fills in `:name` placeholders, as Laravel's translations do. */
export const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.replaceAll(':' + key, value), text);

/** "a, b and c". */
export const list = (items, and) => (items.length < 2 ? items.join('') : items.slice(0, -1).join(', ') + ' ' + and + ' ' + items.at(-1));

/** A score's band, as the control panel's Score badge colours it. */
export { band as tone } from '../shared/score.js';
