import { router } from '@statamic/cms/inertia';
import RedirectConfirm from './components/RedirectConfirm.vue';
import SeoPreviewFieldtype from './components/SeoPreviewFieldtype.vue';
import SeoWidget from './components/SeoWidget.vue';
import NotFound from './pages/NotFound.vue';
import Overview from './pages/Overview.vue';
import RedirectForm from './pages/RedirectForm.vue';
import Report from './pages/Report.vue';
import Reports from './pages/Reports.vue';
import SearchConsole from './pages/SearchConsole.vue';
import Redirects from './pages/Redirects.vue';

/**
 * The current Inertia page: the one the CP loaded with, then each one it
 * navigates to. (Inertia 2's router doesn't expose it outside components.)
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
    if (Statamic.$app.config.globalProperties.$stacks?.count() > 0) return null;

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
    const axios = Statamic.$app.config.globalProperties.$axios;
    const reference = referenceFor(payload);

    // Automatic redirects are Pro: the free edition has nothing to ask.
    if (!reference || !Statamic.$config.get('seo')?.pro) return Promise.resolve();

    return axios
        .post(cp_url('seo/redirects/check'), { reference, values: payload.values })
        .then(({ data }) => {
            if (!data.changes) return;

            return new Promise((resolve, reject) => {
                const modal = Statamic.$components.append('seo-redirect-confirm', { props: { from: data.from, to: data.to } });
                const answer = (create) => {
                    modal.destroy();
                    // A failed answer leaves the default: the redirect is added.
                    axios.post(cp_url('seo/redirects/choice'), { reference, create }).then(resolve, resolve);
                };

                modal.on('add', () => answer(true));
                modal.on('skip', () => answer(false));
                modal.on('cancel', () => {
                    modal.destroy();
                    reject(__('seo::cp.confirm.not_saved'));
                });
            });
        })
        // A failed check never blocks a save; the server adds the redirect by default.
        .catch((error) => (typeof error === 'string' ? Promise.reject(error) : undefined));
}

Statamic.booting(() => {
    Statamic.$components.register('seo_preview-fieldtype', SeoPreviewFieldtype);
    Statamic.$components.register('seo-widget', SeoWidget);
    Statamic.$components.register('seo-redirect-confirm', RedirectConfirm);
    Statamic.$inertia.register('seo::Overview', Overview);
    Statamic.$inertia.register('seo::Redirects', Redirects);
    Statamic.$inertia.register('seo::RedirectForm', RedirectForm);
    Statamic.$inertia.register('seo::NotFound', NotFound);
    Statamic.$inertia.register('seo::Reports', Reports);
    Statamic.$inertia.register('seo::Report', Report);
    Statamic.$inertia.register('seo::SearchConsole', SearchConsole);

    router.on('navigate', (event) => {
        page = event.detail.page;
    });

    for (const type of ['entry', 'term']) {
        Statamic.$hooks.on(`${type}.saving`, (resolve, reject, payload) => confirmRedirect(payload).then(resolve, reject));
    }
});
