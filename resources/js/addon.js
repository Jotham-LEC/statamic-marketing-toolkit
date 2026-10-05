import SeoPreviewFieldtype from './components/SeoPreviewFieldtype.vue';
import SeoWidget from './components/SeoWidget.vue';
import Overview from './pages/Overview.vue';

Statamic.booting(() => {
    Statamic.$components.register('seo_preview-fieldtype', SeoPreviewFieldtype);
    Statamic.$components.register('seo-widget', SeoWidget);
    Statamic.$inertia.register('seo::Overview', Overview);
});
