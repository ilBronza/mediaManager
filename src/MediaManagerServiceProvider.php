<?php

namespace IlBronza\MediaManager;

use IlBronza\MediaManager\Console\{ImportCommand, ReconcileCommand, RecoverCommand, ResumeCommand, AdoptCommand};
use IlBronza\MediaManager\Contracts\IdentityStore;
use IlBronza\MediaManager\Http\BrowserController;
use IlBronza\MediaManager\Http\FilesystemBrowserController;
use IlBronza\MediaManager\Http\FileHelperController;
use IlBronza\MediaManager\Console\FileHelperCommand;
use IlBronza\MediaManager\Helpers\Authorization\MediaManagerGatesHelper;
use IlBronza\MediaManager\Http\Middleware\MediaManagerMiddlewareRolesPermissions;
use IlBronza\MediaManager\Integrations\Spatie\{ManagedUrlGenerator, ManagedPathGenerator, ManagedMediaObserver, ManagedFilesystem};
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MediaManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media-manager.php', 'media-manager');
        $this->app->singleton(MediaManager::class);
        $this->app->alias(MediaManager::class, 'mediaManager');
        $this->app->singleton(IdentityStore::class, fn ($app) => $app->make(config('media-manager.identity_store')));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'media-manager');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'media-manager');
        $this->app->booted(function ()
        {
            $this->app->make(MediaManagerGatesHelper::class)->register();
        });
        $this->publishes([__DIR__.'/../config/media-manager.php' => config_path('media-manager.php')], 'media-manager.config');
        $this->commands([ImportCommand::class, ReconcileCommand::class, RecoverCommand::class, ResumeCommand::class, FileHelperCommand::class]);
        if (config('media-manager.spatie')) $this->bootSpatie();
        if (!config('media-manager.routes')) return;
        $middleware = array_merge(config('media-manager.middleware', ['web', 'auth']), [MediaManagerMiddlewareRolesPermissions::class]);

        Route::prefix(config('media-manager.prefix'))->middleware($middleware)->name('media-manager.')->group(function () {
            Route::get('/', [BrowserController::class, 'index'])->name('index');
            Route::get('/filesystem', [FilesystemBrowserController::class, 'index'])->name('filesystem');
            Route::post('/entries/{entry}/helpers/{helper}', [FileHelperController::class, 'run'])
                ->whereIn('helper', array_keys(app(\IlBronza\MediaManager\Helpers\Files\FileHelpersRegistry::class)->all()))
                ->name('helpers');
            Route::post('/directories', [BrowserController::class, 'directory'])->name('directories');
            Route::post('/uploads', [BrowserController::class, 'upload'])->name('uploads');
            Route::post('/scan', [BrowserController::class, 'scan'])->name('scan');
            Route::get('/entries/{entry}/download', [BrowserController::class, 'download'])->name('download');
            Route::post('/entries/{entry}/move', [BrowserController::class, 'move'])->name('move');
            Route::post('/entries/{entry}/recover', [BrowserController::class, 'recover'])->name('recover');
            Route::patch('/entries/{entry}/metadata', [BrowserController::class, 'metadata'])->name('metadata');
        });
    }

    private function bootSpatie(): void
    {
        if (!class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class)) throw new \LogicException('Install Spatie Media Library before enabling the bridge.');
        $urlClass = config('media-library.url_generator');
        if (!in_array($urlClass, [\Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator::class, ManagedUrlGenerator::class], true)) throw new \LogicException('Custom Spatie URL generators require an explicit MediaManager adapter.');
        config([
            'media-manager.legacy_path_generator' => config('media-library.path_generator'),
            'media-manager.legacy_custom_path_generators' => config('media-library.custom_path_generators', []),
            'media-library.url_generator' => ManagedUrlGenerator::class,
            'media-library.path_generator' => ManagedPathGenerator::class,
            'media-library.custom_path_generators' => [],
        ]);
        $class = config('media-library.media_model');
        $class::observe(ManagedMediaObserver::class);
        // Spatie 10 explicitly binds its default Filesystem; Spatie 11 resolves
        // it automatically. Both are safe to adapt, but custom implementations are not.
        $filesystemClass = \Spatie\MediaLibrary\MediaCollections\Filesystem::class;
        if ($this->app->bound($filesystemClass) && get_class($this->app->make($filesystemClass)) !== $filesystemClass)
        {
            throw new \LogicException('A custom Spatie Filesystem binding requires an explicit MediaManager adapter.');
        }
        $this->app->bind(\Spatie\MediaLibrary\MediaCollections\Filesystem::class, ManagedFilesystem::class);
        $uploadEvent = class_exists(\Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent::class)
            ? \Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent::class
            : \Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAdded::class;
        Event::listen($uploadEvent, \IlBronza\MediaManager\Integrations\Spatie\TrackUploadedMedia::class);
        $this->commands([AdoptCommand::class]);
    }
}
