<?php

namespace JothamLec\MarketingToolkit\Og\Layouts;

use ImagickDraw;
use ImagickPixel;
use Intervention\Image\Geometry\Point;
use JothamLec\MarketingToolkit\Og\Brand;
use JothamLec\MarketingToolkit\Og\Card;
use JothamLec\MarketingToolkit\Og\LogoBox;
use JothamLec\MarketingToolkit\Og\Shape;
use SimonHamp\TheOg\BorderPosition;
use SimonHamp\TheOg\Layout\AbstractLayout;
use SimonHamp\TheOg\Layout\PictureBox;
use SimonHamp\TheOg\Layout\Position;
use SimonHamp\TheOg\Layout\TextBox;
use SimonHamp\TheOg\Theme\PicturePlacement;
use Throwable;

/**
 * The default card's layout, in any of the three shapes. From the top: the brand (the logo, on
 * a pill when it doesn't stand out from the background, or the name in type), the title, the
 * description and the section, with the accent border along the bottom. The brand goes to the
 * bottom with `marketing-toolkit.og.logo_position` set to `bottom`. Brand's Picture, when set,
 * is a square on the right, and the text column narrows for it.
 */
class Branded extends AbstractLayout
{
    protected BorderPosition $borderPosition = BorderPosition::Bottom;

    protected int $borderWidth = LogoBox::BORDER;

    protected int $padding = LogoBox::PADDING;

    /** The title's size in pixels for each shape; the other text follows it. */
    private const array TITLE_SIZE = ['1200x630' => 60, '4x3' => 72, '1x1' => 80];

    private Shape $shape;

    public function __construct(private Card $card, private string $logoPosition = 'top')
    {
        $this->shape = $card->shape;
        $this->width = $this->shape->width();
        $this->height = $this->shape->height();
    }

    public function features(): void
    {
        $title = self::TITLE_SIZE[$this->shape->value];
        $gap = (int) round($title * 0.6);
        $pictureSide = $this->card->picture ? min(420, (int) round($this->height * 0.48)) : 0;
        $column = $this->width - 2 * LogoBox::PADDING - ($pictureSide ? $pictureSide + 48 : 0);
        $bottom = $this->logoPosition === 'bottom';

        if ($pictureSide) {
            $this->addFeature((new PictureBox)
                ->path($this->card->picture)
                ->placement(PicturePlacement::Cover)
                ->box($pictureSide, $pictureSide)
                ->position(
                    x: $this->width - LogoBox::PADDING - $pictureSide,
                    y: (int) round(($this->height - LogoBox::BORDER - $pictureSide) / 2),
                ));
        }

        $this->brand($title, $bottom);

        // The text starts below the brand, or at the top when the brand is at the bottom. On the
        // taller shapes it starts lower, so it doesn't crowd the top and leave the bottom empty.
        $lowest = (int) round($this->height * match ($this->shape) {
            Shape::Landscape => 0,
            Shape::Classic => 0.25,
            Shape::Square => 0.36,
        });
        $lines = $this->shape === Shape::Square ? 4 : 3;
        $start = function () use ($bottom, $gap, $lowest): Point {
            $below = $bottom ? new Point(LogoBox::PADDING, LogoBox::PADDING) : $this->getFeature('brand')->anchor(Position::BottomLeft)->moveY($gap);

            return new Point($below->x(), max($below->y(), $lowest));
        };
        $room = $this->height - LogoBox::BORDER - 2 * LogoBox::PADDING - LogoBox::area($this->shape)['height'] - $gap - max(0, $lowest - LogoBox::PADDING - LogoBox::area($this->shape)['height'] - $gap);
        $label = $this->label();

        $this->addFeature((new TextBox)
            ->name('title')
            ->text($this->title())
            ->color($this->config->theme->getTitleColor())
            ->font($this->config->theme->getTitleFont())
            ->size($title)
            ->lineHeight(1.4)
            // At most three lines (four on a square); a longer title ends with an ellipsis.
            ->box($column, min((int) round($title * 1.4 * $lines), (int) round($room * 0.65)))
            ->position(x: 0, y: 0, relativeTo: $start));

        if ($description = $this->description()) {
            $size = (int) round($title * 0.6);

            $this->addFeature((new TextBox)
                ->name('description')
                ->text($description)
                ->color($this->config->theme->getDescriptionColor())
                ->font($this->config->theme->getDescriptionFont())
                ->size($size)
                ->lineHeight(1.5)
                ->box($column, max((int) round($size * 1.6), (int) round($room * 0.35) - ($label ? $size : 0)))
                ->position(x: 0, y: (int) round($gap * 0.6), relativeTo: fn () => $this->getFeature('title')->anchor(Position::BottomLeft)));
        }

        if ($label) {
            $this->addFeature((new TextBox)
                ->name('label')
                ->text($label)
                ->color($this->config->theme->getCallToActionColor())
                ->font($this->config->theme->getCallToActionFont())
                ->size((int) round($title * 0.4))
                ->box($column, (int) round($title * 0.8))
                ->position(x: 0, y: (int) round($gap * 0.6), relativeTo: fn () => ($this->getFeature('description') ?? $this->getFeature('title'))->anchor(Position::BottomLeft)));
        }
    }

