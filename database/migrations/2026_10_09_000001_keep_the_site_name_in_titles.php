<?php

use Illuminate\Database\Migrations\Migration;
use JothamLec\MarketingToolkit\Support\TitleSiteName;

/*
 * A site with a title separator saved keeps the site name in its titles once
 * someone saves SEO & brand: the new toggle is turned on where it would
 * otherwise show off. See TitleSiteName.
 */
return new class extends Migration
{
    public function up(): void
    {
        TitleSiteName::keep();
    }

    public function down(): void
    {
        // The toggle matches what the titles already did.
    }
};
