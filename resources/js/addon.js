import { components, conditions, config, hooks, inertia, stacks, toast } from '@statamic/cms/api';
import { router } from '@statamic/cms/inertia';
import { PipelineStopped } from '@statamic/cms/save-pipeline';
import RedirectConfirm from './components/RedirectConfirm.vue';
import HeadingFieldtype from './components/HeadingFieldtype.vue';
import SeoPreviewFieldtype from './components/SeoPreviewFieldtype.vue';
import SeoWidget from './components/SeoWidget.vue';
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
    const { automaticRedirects, urls } = config.get('marketingToolkit') ?? {};

    // Automatic redirects switched off: nothing to ask.
    if (!reference || !automaticRedirects) return Promise.resolve();

    return axios
        .post(urls.redirectCheck, { reference, values: payload.values })
        .then(({ data }) => {
            if (!data.changes) return;

            return new Promise((resolve, reject) => {
                const modal = components.append('mt-redirect-confirm', { props: { from: data.from, to: data.to } });
                // The first answer counts: a double click doesn't answer twice.
                let settled = false;
                const settle = () => {
                    if (settled) return false;
                    settled = true;
                    modal.destroy();
                    return true;
                };
                const answer = (create) => {
                    if (!settle()) return;

                    // A failed answer leaves the default: the redirect is added. The save
                    // goes ahead, so say so when that isn't what was chosen.
                    axios.post(urls.redirectChoice, { reference, create }).then(resolve, () => {
                        if (!create) toast.error(__('marketing-toolkit::cp.confirm.choice_failed'), { duration: 10000 });
                        resolve();
                    });
                };

                modal.on('add', () => answer(true));
                modal.on('skip', () => answer(false));
                // Closed, or "not yet": nothing is saved; the next save asks again.
                // PipelineStopped is how Statamic's save stops quietly; anything
                // else it reports as "Something went wrong".
                modal.on('cancel', () => {
                    if (!settle()) return;
                    toast.info(__('marketing-toolkit::cp.confirm.not_saved'));
                    reject(new PipelineStopped());
                });
            });
        })
        // A failed check never blocks a save; the server adds the redirect by default.
        .catch((error) => (error instanceof PipelineStopped ? Promise.reject(error) : undefined));
}

Statamic.booting(() => {
    components.register('mt_preview-fieldtype', SeoPreviewFieldtype);
    components.register('mt_heading-fieldtype', HeadingFieldtype);
    components.register('mt-widget', SeoWidget);
    components.register('mt-redirect-confirm', RedirectConfirm);
    inertia.register('marketing-toolkit::Overview', Overview);
    inertia.register('marketing-toolkit::Redirects', Redirects);
    inertia.register('marketing-toolkit::RedirectForm', RedirectForm);
    inertia.register('marketing-toolkit::NotFound', NotFound);
    inertia.register('marketing-toolkit::Reports', Reports);
    inertia.register('marketing-toolkit::Report', Report);
    inertia.register('marketing-toolkit::SearchConsole', SearchConsole);

    router.on('navigate', (event) => {
        page = event.detail.page;
    });

    for (const type of ['entry', 'term']) {
        hooks.on(`${type}.saving`, (resolve, reject, payload) => confirmRedirect(payload).then(resolve, reject));
    }

    // The Tracking tab's warning, as its fields change, and a toast when the set holding it is saved.
    conditions.add('mtTrackingOverlap', ({ root, values }) => trackingOverlaps(root ?? values));

    // Worked out while saving, which has the values, and shown once saved, which
    // only runs when the save worked (its payload has no values).
    const overlapping = new Set();

    hooks.on('global-set.saving', (resolve, reject, payload) => {
        const handle = payload?.globalSet;
        config.get('marketingToolkit')?.globals?.includes(handle) && trackingOverlaps(payload.values) ? overlapping.add(handle) : overlapping.delete(handle);

        resolve();
    });

    hooks.on('global-set.saved', (resolve, reject, payload) => {
        if (overlapping.delete(payload?.globalSet)) {
            setTimeout(() => toast.info(__('marketing-toolkit::cp.tracking.overlap_toast'), { duration: 10000 }), 500);
        }

        resolve();
    });
});

/**
 * Google Tag Manager and another tracker both set (in the form, or in .env):
 * if GTM loads that tracker too, each visit counts twice.
 */
function trackingOverlaps(values) {
    const { trackingFields: fields = {}, trackingFromConfig: env = {} } = config.get('marketingToolkit') ?? {};
    const set = (tracker) => Boolean(env[tracker] || String(values?.[fields[tracker]] ?? '').trim());

    return set('gtm') && Object.keys(fields).some((tracker) => tracker !== 'gtm' && set(tracker));
}
