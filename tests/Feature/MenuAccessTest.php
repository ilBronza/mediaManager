<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\MediaManager\Helpers\Authorization\MediaManagerGatesHelper;
use IlBronza\MediaManager\Http\Middleware\MediaManagerMiddlewareRolesPermissions;
use IlBronza\MediaManager\MediaManager;
use IlBronza\MediaManager\Tests\Fixtures\MenuUser;
use IlBronza\MediaManager\Tests\Fixtures\HostMenu;
use IlBronza\MediaManager\Tests\TestCase;
use IlBronza\Menu\Menu;
use IlBronza\Menu\MenuServiceProvider;
use IlBronza\Menu\Helpers\MenuPayloadBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class MenuAccessTest extends TestCase
{
    protected bool $defineMediaGates = false;
    protected bool $enforceRoleMiddleware = true;

    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [MenuServiceProvider::class]);
    }

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('menu.serviceProviders', ['mediaManager']);
        $app['config']->set('menu.logo.show', false);
        $app['config']->set('app.template', 'uikittemplate');
        // Named menu links must keep working with a custom installation prefix.
        $app['config']->set('media-manager.prefix', 'tools/media');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('datatabledata', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('key');
            $table->json('data');
            $table->timestamps();
        });
    }

    public function test_each_allowed_role_gets_the_standard_menu_and_can_open_both_views(): void
    {
        $this->assertInstanceOf(MediaManager::class, app('mediaManager'));

        foreach (['administrator', 'mediaManager'] as $role)
        {
            $this->actingAs(new MenuUser([$role]));

            $menu = new HostMenu();
            $this->app->instance('menu', $menu);
            $menu->loadItemsFromServiceProviders();
            $menu->loadItemsFromServiceProviders();

            $group = $menu->getButtonByName('mediaManager');

            $this->assertNotNull($group);
            $this->assertCount(2, $group->getChildren());
            $this->assertSame(['administrator', 'mediaManager'], $group->getRoles());
            $this->assertSame('Catalogo', trans('media-manager::menu.catalog'));

            $catalog = $group->getChildren()->firstWhere('name', 'mediaManager.catalog');
            $filesystem = $group->getChildren()->firstWhere('name', 'mediaManager.filesystem');

            $this->assertSame(route('media-manager.index', ['disk' => 'archive']), $catalog->href);
            $this->assertSame(route('media-manager.filesystem', ['disk' => 'archive']), $filesystem->href);
            $this->assertStringContainsString('/tools/media', $catalog->href);

            $payload = new MenuPayloadBuilder($menu);
            $root = $payload->root();
            $menuGroup = $root['navbars'][0]['items'][0];
            $children = $payload->children($menuGroup['id']);

            $this->assertSame('mediaManager', $menuGroup['name']);
            $this->assertSame(['mediaManager.catalog', 'mediaManager.filesystem'], array_column($children['items'], 'name'));

            $this->get($catalog->href)->assertOk();
            $this->get($filesystem->href)->assertOk();
        }
    }

    public function test_other_roles_are_denied_even_with_a_permissive_global_gate(): void
    {
        $this->fixtureFile('document.txt');
        $entry = $this->catalog->import('archive', 'document.txt');

        Gate::before(fn ($user, $ability) => true);

        foreach (['editor', 'superadmin', 'mediamanager'] as $role)
        {
            $this->actingAs(new MenuUser([$role]));
            $menu = new Menu();
            $this->app->instance('menu', $menu);
            $menu->loadItemsFromServiceProviders();

            $this->assertNull($menu->getButtonByName('mediaManager'));
            $this->get(route('media-manager.index'))->assertForbidden();
            $this->get(route('media-manager.filesystem'))->assertForbidden();
            $this->get(route('media-manager.download', $entry))->assertForbidden();
            $this->post(route('media-manager.scan'), ['disk' => 'archive'])->assertForbidden();
            $this->post(route('media-manager.helpers', ['entry' => $entry, 'helper' => 'write-uuid']))->assertForbidden();
            $this->patch(route('media-manager.metadata', $entry), ['metadata' => '{"changed":true}'])->assertForbidden();
        }

        $this->assertSame([], $entry->fresh()->metadata);
    }

    public function test_guest_and_empty_role_configuration_fail_closed(): void
    {
        app('menu')->loadItemsFromServiceProviders();

        $this->assertNull(app('menu')->getButtonByName('mediaManager'));
        $this->get(route('media-manager.index'))->assertForbidden();

        $this->actingAs(new MenuUser(['administrator']));
        config(['media-manager.defaultRoles' => []]);

        app('menu')->loadItemsFromServiceProviders();

        $this->assertNull(app('menu')->getButtonByName('mediaManager'));
        $this->get(route('media-manager.index'))->assertForbidden();
    }

    public function test_host_gates_remain_authoritative_and_filter_menu_children(): void
    {
        $this->actingAs(new MenuUser(['mediaManager']));
        Gate::define('media-manager.filesystem', fn ($user, $disk) => false);
        Gate::define('media-manager.manage', fn ($user, $disk, $entry) => false);

        app(MediaManagerGatesHelper::class)->register();
        app('menu')->loadItemsFromServiceProviders();

        $group = app('menu')->getButtonByName('mediaManager');

        $this->assertCount(1, $group->getChildren());
        $this->assertNull($group->getChildren()->firstWhere('name', 'mediaManager.filesystem'));
        $this->get(route('media-manager.index'))->assertOk();
        $this->get(route('media-manager.filesystem'))->assertForbidden();

        $this->fixtureFile('a');
        $entry = $this->catalog->import('archive', 'a');

        $this->assertFalse(Gate::allows('media-manager.manage', ['archive', $entry]));
        $this->assertFalse(Gate::has('media-manager.view-dossier'));
        $this->assertFalse(Gate::allows('media-manager.browse', ['disabled-disk']));
    }

    public function test_menu_does_not_create_dead_links_without_enabled_disks_or_routes(): void
    {
        $this->actingAs(new MenuUser(['administrator']));
        config(['media-manager.disks' => []]);

        app('menu')->loadItemsFromServiceProviders();

        $this->assertNull(app('menu')->getButtonByName('mediaManager'));

        config(['media-manager.disks' => ['archive'], 'media-manager.routes' => false]);

        app('menu')->loadItemsFromServiceProviders();

        $this->assertNull(app('menu')->getButtonByName('mediaManager'));
    }

    public function test_role_middleware_is_attached_to_every_package_route_even_with_custom_middleware(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(function ($route)
        {
            return str_starts_with($route->getName() ?? '', 'media-manager.');
        });

        $this->assertNotEmpty($routes);

        foreach ($routes as $route)
        {
            $this->assertContains(MediaManagerMiddlewareRolesPermissions::class, $route->gatherMiddleware());
        }
    }
}
