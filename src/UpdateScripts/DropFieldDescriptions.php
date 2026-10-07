<?php

namespace JothamLec\MarketingToolkit\UpdateScripts;

use Illuminate\Support\Facades\Lang;
use JothamLec\MarketingToolkit\Settings;
use Statamic\Facades\Blueprint;
use Statamic\UpdateScripts\UpdateScript;

/**
 * 0.21 shows no descriptions under the fields of Brand and Marketing
 * settings (editors.md explains them). The site's own copies of those
 * blueprints still name the descriptions `mt:install` gave them, which no
 * longer exist and would show as raw keys: this takes them out. A
 * description the site wrote itself stays. A site coming from 0.19 or
 * earlier still has them under `seo::`: RenameFromSeo renames them after
 * this has run (Statamic asks every script before running any), so those
 * are taken out too, judged by the name they are renamed to.
 */
class DropFieldDescriptions extends UpdateScript
{
    public function shouldUpdate($newVersion, $oldVersion)
    {
        return collect(Settings::handles())->contains(fn (string $handle) => ($blueprint = Blueprint::find('globals.'.$handle))
            && $this->without($blueprint->contents()) !== $blueprint->contents());
    }

    public function update()
    {
        foreach (Settings::handles() as $handle) {
            $blueprint = Blueprint::find('globals.'.$handle);

            if ($blueprint && ($contents = $this->without($blueprint->contents())) !== $blueprint->contents()) {
                $blueprint->setContents($contents)->save();
            }
        }
    }

    /**
     * The contents without the addon's descriptions that are gone, at any depth.
     *
     * @param  array<mixed>  $contents
     * @return array<mixed>
     */
    private function without(array $contents): array
    {
        foreach ($contents as $key => $value) {
            if ($key === 'instructions' && is_string($value) && $this->isGone($value)) {
                unset($contents[$key]);
            } elseif (is_array($value)) {
                $contents[$key] = $this->without($value);
            }
        }

        return $contents;
    }

    /**
     * Whether the value names an addon description that no longer exists,
     * by its current name or the 0.19 `seo::` one.
     */
    private function isGone(string $instructions): bool
    {
        if (str_starts_with($instructions, 'seo::')) {
            $instructions = 'marketing-toolkit::'.substr($instructions, strlen('seo::'));
        }

        return str_starts_with($instructions, 'marketing-toolkit::') && ! Lang::has($instructions, 'en', false);
    }
}