    /**
     * Adds the logo, or the name in type when there is no logo or it can't be drawn.
     */
    private function brand(int $title, bool $bottom): void
    {
        if ($this->card->logo && $this->logo($bottom)) {
            return;
        }

        $area = LogoBox::area($this->shape);

        $this->addFeature((new TextBox)
            ->name('brand')
            ->text($this->card->brandText)
            ->color($this->config->theme->getAccentColor())
            ->font($this->config->theme->getTitleFont())
            ->size(min((int) round($title * 0.7), (int) round($area['height'] * 0.55)))
            ->box($area['width'], $area['height'])
            ->position(
                x: LogoBox::PADDING,
                y: $bottom ? $this->height - LogoBox::BORDER - LogoBox::PADDING : LogoBox::PADDING,
                anchor: $bottom ? Position::BottomLeft : Position::TopLeft,
            ));
    }

    /**
     * Adds the logo fitted in its box, on a pill when it needs one. It returns false, and
     * reports why, when the image can't be read.
     */
    private function logo(bool $bottom): bool
    {
        try {
            $path = (string) $this->card->logo;
            $size = getimagesize($path) ?: throw new \RuntimeException("The share-card logo [{$path}] isn't an image.");
            $fit = LogoBox::fit($size[0], $size[1], $this->shape, $bottom ? 'bottom' : 'top');

            if ($fit === null) {
                return false;
            }

            $box = (new PictureBox)->name('brand')->path($path)->box($fit['width'], $fit['height'])->position(x: $fit['x'], y: $fit['y']);
            // Reading it now means a broken file fails here, not halfway through drawing.
            $box->dimensions();

            $colour = app(Brand::class)->colour($path);
            $pill = $colour ? LogoBox::pill($colour, $this->card->background, $this->card->text) : null;
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        if ($pill) {
            $this->pill($fit, $pill);
        }

        $this->addFeature($box);

        return true;
    }

    /**
     * Draws a rounded backing behind the logo, straight onto the canvas, before the logo.
     *
     * @param  array{x: int, y: int, width: int, height: int}  $fit
     */
    private function pill(array $fit, string $colour): void
    {
        $pad = max(8, (int) round($fit['height'] * 0.2));
        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel($colour));
        $draw->roundRectangle($fit['x'] - $pad, $fit['y'] - $pad, $fit['x'] + $fit['width'] + $pad, $fit['y'] + $fit['height'] + $pad, $pad, $pad);

        $this->canvas->core()->native()->drawImage($draw);
    }

    /**
     * Returns the section the page is in, or null when it would only repeat the brand.
     */
    private function label(): ?string
    {
        $label = $this->callToAction();

        return blank($label) || in_array($label, [$this->card->siteName, $this->card->brandText], true) ? null : $label;
    }
}
