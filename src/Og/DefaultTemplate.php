<?php

namespace JothamLec\MarketingToolkit\Og;

use JothamLec\MarketingToolkit\Og\Layouts\Branded;
use SimonHamp\TheOg\BorderPosition;
use SimonHamp\TheOg\Image;
use SimonHamp\TheOg\Theme;

/**
 * Draws the Branded layout in the colours from Brand's Share cards tab, in all three shapes: the
 * brand (the logo, or the name in type), the title, the description, the section, and Brand's
 * Picture on the right when one is set.
 */
class DefaultTemplate extends Template
{
    public function image(Card $card): Image
    {
        // This starts from the built-in light theme for its fonts, then colours every part.
        $theme = Theme::Light->load()
            ->backgroundColor($card->background)
            ->baseColor($card->text)
            ->titleColor($card->text)
            ->descriptionColor($card->text)
            ->urlColor($card->accent)
            ->callToActionColor($card->accent)
            ->accentColor($card->accent);

        $image = (new Image)
            ->layout(new Branded($card, (string) config('marketing-toolkit.og.logo_position', 'top')))
            ->theme($theme)
            ->border(BorderPosition::Bottom, $card->accent, LogoBox::BORDER)
            ->callToAction($card->label)
            ->title($card->title)
            ->url($card->siteName);

        if ($card->description) {
            $image->description($card->description);
        }

        return $image;
    }

    public function version(): string
    {
        return '2';
    }

    public function shapes(): array
    {
        return [Shape::Landscape, Shape::Square, Shape::Classic];
    }
}
