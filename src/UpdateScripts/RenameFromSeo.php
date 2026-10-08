<?php

namespace JothamLec\MarketingToolkit\UpdateScripts;

use Illuminate\Support\Facades\File;
use JothamLec\MarketingToolkit\Support\Permissions;
use Statamic\Facades\Role;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The addon's `seo` names became `marketing-toolkit` (namespaces, config)
 * and `mt` (tags, handles) in 0.20. This renames them in the site's own
 * files, on the developer's machine, so the change is committed. It covers blueprint
 * and fieldset imports and labels, the addon's tags in templates, the widget
 * in config/statamic/cp.php, a published config/seo.php and translations,
 * and the permissions roles were given. The rename_seo_tables_to_mt migration
 * handles the database and storage.
 *
 * It runs when updating from before 0.20, or while the site still has one
 * of the old names this renames (a site that swapped Co-SEO for this package
 * without `updates:run` has no old version). Each of those is gone once it
 * has run, so it doesn't run again. It renames only what was the addon's. The
 * Brand global is still `seo`, so `{{ seo:site_name }}` stays, and so do
 * a `config/seo.php` or `lang/vendor/seo` of another package. Remove it at 1.0.
 */
final class RenameFromSeo extends UpdateScript
{
    public const array PERMISSIONS = [
        'view seo' => Permissions::VIEW,
        'manage seo redirects' => Permissions::REDIRECTS,
        'run seo reports' => Permissions::REPORTS,
    ];

    /** These are the tags the addon gave as `seo:` up to 0.19. */
    public const array TAGS = ['head', 'body', 'meta', 'favicons'];

    /** These keys of the addon's config/seo.php are ones another package's (ralphjsmit/laravel-seo's) lacks. */
    private const array CONFIG_KEYS = [
        'collections', 'taxonomies', 'robots_txt', 'llms_txt', 'ads_txt', 'hreflang', 'not_found',
        'indexnow', 'search_console', 'tracking', 'favicons', 'reports', 'og',
    ];

    /** This matches the addon's `seo::` names that a blueprint or fieldset can hold (the fieldset and translations). */
    private const string NAMESPACED = '/(?<![\w-])seo::(?=(?:seo|cp|fields|frontend|reports|validation)\b)/';

    public function shouldUpdate($newVersion, $oldVersion)
    {
        return self::before((string) $oldVersion, '0.20.0') || $this->hasOldNames();
    }

    public function update(): void
    {
        $changed = [
            ...$this->rewrite([resource_path('blueprints'), resource_path('fieldsets')], '/\.yaml$/', [
                self::NAMESPACED => 'marketing-toolkit::',
                '/\bseoTrackingOverlap\b/' => 'mtTrackingOverlap',
                '/(\btype:\s*)seo_preview\b/' => '$1mt_preview',
            ]),
            ...$this->renameTags(),
            ...$this->rewrite([config_path('statamic')], '/^cp\.php$/', [
                "/'type'(\s*)=>(\s*)'seo'/" => "'type'\$1=>\$2'mt'",
            ]),
            ...($this->hasOwnConfig() ? $this->move(config_path('seo.php'), config_path('marketing-toolkit.php')) : []),
            ...($this->hasOwnTranslations() ? $this->move(lang_path('vendor/seo'), lang_path('vendor/marketing-toolkit')) : []),
            ...$this->renamePermissions(),
        ];

        if ($changed !== []) {
            $this->console()->info('Marketing Toolkit renamed its `seo` names in: '.implode(', ', $changed).'. Commit them.');
        }
    }

    /**
     * Determines whether the site still has an old name that only this addon gave. Each one is
     * renamed by update(), so this is false once it has run.
     */
    private function hasOldNames(): bool
    {
        return $this->hasOwnConfig()
            || $this->hasOwnTranslations()
            || Role::all()->contains(fn ($role) => $role->permissions()->intersect(['manage seo redirects', 'run seo reports'])->isNotEmpty())
            || collect([resource_path('blueprints'), resource_path('fieldsets')])
                ->filter(fn (string $folder) => File::isDirectory($folder))
                ->flatMap(fn (string $folder) => File::allFiles($folder))
                ->contains(fn (SplFileInfo $file) => str_ends_with($file->getFilename(), '.yaml') && preg_match(self::NAMESPACED, $file->getContents()));
    }

