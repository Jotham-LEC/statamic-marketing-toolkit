<?php

namespace JothamLec\Seo\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;

/**
 * `php please seo:install`: creates the "SEO & brand" global set and its
 * blueprint through Statamic's API, so editors can fill in the site name,
 * defaults, publisher, verification codes, robots.txt, humans.txt and the
 * share-card colours in the control panel. Safe to rerun: it adds nothing
 * that already exists.
 */
class Install extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:seo:install {--container= : Asset container for the logo and default image}';

    protected $description = 'Create the SEO & brand global set';

    public function handle(): int
    {
        $handle = (string) config('seo.global');
        $container = $this->option('container') ?? AssetContainer::all()->first()?->handle();

        if (! $container) {
            $this->components->error('Create an asset container first, or pass --container.');

            return self::FAILURE;
        }

        if (! Blueprint::find("globals.{$handle}")) {
            Blueprint::make($handle)
                ->setNamespace('globals')
                ->setContents(['tabs' => self::tabs($container)])
                ->save();

            $this->components->info("Blueprint globals.{$handle} created.");
        }

        if (! GlobalSet::findByHandle($handle)) {
            GlobalSet::make($handle)->title('SEO & brand')->save();

            $this->components->info("Global set [{$handle}] created. Fill it in under Globals → SEO & brand.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    public static function tabs(string $container): array
    {
        $field = fn (string $handle, array $config) => ['handle' => $handle, 'field' => $config];
        $asset = fn (string $display, string $instructions = '') => ['type' => 'assets', 'display' => $display, 'container' => $container, 'max_files' => 1, 'instructions' => $instructions];

        return [
            'brand' => ['display' => 'Brand', 'sections' => [['fields' => [
                $field('site_name', ['type' => 'text', 'display' => 'Site name', 'width' => 50, 'instructions' => 'After each page title, and in og:site_name.']),
                $field('title_separator', ['type' => 'text', 'display' => 'Title separator', 'width' => 50, 'placeholder' => ' · ']),
                $field('default_description', ['type' => 'textarea', 'display' => 'Default description', 'character_limit' => 160, 'instructions' => 'For pages with no description and no first paragraph.']),
                $field('default_image', $asset('Default share image', 'For pages without an image or a generated card. 1200×630.')),
                $field('twitter_handle', ['type' => 'text', 'display' => 'X handle', 'width' => 50, 'prepend' => '@']),
            ]]]],
            'publisher' => ['display' => 'Publisher', 'sections' => [['instructions' => 'Who is behind the site, for search engines (JSON-LD).', 'fields' => [
                $field('publisher_type', ['type' => 'select', 'display' => 'Type', 'width' => 50, 'default' => 'Organization', 'options' => ['Organization' => 'Organization', 'LocalBusiness' => 'Local business', 'Person' => 'Person']]),
                $field('publisher_name', ['type' => 'text', 'display' => 'Name', 'width' => 50]),
                $field('publisher_logo', $asset('Logo or portrait')),
                $field('job_title', ['type' => 'text', 'display' => 'Job title', 'width' => 50, 'if' => ['publisher_type' => 'equals Person']]),
                $field('telephone', ['type' => 'text', 'display' => 'Telephone', 'width' => 50]),
                $field('email', ['type' => 'text', 'input_type' => 'email', 'display' => 'Email', 'width' => 50]),
                $field('area_served', ['type' => 'text', 'display' => 'Area served', 'width' => 50]),
                $field('price_range', ['type' => 'text', 'display' => 'Price range', 'width' => 50, 'placeholder' => 'RM 100–500']),
                $field('same_as', ['type' => 'list', 'display' => 'Profiles elsewhere', 'instructions' => 'Full URLs: LinkedIn, Instagram, Google Business Profile…']),
            ]]]],
            'share_cards' => ['display' => 'Share cards', 'sections' => [['instructions' => 'Colours and picture for generated share images.', 'fields' => [
                $field('og_background', ['type' => 'color', 'display' => 'Background', 'width' => 33]),
                $field('og_text', ['type' => 'color', 'display' => 'Text', 'width' => 33]),
                $field('og_accent', ['type' => 'color', 'display' => 'Accent', 'width' => 33]),
                $field('og_picture', $asset('Picture', 'A logo or portrait on every card.')),
            ]]]],
            'crawlers' => ['display' => 'Crawlers', 'sections' => [['fields' => [
                $field('google_verification', ['type' => 'text', 'display' => 'Google verification', 'width' => 50]),
                $field('bing_verification', ['type' => 'text', 'display' => 'Bing verification', 'width' => 50]),
                $field('yandex_verification', ['type' => 'text', 'display' => 'Yandex verification', 'width' => 50]),
                $field('pinterest_verification', ['type' => 'text', 'display' => 'Pinterest verification', 'width' => 50]),
                $field('robots_disallow', ['type' => 'list', 'display' => 'robots.txt Disallow', 'instructions' => 'Paths to keep crawlers out of. Empty: the control panel.']),
                $field('robots_extra', ['type' => 'textarea', 'display' => 'robots.txt extra lines', 'instructions' => 'Added as typed, e.g. rules for AI crawlers.']),
                $field('humans', ['type' => 'textarea', 'display' => 'humans.txt', 'instructions' => 'Served at /humans.txt when filled in.']),
            ]]]],
        ];
    }
}
