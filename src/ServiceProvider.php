<?php

namespace JothamLec\MarketingToolkit;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Cp\Navigation;
use JothamLec\MarketingToolkit\Http\Middleware\HandleMissing;
use JothamLec\MarketingToolkit\Http\Middleware\MarkToolbarUser;
use JothamLec\MarketingToolkit\IndexNow\IndexNow;
use JothamLec\MarketingToolkit\Listeners\AttributeSubmission;
use JothamLec\MarketingToolkit\Listeners\CountConversion;
use JothamLec\MarketingToolkit\Listeners\FlushSitemap;
use JothamLec\MarketingToolkit\Listeners\RedirectChangedUris;
use JothamLec\MarketingToolkit\Listeners\RemakeFavicons;
use JothamLec\MarketingToolkit\Listeners\SubmitToIndexNow;
use JothamLec\MarketingToolkit\Listeners\ToolbarSignIn;
use JothamLec\MarketingToolkit\Listeners\ToolbarSignOut;
use JothamLec\MarketingToolkit\Reports\ReportSettings;
use JothamLec\MarketingToolkit\SearchConsole\Client as SearchConsoleClient;
use JothamLec\MarketingToolkit\SearchConsole\Connection;
use JothamLec\MarketingToolkit\Support\Config;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Sites;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;
use JothamLec\MarketingToolkit\Tracking\Tracking;
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
     * Views, translations, fieldsets and blueprints are all `marketing-toolkit::`,
     * the addon's slug, as Statamic names the last three; it names views
     * after the package unless told.
     */
    protected $viewNamespace = 'marketing-toolkit';

    /** Merged in register() instead (see mergeConfigFrom), before anything boots that reads it. */
    protected $config = false;

    protected $vite = [
        'input' => ['resources/js/addon.js', 'resources/css/addon.css'],
        'publicDirectory' => 'resources/dist',
    ];

    protected $middlewareGroups = [
        'statamic.web' => [HandleMissing::class],
        // After Statamic's own: the user is known.
        'statamic.cp.authenticated' => [MarkToolbarUser::class],
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

    public function register(): void
    {
        parent::register();

        // Here rather than in Statamic's bootConfig(), which runs after the Features switches read it.
        $this->mergeConfigFrom(__DIR__.'/../config/marketing-toolkit.php', 'marketing-toolkit');

        // A site binds its subclass in its own service provider, which registers after this one.
        // `marketing-toolkit.class` still works until 1.0.
        $this->app->bind(SiteSeo::class, fn ($app) => $app->build(config('marketing-toolkit.class') ?? SiteSeo::class));

        // One instance, so what it learns while content saves is still there once it has saved.
        $this->app->singleton(RedirectChangedUris::class);

        // One per request: it gathers the changed addresses until the response is out.
        $this->app->singleton(IndexNow::class);
    }

    /**
     * The site's config/marketing-toolkit.php over the addon's, merged at every depth
     * (Support\Config) rather than Laravel's one level, so a site states only
     * what it changes, even inside `og` or `robots`. Keys renamed since the
     * site published its copy are read under their new names.
     */
    protected function mergeConfigFrom($path, $key)
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $config->set($key, Config::merge(require $path, Config::upgrade((array) $config->get($key, []))));
    }

    public function boot()
    {
        // Ahead of the parent's own callback, which registers the commands,
        // widget, actions, listeners and middleware of the modules that are on.
        Statamic::booted(fn () => $this->bootFeatures());

        parent::boot();
    }

    /**
     * The modules that are off (Features), off before anything registers.
     */
    protected function bootFeatures(): void
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
            FlushSitemap::class => ! config('marketing-toolkit.sitemap.enabled') && ! config('marketing-toolkit.llms_txt.enabled'),
            SubmitToIndexNow::class => ! config('marketing-toolkit.indexnow.enabled'),
            RemakeFavicons::class => ! config('marketing-toolkit.favicons.enabled'),
            AttributeSubmission::class => ! config('marketing-toolkit.leads.enabled'),
            CountConversion::class => ! config('marketing-toolkit.leads.enabled'),
            RedirectChangedUris::class => ! config('marketing-toolkit.redirects.automatic'),
            HandleMissing::class => ! config('marketing-toolkit.redirects.enabled') && ! config('marketing-toolkit.not_found.enabled'),
            MarkToolbarUser::class => ! Toolbar::enabled(),
            ToolbarSignIn::class => ! Toolbar::enabled(),
            ToolbarSignOut::class => ! Toolbar::enabled(),
        ]));

        $this->listen = array_filter(array_map(fn (array $listeners) => array_values(array_diff($listeners, $this->unused)), $this->listen));
        $this->subscribe = array_values(array_diff($this->subscribe, $this->unused));
        $this->middlewareGroups = array_filter(array_map(fn (array $middleware) => array_values(array_diff($middleware, $this->unused)), $this->middlewareGroups));
    }

    /**
     * Statamic also registers every command, widget, action and listener in
     * their folders: none registers one of a module that's off.
     */
    protected function autoloadFilesFromFolder($folder, $requiredClass = null)
    {
        return array_values(array_diff(parent::autoloadFilesFromFolder($folder, $requiredClass), $this->unused));
    }

    public function bootAddon(): void
    {
        $this->publishes([__DIR__.'/../config/marketing-toolkit.php' => config_path('marketing-toolkit.php')], 'marketing-toolkit-config');

        // Written and read by the page's own script: left as plain text.
        EncryptCookies::except([Attribution::COOKIE, CountConversion::COOKIE, Toolbar::COOKIE]);

        // A key and property set up in the control panel, where .env has none.
        Connection::apply();

        $this->app->terminating(fn () => $this->app->make(IndexNow::class)->flush());

        // A queue worker doesn't terminate between jobs: send what each job changed once it is done.
        // (A sync job is part of the request or command that ran it, which terminates as usual.)
        Queue::after(function (JobProcessed $event) {
            if ($event->connectionName !== 'sync') {
                $this->app->make(IndexNow::class)->flush();
            }
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Permission::extend(fn () => Permission::group('marketing-toolkit', __('marketing-toolkit::cp.permissions.group'), function () {
            Permission::register('view marketing toolkit')->label(__('marketing-toolkit::cp.permissions.view'));
            Permission::register('manage marketing toolkit redirects')->label(__('marketing-toolkit::cp.permissions.redirects'));
            Permission::register('run marketing toolkit reports')->label(__('marketing-toolkit::cp.permissions.reports'));
        }));

        Navigation::register();

        Statamic::provideToScript(['marketingToolkit' => [
            // Off: a save has nothing to ask the redirect check.
            'automaticRedirects' => (bool) config('marketing-toolkit.redirects.automatic'),
            // Brand and Marketing settings: the Tracking tab is in one of them.
            'globals' => Settings::handles(),
            // Trackers set in .env, which the Tracking tab's warning counts as well.
            'trackingFromConfig' => array_filter(app(Tracking::class)->fromConfig()),
        ]]);
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
            // A key set up in the control panel, which bootAddon() would only apply after this.
            Connection::apply();

            parent::bootSchedule();
        }

        return $this;
    }

    protected function schedule($schedule)
    {
        $settings = app(ReportSettings::class);
        // Off under Features (or in config/marketing-toolkit.php): reports run only by hand.
        $schedules = config('marketing-toolkit.reports.enabled') ? $settings->get('schedule') : 'off';
        $time = substr((string) $settings->get('schedule_time'), 0, 5) ?: '03:00';
        $day = array_search($settings->get('schedule_day'), ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], true);

        // One run per site on a multi-site install, each with its own overlap lock.
        foreach (Sites::multiple() ? Sites::handles() : [null] as $site) {
            $command = $site === null ? 'statamic:mt:report' : 'statamic:mt:report --site='.$site;

            $event = match ($schedules) {
                'daily' => $schedule->command($command)->dailyAt($time),
                'weekly' => $schedule->command($command)->weeklyOn($day === false ? 1 : $day, $time),
                default => null,
            };

            $event?->withoutOverlapping()->runInBackground();
        }

        // Search Console's numbers, daily, once it is set up (in .env or the control panel).
        if (app(SearchConsoleClient::class)->configuredForAnySite()) {
            $schedule->command('statamic:mt:search-console')->dailyAt('04:30')->withoutOverlapping();
        }
    }
}
