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
 * This holds the current Inertia page. It starts as the page the CP loaded with
 * and is replaced by each page the CP navigates to, because Inertia 2's router
 * doesn't expose the page outside components. It relies on Statamic internals:
 * the [data-page] element Inertia boots from, and the edit pages' `collection`,
 * `taxonomy` and `reference` props, which referenceFor() reads. Check them after
 * a Statamic update.
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
 * Returns the saved entry or term that the form being saved belongs to.
 * Statamic's `saving` hook carries only the handle and the values, so the
 * reference comes from the edit page when the form is that page's own. A form
 * in a stack on top of it (a related entry, perhaps of the same collection)
 * can't be told apart, so the user isn't asked about it, and its save adds
 * the redirect.
 */
function referenceFor(payload) {
    if (stacks.count() > 0) return null;

    const props = page?.props ?? {};
    const handle = payload?.collection ?? payload?.taxonomy;
    const owner = props.collection?.handle ?? props.collection ?? props.taxonomy?.handle ?? props.taxonomy;

    return payload?.reference ?? (props.reference && handle && owner === handle ? props.reference : null);
}

/**
 * Asks whether to leave a redirect from the old address to the new one. The
 * promise resolves with 'add', 'skip', or 'cancel' (when the dialog was closed,
 * or the user chose "not yet"). Only the first answer counts, so a double click
 * doesn't answer twice.
 */
function askRedirect(from, to) {
    return new Promise((resolve) => {
        const modal = components.append('mt-redirect-confirm', { props: { from, to } });
        let answered = false;
        const answer = (choice) => {
            if (answered) return;
            answered = true;
            modal.destroy();
            resolve(choice);
        };

        modal.on('add', () => answer('add'));
        modal.on('skip', () => answer('skip'));
        modal.on('cancel', () => answer('cancel'));
    });
}

/**
 * Runs before an entry or term form saves. If the item's address will change,
 * it asks whether to leave a 301 redirect behind. The server reads the answer
 * when the save arrives, and when there is no answer (as with a save from
 * code), it adds the redirect.
 */
async function confirmRedirect(payload) {
    const axios = useAxios();
    const reference = referenceFor(payload);
    const { automaticRedirects, urls } = config.get('marketingToolkit') ?? {};

    // When automatic redirects are switched off, there is nothing to ask.
    if (!reference || !automaticRedirects) return;

    let check;

    try {
        check = (await axios.post(urls.redirectCheck, { reference, values: payload.values })).data;
    } catch {
        // A failed check never blocks a save, and the server adds the redirect by default.
        return;
    }

    if (!check.changes) return;

    const choice = await askRedirect(check.from, check.to);

    if (choice === 'cancel') {
        // Nothing is saved, and the next save asks again. Throwing PipelineStopped is how
        // Statamic's save stops quietly; it reports any other error as "Something went wrong".
        toast.info(__('marketing-toolkit::cp.confirm.not_saved'));
        throw new PipelineStopped();
    }

    const create = choice === 'add';

    try {
        await axios.post(urls.redirectChoice, { reference, create });
    } catch {
        // When sending the answer fails, the default applies and the redirect is added.
        // The save goes ahead, so we tell the user when that isn't what they chose.
        if (!create) toast.error(__('marketing-toolkit::cp.confirm.choice_failed'), { duration: 10000 });
    }
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

    // This shows the Tracking tab's warning as its fields change, and a toast when the set holding it is saved.
    conditions.add('mtTrackingOverlap', ({ root, values }) => trackingOverlaps(root ?? values));

    // The overlap is worked out while saving, because that hook has the values. The toast
    // is shown once saved, because that hook only runs when the save worked, but its
    // payload has no values.
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
 * Returns whether Google Tag Manager and another tracker are both set, in the
 * form or in .env. If GTM loads that tracker too, each visit counts twice.
 */
function trackingOverlaps(values) {
    const { trackingFields: fields = {}, trackingFromConfig: env = {} } = config.get('marketingToolkit') ?? {};
    const set = (tracker) => Boolean(env[tracker] || String(values?.[fields[tracker]] ?? '').trim());

    return set('gtm') && Object.keys(fields).some((tracker) => tracker !== 'gtm' && set(tracker));
}
