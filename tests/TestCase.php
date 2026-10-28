<?php

namespace IlBronza\MediaManager\Tests;

use IlBronza\MediaManager\Contracts\IdentityStore;
use IlBronza\MediaManager\MediaManagerServiceProvider;
use IlBronza\MediaManager\Services\Catalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{File, Gate, Schema};

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected bool $spatie = false;
    protected bool $defineMediaGates = true;
    protected bool $enforceRoleMiddleware = false;
    protected string $root;
    protected Catalog $catalog;
    protected FakeIdentityStore $identity;
    public static function applicationBasePath(): string { return __DIR__.'/runtime'; }
    protected function getPackageProviders($app)
    {
        return array_merge([
            \IlBronza\UikitTemplate\UikitTemplateServiceProvider::class,
            \IlBronza\Buttons\ButtonsServiceProvider::class,
            \IlBronza\Form\FormServiceProvider::class,
            \IlBronza\FormField\FormFieldServiceProvider::class,
            \IlBronza\Datatables\DatatablesServiceProvider::class,
        ], $this->spatie ? [\Spatie\MediaLibrary\MediaLibraryServiceProvider::class] : [], [MediaManagerServiceProvider::class]);
    }
    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]);
        $app['config']->set('media-manager.disks', ['archive']);
        $app['config']->set('media-manager.middleware', ['web']);
        $app['config']->set('media-manager.layout', 'media-manager-test::layout');
        $app['config']->set('app.locale', 'it');
        $app['config']->set('datatables.errors.useAErr', false);
        $app['config']->set('media-manager.identity_store', FakeIdentityStore::class);
        $app['config']->set('media-manager.spatie', $this->spatie);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a',32)));
    }
    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->enforceRoleMiddleware)
        {
            $this->withoutMiddleware(\IlBronza\MediaManager\Http\Middleware\MediaManagerMiddlewareRolesPermissions::class);
        }
        app('view')->addNamespace('media-manager-test', __DIR__.'/Fixtures/views');
        $this->root = __DIR__.'/runtime/storage/test-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0777, true);
        config(['filesystems.disks.archive' => ['driver'=>'local','root'=>$this->root, 'url'=>'/archive', 'throw'=>true]]);
        (require __DIR__.'/../database/migrations/2026_10_28_000001_create_media_manager_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_10_28_000002_allow_superseded_media_locations.php')->up();
        $this->catalog = app(Catalog::class);
        $this->identity = app(IdentityStore::class);
        if ($this->defineMediaGates)
        {
            Gate::define('media-manager.browse', fn ($user = null, $disk = null) => $disk === 'archive');
            Gate::define('media-manager.filesystem', fn ($user = null, $disk = null) => $disk === 'archive');
            Gate::define('media-manager.view', fn ($user = null, $entry = null) => true);
            Gate::define('media-manager.manage', fn ($user = null, $disk = null, $entry = null) => true);
            Gate::define('media-manager.reconcile', fn ($user = null, $disk = null) => true);
        }
    }
    protected function tearDown(): void
    {
        if (isset($this->root)) File::deleteDirectory($this->root);
        parent::tearDown();
    }
    protected function fixtureFile(string $path, string $contents = 'document'): void
    {
        if (!is_dir(dirname($this->root.'/'.$path))) mkdir(dirname($this->root.'/'.$path), 0777, true);
        file_put_contents($this->root.'/'.$path, $contents);
    }
    protected function mediaTable(): void
    {
        Schema::create('media', function (Blueprint $t) {
            $t->id(); $t->morphs('model'); $t->uuid('uuid')->nullable()->unique();
            foreach (['collection_name','name','file_name','disk'] as $field) $t->string($field);
            $t->string('mime_type')->nullable(); $t->string('conversions_disk')->nullable(); $t->unsignedBigInteger('size');
            foreach (['manipulations','custom_properties','generated_conversions','responsive_images'] as $field) $t->json($field);
            $t->unsignedInteger('order_column')->nullable(); $t->timestamps();
        });
    }
}
