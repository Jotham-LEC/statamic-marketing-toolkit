<?php

namespace JothamLec\MarketingToolkit\Og;

use SimonHamp\TheOg\BorderPosition;
use SimonHamp\TheOg\Image;
use SimonHamp\TheOg\Layout\Layouts\Standard;
use SimonHamp\TheOg\Theme;

/**
 * the-og's Standard layout in the colours from Brand's Share cards tab: the
 * section up top, the title, the description, the site name along the
 * bottom, and the picture (logo or portrait) when one is set.
 */
class DefaultTemplate extends Template
{
    public function image(Card $card): Image
    {
        // Start from the built-in light theme for its fonts, then colour every part.
        $theme = Theme::Light->load()
            ->backgroundColor($card->background)
            ->baseColor($card->text)
            ->titleColor($card->text)
            ->descriptionColor($card->text)
            ->urlColor($card->accent)
            ->callToActionColor($card->accent)
            ->accentColor($card->accent);

        $image = (new Image)
            ->layout(new Standard)
            ->theme($theme)
            ->border(BorderPosition::Bottom, $card->accent, 16)
            ->callToAction($card->label)
            ->title($card->title)
            ->url($card->siteName);

        if ($card->description) {
            $image->description($card->description);
        }

        if ($card->picture) {
            $image->picture($card->picture);
        }

        return $image;
    }
}
