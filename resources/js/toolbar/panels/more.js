import { fill, h } from '../dom.js';
import { POSITIONS, SHORTCUT, settings } from '../settings.js';

/**
 * Builds the panel for the toolbar's own settings in this browser: its corner,
 * and hiding it (the shortcut brings it back).
 */
export default function more(data, t, actions) {
    const current = settings();

    // Each corner gets a button, and the current one is shown pressed.
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

    return [
        h('p', { class: 'muted' }, t.settings_note),
        h('fieldset', { class: 'setting' }, h('legend', {}, t.corner), h('div', { class: 'choices' }, corners)),
        h('div', { class: 'setting' }, h('h3', {}, t.shortcut), h('p', { class: 'muted' }, fill(t.shortcut_is, { keys: SHORTCUT }))),
        h(
            'div',
            { class: 'setting' },
            h('p', { class: 'muted' }, fill(t.hide_explained, { keys: SHORTCUT })),
            h('div', { class: 'choices' }, h('button', { type: 'button', class: 'choice', onclick: () => actions.hide() }, t.hide)),
        ),
    ];
}
