<?php

namespace JothamLec\MarketingToolkit;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Cp\Navigation;
use JothamLec\MarketingToolkit\Http\Middleware\HandleMissing;
use JothamLec\MarketingToolkit\Http\Middleware\MarkToolbarUser;
use JothamLec\MarketingToolkit\IndexNow\IndexNow;
use JothamLec\MarketingToolkit\Legacy\OldConfig;
use JothamLec\MarketingToolkit\Listeners\CountConversion;
use JothamLec\MarketingToolkit\Listeners\FlushSitemap;
use JothamLec\MarketingToolkit\Listeners\RedirectChangedUris;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Reports\ReportSettings;
use JothamLec\MarketingToolkit\SearchConsole\Client as SearchConsoleClient;
use JothamLec\MarketingToolkit\SearchConsole\Connection;
use JothamLec\MarketingToolkit\Support\Config;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Permissions;
use JothamLec\MarketingToolkit\Support\Sites;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use Statamic\Events\CollectionSaved;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntryScheduleReached;
use Statamic\Events\GlobalVariablesSaved;
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
        // llms.txt starts with Brand's description; saving any global set is rare enough to flush on.
        GlobalVariablesSaved::class => [FlushSitemap::class],
    ];

    protected $subscribe = [RedirectChangedUris::class];

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
        $config->set($key, Config::merge(require $path, OldConfig::upgrade((array) $config->get($key, []))));
    }

    /**
     * @return void
     */
    public function boot()
    {
        // Ahead of the parent's own callback, which registers the routes, listeners and
        // middleware: each listener and middleware asks whether its module is on itself.
        Statamic::booted(fn () => Features::apply());

        parent::boot();
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
            Permission::register(Permissions::VIEW)->label(__('marketing-toolkit::cp.permissions.view'));
            Permission::register(Permissions::REDIRECTS)->label(__('marketing-toolkit::cp.permissions.redirects'));
            Permission::register(Permissions::REPORTS)->label(__('marketing-toolkit::cp.permissions.reports'));
        }));

        Navigation::register();

        // Worked out when the control panel renders, which has the CP routes; a front-end request never asks.
        Statamic::provideToScript(['marketingToolkit' => fn () => [
            // Off: a save has nothing to ask the redirect check.
            'automaticRedirects' => Features::on('automatic_redirects'),
            'urls' => ['redirectCheck' => cp_route('mt.redirects.check'), 'redirectChoice' => cp_route('mt.redirects.choice')],
            // Brand and Marketing settings: the Tracking tab is in one of them.
            'globals' => Settings::handles(),
            // The Tracking tab's fields, and those set in .env, which its warning counts as well.
            'trackingFields' => Tracking::FIELDS,
            'trackingFromConfig' => array_filter(app(Tracking::class)->fromConfig()),
        ]]);
    }

    /**
     * Statamic builds an addon's schedule on every console boot: each artisan
     * command, queue job process and test. Reading the report settings costs
     * 15 to 25 ms there (Statamic parses each default value as Antlers), spent
     * only to learn that reports are off. The schedule is needed only by the
     * commands that run it, list it, or finish a background event of it.
     *
     * @param  Schedule  $schedule
     * @return void
     */
    protected function schedule($schedule)
    {
        if (! $this->app->runningConsoleCommand(['schedule:run', 'schedule:work', 'schedule:test', 'schedule:list', 'schedule:finish'])) {
            return;
        }

        // A key set up in the control panel, which bootAddon() would only apply after this.
        Connection::apply();

        $this->scheduleJobs($schedule);
    }

    private function scheduleJobs(Schedule $schedule): void
    {
        $settings = app(ReportSettings::class);
        // Off under Features (or in config/marketing-toolkit.php): reports run only by hand.
        $schedules = Features::on('reports') ? $settings->get('schedule') : 'off';
        $time = substr((string) $settings->get('schedule_time'), 0, 5) ?: '03:00';
        $day = array_search($settings->get('schedule_day'), ReportSettings::DAYS, true);

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

        if (app(SearchConsoleClient::class)->configuredForAnySite()) {
            $schedule->command('statamic:mt:search-console')->dailyAt('04:30')->withoutOverlapping();
        }

        // Keeps the 404 log to `not_found.max_rows` (MissingPath::prunable()).
        if (Features::on('not_found')) {
            $schedule->command('model:prune', ['--model' => [MissingPath::class]])->daily();
        }
    }
}
