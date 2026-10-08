import { fill, h, link, list, say } from '../dom.js';

const SIGNALS = ['ad_storage', 'analytics_storage', 'ad_user_data', 'ad_personalization'];

/**
 * Works out this browser's Consent Mode state from what the page pushed to
 * dataLayer: the defaults first, then each update in order.
 */
function consentState() {
    const state = {};

    for (const item of window.dataLayer ?? []) {
        if (item && typeof item === 'object' && item[0] === 'consent' && item[2] && typeof item[2] === 'object') {
            for (const signal of SIGNALS) {
                if (item[2][signal] === 'granted' || item[2][signal] === 'denied') state[signal] = item[2][signal];
            }
        }
    }

    return state;
}

export default function tracking(data, t) {
    const { tracking } = data;
    let browser = null;

    if (tracking.consent) {
        const state = consentState();
        const granted = SIGNALS.filter((signal) => state[signal] === 'granted');
        const denied = SIGNALS.filter((signal) => state[signal] === 'denied');
        const values = { granted: list(granted, t.and), denied: list(denied, t.and) };

        browser = !granted.length && !denied.length ? t.consent_unknown : fill(!denied.length ? t.consent_all_granted : !granted.length ? t.consent_all_denied : t.consent_state, values);
    }

    return [say(tracking.messages), browser ? h('p', {}, browser) : null, h('p', { class: 'actions' }, link(tracking.settings_url, t.settings))];
}
