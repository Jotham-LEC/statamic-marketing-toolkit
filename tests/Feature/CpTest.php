<?php

use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use JothamLec\Seo\Cp\Navigation;
use JothamLec\Seo\Widgets\SeoWidget;
use Statamic\CP\Navigation\NavItem;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;

/**
 * @param  list<string>  $permissions
 */
function cpUser(array $permissions = [], bool $super = false): Statamic\Contracts\Auth\User
{
    $user = User::make()->email('someone@example.test');

    if ($super) {
        $user->makeSuper();
    } else {
        Role::make('seo-role')->permissions(['access cp', ...$permissions])->save();
        $user->assignRole('seo-role');
    }

    return tap($user)->save();
}

/**
 * The CP navigation's items under Tools, keyed by name, as the user sees them.
 *
 * @return Collection<string, NavItem>
 */
function toolsNav(): Collection
{
    // AddonTestCase mocks the nav after the addon has extended the real one.
    Nav::swap(new Statamic\CP\Navigation\Nav);
    Navigation::register();

    $tools = collect(Nav::build())->firstWhere('display', 'Tools');

    return collect($tools['items'] ?? [])->keyBy(fn ($item) => $item->display());
}

test('the addon registers its permissions in an SEO group', function () {
    $permissions = Permission::boot()->all()->filter(fn ($permission) => $permission->group() === 'seo')->map->value()->values()->all();

    expect($permissions)->toBe(['view seo', 'manage seo redirects', 'run seo reports']);
});

test('Tools → SEO opens the overview and links to the brand global', function () {
    seoGlobal(['site_name' => 'Acme']);
    $this->actingAs(cpUser(super: true));

    $seo = toolsNav()->get('SEO');

    expect($seo)->not->toBeNull()
        ->and($seo->url())->toBe(cp_route('seo.index'))
        ->and(collect($seo->resolveChildren()->children())->map->display()->all())->toBe(['Brand & defaults']);

    $this->get(cp_route('seo.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('seo::Overview', false)
            ->where('siteName', 'Acme')
            ->where('global.exists', true)
            ->where('files.0', ['label' => 'Sitemap', 'url' => 'https://example.test/sitemap.xml']));
});

test('without the global the overview says so', function () {
    $this->actingAs(cpUser(super: true));

    $this->get(cp_route('seo.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('global', ['exists' => false, 'url' => null]));
});

test('SEO is hidden from people without "view seo"', function () {
    $this->actingAs(cpUser());

    expect(toolsNav()->has('SEO'))->toBeFalse();
    $this->get(cp_route('seo.index'))->assertForbidden();
});

test('the dashboard widget shows its empty states until reports and 404s exist', function () {
    $this->actingAs(cpUser(['view seo']));

    $component = (new SeoWidget)->component();

    expect($component)->toBeInstanceOf(VueComponent::class)
        ->and($component->toArray())->toBe([
            'name' => 'seo-widget',
            'props' => ['title' => 'SEO', 'report' => null, 'notFound' => [], 'url' => cp_route('seo.index')],
        ]);
});

test('the dashboard widget is left out for people without "view seo"', function () {
    $this->actingAs(cpUser());

    expect((new SeoWidget)->component())->toBeNull();
});