    /**
     * Renames the addon's tags in templates, Antlers and Blade. A site with a `seo`
     * tag of its own (or another addon's) keeps its templates as they are.
     *
     * @return list<string> the files changed
     */
    private function renameTags(): array
    {
        if (app('statamic.tags')->has('seo')) {
            $this->console()->warn('The site has a `seo` tag of its own, so Marketing Toolkit left its templates alone: rename `seo:head`, `seo:body`, `seo:meta` and `seo:favicons` to `mt:` by hand where they are the addon\'s.');

            return [];
        }

        $tags = implode('|', self::TAGS);

        return $this->rewrite([resource_path('views')], '/\.(html|php)$/', [
            "/<s:seo:($tags)(?![\\w-])/" => '<s:mt:$1',
            "/\\{\\{(\\s*)seo:($tags)(?![\\w-])/" => '{{$1mt:$2',
            "/Statamic::tag\\((['\"])seo:($tags)\\1/" => 'Statamic::tag($1mt:$2$1',
        ]);
    }

    /**
     * Determines whether config/seo.php was published from this addon rather than another package.
     * It is if it names the addon's classes, or if, cut down to what the site changed (only its own
     * `App\Seo`, say), it sets two of the addon's keys.
     */
    private function hasOwnConfig(): bool
    {
        if (! File::exists(config_path('seo.php')) || File::exists(config_path('marketing-toolkit.php'))) {
            return false;
        }

        $config = File::get(config_path('seo.php'));
        $keys = implode('|', self::CONFIG_KEYS);
        preg_match_all("/(['\"])($keys)\\1\\s*=>/", $config, $matches);

        return str_contains($config, 'JothamLec\\') || count(array_unique($matches[2])) >= 2;
    }

    /** Determines whether translations were published from this addon (by its file names), not another package. */
    private function hasOwnTranslations(): bool
    {
        return File::isDirectory(lang_path('vendor/seo'))
            && ! File::exists(lang_path('vendor/marketing-toolkit'))
            && collect(File::allFiles(lang_path('vendor/seo')))->contains(fn (SplFileInfo $file) => in_array($file->getFilename(), ['fields.php', 'reports.php'], true));
    }

    /**
     * @param  list<string>  $folders
     * @param  array<string, string>  $replacements  pattern => replacement
     * @return list<string> the files changed
     */
    private function rewrite(array $folders, string $name, array $replacements): array
    {
        $changed = [];

        foreach (array_filter($folders, fn (string $folder) => File::isDirectory($folder)) as $folder) {
            foreach (File::allFiles($folder) as $file) {
                /** @var SplFileInfo $file */
                if (! preg_match($name, $file->getFilename())) {
                    continue;
                }

                $old = $file->getContents();
                $new = (string) preg_replace(array_keys($replacements), array_values($replacements), $old);

                if ($new !== $old) {
                    File::put($file->getPathname(), $new);
                    $changed[] = $this->relative($file->getPathname());
                }
            }
        }

        return $changed;
    }

    /**
     * @return list<string>
     */
    private function move(string $from, string $to): array
    {
        if (! File::exists($from) || File::exists($to)) {
            return [];
        }

        File::move($from, $to);

        return [$this->relative($from).' → '.$this->relative($to)];
    }

    /**
     * @return list<string>
     */
    private function renamePermissions(): array
    {
        $changed = [];

        foreach (Role::all() as $role) {
            $permissions = $role->permissions()->all();
            $renamed = array_map(fn (string $permission) => self::PERMISSIONS[$permission] ?? $permission, $permissions);

            if ($renamed !== $permissions) {
                $role->permissions($renamed)->save();
                $changed[] = "the {$role->handle()} role";
            }
        }

        return $changed;
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }
}
