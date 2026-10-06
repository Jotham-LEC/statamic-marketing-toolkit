<?php

namespace JothamLec\MarketingToolkit;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use JothamLec\MarketingToolkit\Actions\CreateRedirect;
use JothamLec\MarketingToolkit\Commands\Report;
use JothamLec\MarketingToolkit\Commands\SearchConsole;
use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Cp\Navigation;
use JothamLec\MarketingToolkit\Http\Middleware\HandleMissing;
use JothamLec\MarketingToolkit\IndexNow\IndexNow;
use JothamLec\MarketingToolkit\Listeners\AttributeSubmission;
use JothamLec\MarketingToolkit\Listeners\CountConversion;
use JothamLec\MarketingToolkit\Listeners\FlushSitemap;
use JothamLec\MarketingToolkit\Listeners\RedirectChangedUris;
use JothamLec\MarketingToolkit\Listeners\RemakeFavicons;
use JothamLec\MarketingToolkit\Listeners\SubmitToIndexNow;
use JothamLec\MarketingToolkit\Reports\ReportSettings;
use JothamLec\MarketingToolkit\SearchConsole\Client as SearchConsoleClient;
use JothamLec\MarketingToolkit\SearchConsole\Connection;
use JothamLec\MarketingToolkit\Support\Config;
use JothamLec\MarketingToolkit\Support\Edition;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Sites;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use JothamLec\MarketingToolkit\Widgets\SeoWidget;
use Statamic\Events\CollectionSaved;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntryScheduleReached;
use Statamic\Events\StacheCleared;
use Statamic\Events\TaxonomySaved;
use Statamic\Events\TermDeleted;
use Statamic\Events\TermSaved;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    /*
     * The SEO module keeps the names it had as Co-SEO (addon slug `seo`):
     * `seo::` views, translations, fieldsets and blueprints, and
     * config/seo.php, so sites that import `seo::seo` or call `__('seo::…')`
     * don't change. Statamic would name them after the slug.
     */
    protected $viewNamespace = 'seo';

    protected $fieldsetNamespace = 'seo';

    protected $blueprintNamespace = 'seo';

    protected $config = false;

    protected $translations = false;

    protected $vite = [
        'input' => ['resources/js/addon.js', 'resources/css/addon.css'],
        'publicDirectory' => 'resources/dist',
    ];

    protected $middlewareGroups = [
        'statamic.web' => [HandleMissing::class],
    ];

    /*
     * FlushSitemap takes no event, so Statamic can't find its events from its
     * handle() as it does for the other listeners in Listeners/.
     */
    protected $listen = [
        EntrySaved::class => [FlushSitemap::class],
        EntryDeleted::class => [FlushSitemap::class],
        // A scheduled entry going live, or an expiring one going away (Statamic's scheduler).
        EntryScheduleReached::class => [FlushSitemap::class],
        TermSaved::class => [FlushSitemap::class],
        TermDeleted::class => [FlushSitemap::class],
        CollectionTreeSaved::class => [FlushSitemap::class],
        // A new route moves every entry or term in it.
        CollectionSaved::class => [FlushSitemap::class],
        TaxonomySaved::class => [FlushSitemap::class],
        // A deploy clears the Stache; the rules may have changed with the code.
        StacheCleared::class => [FlushSitemap::class],
    ];

    protected $subscribe = [RedirectChangedUris::class];

    /** @var list<class-string> listeners and middleware of modules that are off (leaveOutUnused) */
    private array $unused = [];

    /** What the free edition leaves out of Statamic's autoloading (autoloadFilesFromFolder). */
    private const array PRO_ONLY = [Report::class, SearchConsole::class, SeoWidget::class, CreateRedirect::class];

    public function register(): void
    {
        parent::register();

        // Merged here rather than in Statamic's bootConfig(), which would name the file after the slug.
        $this->mergeConfigFrom(__DIR__.'/../config/seo.php', 'seo');

        $this->app->bind(SiteSeo::class, fn ($app) => $app->build(config('seo.class')));

        // One instance, so what it learns while content saves is still there once it has saved.
        $this->app->singleton(RedirectChangedUris::class);

        // One per request: it gathers the changed addresses until the response is out.
        $this->app->singleton(IndexNow::class);
    }

    /**
     * The site's config/seo.php over the addon's, merged at every depth
     * (Support\Config) rather than Laravel's one level, so a site states only
     * what it changes, even inside `og` or `robots`.
     */
    protected function mergeConfigFrom($path, $key)
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $config->set($key, Config::merge(require $path, (array) $config->get($key, [])));
    }

    public function boot()
    {
        // Ahead of the parent's own callback, which registers the commands,
        // widget, actions and routes this takes out of the free edition.
        Statamic::booted(fn () => $this->bootEdition());

        parent::boot();
    }

    /**
     * The modules that are off (Features), off before anything registers. The
     * free edition's Pro classes are left out of the autoloading below.
     */
    protected function bootEdition(): void
    {
        Features::apply();

        $this->leaveOutUnused();
    }

    /**
     * Listeners and middleware of modules that are off aren't registered at
     * all, so they cost nothing on a request or a save.
     */
    protected function leaveOutUnused(): void
    {
        $this->unused = array_keys(array_filter([
            FlushSitemap::class => ! config('seo.sitemap.enabled') && ! config('seo.llms_txt'),
            SubmitToIndexNow::class => ! config('seo.indexnow.enabled'),
            RemakeFavicons::class => ! config('seo.favicons.enabled'),
            AttributeSubmission::class => ! config('seo.leads.enabled'),
            CountConversion::class => ! config('seo.leads.enabled'),
            RedirectChangedUris::class => ! config('seo.redirects.automatic'),
            HandleMissing::class => ! config('seo.redirects.enabled') && ! config('seo.not_found.enabled'),
        ]));

        $this->listen = array_filter(array_map(fn (array $listeners) => array_values(array_diff($listeners, $this->unused)), $this->listen));
        $this->subscribe = array_values(array_diff($this->subscribe, $this->unused));
        $this->middlewareGroups = array_filter(array_map(fn (array $middleware) => array_values(array_diff($middleware, $this->unused)), $this->middlewareGroups));
    }

    /**
     * Statamic also registers every command, widget, action and listener in
     * their folders: the free edition's leave Pro's out there too, and none
     * registers one of a module that's off.
     */
    protected function autoloadFilesFromFolder($folder, $requiredClass = null)
    {
        $classes = array_values(array_diff(parent::autoloadFilesFromFolder($folder, $requiredClass), $this->unused));

        return Edition::pro() ? $classes : array_values(array_diff($classes, self::PRO_ONLY));
    }

    /**
     * The settings are the reports': Pro only.
     */
    protected function bootSettingsBlueprint()
    {
        return Edition::pro() ? parent::bootSettingsBlueprint() : $this;
    }

    public function bootAddon(): void
    {
        $this->bootSeoNames();

        // Written and read by the page's own script: left as plain text.
        EncryptCookies::except([Attribution::COOKIE, CountConversion::COOKIE]);

        // A key and property set up in the control panel, where .env has none.
        if (Edition::pro()) {
            Connection::apply();
        }

        $this->app->terminating(fn () => $this->app->make(IndexNow::class)->flush());

        // A queue worker doesn't terminate between jobs: send what each job changed once it is done.
        // (A sync job is part of the request or command that ran it, which terminates as usual.)
        Queue::after(function (JobProcessed $event) {
            if ($event->connectionName !== 'sync') {
                $this->app->make(IndexNow::class)->flush();
            }
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Permission::extend(fn () => Permission::group('seo', __('seo::cp.seo'), function () {
            Permission::register('view seo')->label(__(Edition::pro() ? 'seo::cp.permissions.view' : 'seo::cp.permissions.view_free'));
            Permission::register('manage seo redirects')->label(__('seo::cp.permissions.redirects'));

            if (Edition::pro()) {
                Permission::register('run seo reports')->label(__('seo::cp.permissions.reports'));
            }
        }));

        Navigation::register();

        Statamic::provideToScript(['seo' => [
            'pro' => Edition::pro(),
            'global' => (string) config('seo.global'),
            // Trackers set in .env, which the Tracking tab's warning counts as well.
            'trackingFromConfig' => array_filter(app(Tracking::class)->fromConfig()),
        ]]);
    }

    /**
     * config/seo.php and the `seo::` translations, under the names the SEO
     * module has always had (see the namespaces above). The addon settings
     * Co-SEO saved under its old name are copied over by a migration.
     */
    protected function bootSeoNames(): void
    {
        $this->publishes([__DIR__.'/../config/seo.php' => config_path('seo.php')], 'seo-config');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'seo');
        $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/seo')], 'seo-translations');
    }

    /**
     * Statamic builds an addon's schedule on every console boot: each artisan
     * command, queue job process and test. Reading the report settings costs
     * 15 to 25 ms there (Statamic parses each default value as Antlers), spent
     * only to learn that reports are off. The schedule is needed only by the
     * commands that run it, list it, or finish a background event of it.
     * Overrides Statamic's AddonServiceProvider::bootSchedule(), an internal
     * method: check it still exists, and still only calls schedule(), on a
     * Statamic upgrade.
     */
    protected function bootSchedule()
    {
        if ($this->app->runningConsoleCommand(['schedule:run', 'schedule:work', 'schedule:test', 'schedule:list', 'schedule:finish'])) {
            parent::bootSchedule();
        }

        return $this;
    }

    protected function schedule($schedule)
    {
        // Reports and Search Console are Pro.
        if (! Edition::pro()) {
            return;
        }

        $settings = app(ReportSettings::class);
        // Off under Features (or in config/seo.php): reports run only by hand.
        $schedules = config('seo.reports.enabled') ? $settings->get('schedule') : 'off';
        $time = substr((string) $settings->get('schedule_time'), 0, 5) ?: '03:00';
        $day = array_search($settings->get('schedule_day'), ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], true);

        // One run per site on a multi-site install, each with its own overlap lock.
        foreach (Sites::multiple() ? Sites::handles() : [null] as $site) {
            $command = $site === null ? 'statamic:seo:report' : 'statamic:seo:report --site='.$site;

            $event = match ($schedules) {
                'daily' => $schedule->command($command)->dailyAt($time),
                'weekly' => $schedule->command($command)->weeklyOn($day === false ? 1 : $day, $time),
                default => null,
            };

            $event?->withoutOverlapping()->runInBackground();
        }

        // Search Console's numbers, daily, once it is set up. Asked when the
        // schedule runs: a key set up in the control panel is read later in boot.
        $schedule->command('statamic:seo:search-console')->dailyAt('04:30')->withoutOverlapping()
            ->when(fn () => app(SearchConsoleClient::class)->configuredForAnySite());
    }
}
