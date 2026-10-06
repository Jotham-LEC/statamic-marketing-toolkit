import { components, conditions, config, hooks, inertia, stacks, toast } from '@statamic/cms/api';
import { router } from '@statamic/cms/inertia';
import RedirectConfirm from './components/RedirectConfirm.vue';
import SeoPreviewFieldtype from './components/SeoPreviewFieldtype.vue';
import SeoWidget from './components/SeoWidget.vue';
import Features from './pages/Features.vue';
import NotFound from './pages/NotFound.vue';
import Overview from './pages/Overview.vue';
import RedirectForm from './pages/RedirectForm.vue';
import Report from './pages/Report.vue';
import Reports from './pages/Reports.vue';
import SearchConsole from './pages/SearchConsole.vue';
import Redirects from './pages/Redirects.vue';
import { useAxios } from './util.js';

/**
 * The current Inertia page: the one the CP loaded with, then each one it
 * navigates to. (Inertia 2's router doesn't expose it outside components.)
 * Relies on Statamic internals: the [data-page] element Inertia boots from,
 * and the edit pages' `collection`/`taxonomy` and `reference` props, which
 * referenceFor() reads. Check them after a Statamic update.
 */
let page = (() => {
    const element = document.querySelector('[data-page]');

    try {
        return JSON.parse(element?.dataset.page || element?.textContent || 'null');
    } catch {
        return null;
    }
})();

/**
 * The saved entry or term the form being saved belongs to. Statamic's
 * `saving` hook carries only the handle and the values, so the reference
 * comes from the edit page, when the form is that page's own. A form in a
 * stack on top of it (a related entry, perhaps of the same collection) can't
 * be told apart, so it isn't asked about; its save adds the redirect.
 */
function referenceFor(payload) {
    if (stacks.count() > 0) return null;

    const props = page?.props ?? {};
    const handle = payload?.collection ?? payload?.taxonomy;
    const owner = props.collection?.handle ?? props.collection ?? props.taxonomy?.handle ?? props.taxonomy;

    return payload?.reference ?? (props.reference && handle && owner === handle ? props.reference : null);
}

/**
 * Before an entry or term form saves: if its address will change, ask whether
 * to leave a 301 behind. The server reads the answer when the save arrives;
 * without one (a save from code), it adds the redirect.
 */
function confirmRedirect(payload) {
    const axios = useAxios();
    const reference = referenceFor(payload);

    // Automatic redirects are Pro: the free edition has nothing to ask.
    if (!reference || !config.get('seo')?.pro) return Promise.resolve();

    return axios
        .post(cp_url('seo/redirects/check'), { reference, values: payload.values })
        .then(({ data }) => {
            if (!data.changes) return;

            return new Promise((resolve, reject) => {
                const modal = components.append('seo-redirect-confirm', { props: { from: data.from, to: data.to } });
                // The first answer counts: a double click doesn't answer twice.
                let settled = false;
                const settle = () => {
                    if (settled) return false;
                    settled = true;
                    modal.destroy();
                    return true;
                };
                const answer = (create) => {
                    // A failed answer leaves the default: the redirect is added.
                    if (settle()) axios.post(cp_url('seo/redirects/choice'), { reference, create }).then(resolve, resolve);
                };

                modal.on('add', () => answer(true));
                modal.on('skip', () => answer(false));
                // Closed, or "not yet": nothing is saved; the next save asks again.
                modal.on('cancel', () => {
                    if (settle()) reject(__('seo::cp.confirm.not_saved'));
                });
            });
        })
        // A failed check never blocks a save; the server adds the redirect by default.
        .catch((error) => (typeof error === 'string' ? Promise.reject(error) : undefined));
}

Statamic.booting(() => {
    components.register('seo_preview-fieldtype', SeoPreviewFieldtype);
    components.register('seo-widget', SeoWidget);
    components.register('seo-redirect-confirm', RedirectConfirm);
    inertia.register('seo::Overview', Overview);
    inertia.register('seo::Redirects', Redirects);
    inertia.register('seo::RedirectForm', RedirectForm);
    inertia.register('seo::NotFound', NotFound);
    inertia.register('seo::Reports', Reports);
    inertia.register('seo::Report', Report);
    inertia.register('seo::SearchConsole', SearchConsole);
    inertia.register('seo::Features', Features);

    router.on('navigate', (event) => {
        page = event.detail.page;
    });

    for (const type of ['entry', 'term']) {
        hooks.on(`${type}.saving`, (resolve, reject, payload) => confirmRedirect(payload).then(resolve, reject));
    }

    // The Tracking tab's warning, as its fields change, and a toast when the brand global is saved.
    conditions.add('seoTrackingOverlap', ({ root, values }) => trackingOverlaps(root ?? values));

    hooks.on('global-set.saving', (resolve, reject, payload) => {
        if (payload?.globalSet === config.get('seo')?.global && trackingOverlaps(payload.values)) {
            setTimeout(() => toast.info(__('seo::cp.tracking.overlap_toast'), { duration: 10000 }), 500);
        }

        resolve();
    });
});

/**
 * Google Tag Manager and another tracker both set (in the form, or in .env):
 * if GTM loads that tracker too, each visit counts twice.
 */
function trackingOverlaps(values) {
    const env = config.get('seo')?.trackingFromConfig ?? {};
    const set = (tracker, field) => Boolean(env[tracker] || String(values?.[field] ?? '').trim());
    const others = [
        ['ga4', 'ga4_id'],
        ['posthog', 'posthog_key'],
        ['meta', 'meta_pixel_id'],
        ['linkedin', 'linkedin_partner_id'],
    ];

    return set('gtm', 'gtm_id') && others.some(([tracker, field]) => set(tracker, field));
}
