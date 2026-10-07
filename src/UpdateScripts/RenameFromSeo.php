<?php

namespace JothamLec\MarketingToolkit\UpdateScripts;

use Illuminate\Support\Facades\File;
use Statamic\Facades\Role;
use Statamic\UpdateScripts\UpdateScript;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The addon's `seo` names became `marketing-toolkit` (namespaces, config)
 * and `mt` (tags, handles) after 0.19. This renames them in the site's own
 * files, on the developer's machine, so the change is committed: blueprint
 * and fieldset imports and labels, the tags in templates, the widget in
 * config/statamic/cp.php, a published config/seo.php and translations, and
 * the permissions roles were given. The database and storage are the
 * rename_seo_tables_to_mt migration's. Each step finds nothing to do once
 * done; remove the script at 1.0.
 */
class RenameFromSeo extends UpdateScript
{
    public const array PERMISSIONS = [
        'view seo' => 'view marketing toolkit',
        'manage seo redirects' => 'manage marketing toolkit redirects',
        'run seo reports' => 'run marketing toolkit reports',
    ];

    public function shouldUpdate($newVersion, $oldVersion)
    {
        return true;
    }

    public function update()
    {
        $changed = [
            ...$this->rewrite([resource_path('blueprints'), resource_path('fieldsets')], '/\.yaml$/', [
                '/(?<![\w-])seo::/' => 'marketing-toolkit::',
                '/\bseoTrackingOverlap\b/' => 'mtTrackingOverlap',
                '/(\btype:\s*)seo_preview\b/' => '$1mt_preview',
            ]),
            ...$this->rewrite([resource_path('views')], '/\.(html|php)$/', [
                '/<s:seo:/' => '<s:mt:',
                '/\{\{(\s*)seo:/' => '{{$1mt:',
            ]),
            ...$this->rewrite([config_path('statamic')], '/^cp\.php$/', [
                "/'type'(\s*)=>(\s*)'seo'/" => "'type'\$1=>\$2'mt'",
            ]),
            ...$this->move(config_path('seo.php'), config_path('marketing-toolkit.php')),
            ...$this->move(lang_path('vendor/seo'), lang_path('vendor/marketing-toolkit')),
            ...$this->renamePermissions(),
        ];

        if ($changed !== []) {
            $this->console()->info('Marketing Toolkit renamed its `seo` names in: '.implode(', ', $changed).'. Commit them.');
        }
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
