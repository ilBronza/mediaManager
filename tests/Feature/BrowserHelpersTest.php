<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\MediaManager\Helpers\Browser\CatalogListingHelper;
use IlBronza\MediaManager\Helpers\Browser\CatalogOverlayHelper;
use IlBronza\MediaManager\Helpers\Browser\FilesystemListingHelper;
use IlBronza\MediaManager\Helpers\Files\ReadCatalogEntryHelper;
use IlBronza\MediaManager\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

class BrowserHelpersTest extends TestCase
{
    public function test_single_entry_details_show_metadata_and_links_without_accessing_storage(): void
    {
        $this->fixtureFile('document.txt');
        $entry = $this->catalog->import('archive', 'document.txt', ['note' => '<script>danger()</script>']);
        $this->catalog->link($entry, $entry->parent, 'allegato');

        config(['filesystems.disks.archive.root' => $this->root.'/offline']);

        $details = app(ReadCatalogEntryHelper::class)->run($entry);

        $this->assertSame($entry->id, $details['id']);
        $this->assertSame('allegato', $details['links'][0]['role']);

        $response = $this->get('/media-manager?entry='.$entry->id)->assertOk();
        $response->assertSee('Informazioni sul file');
        $response->assertSee('Collegamenti registrati');
        $response->assertSee('allegato');
        $response->assertSee('&lt;script&gt;danger()&lt;/script&gt;', false);
        $response->assertDontSee('<script>danger()</script>', false);

        Gate::define('media-manager.view', fn ($user = null, $item = null) => $item->id !== $entry->id);

        $this->get('/media-manager?entry='.$entry->id)->assertForbidden();
    }

    public function test_readers_can_work_on_one_file_without_http_or_implicit_registration(): void
    {
        $this->fixtureFile('registered.txt');
        $entry = $this->catalog->import('archive', 'registered.txt');
        $this->fixtureFile('external.txt');

        $catalog = app(CatalogListingHelper::class);
        $filesystem = app(FilesystemListingHelper::class);
        $overlay = app(CatalogOverlayHelper::class);

        $registered = $catalog->read('archive', 'registered.txt');
        $external = $filesystem->read('archive', 'external.txt');

        $this->assertSame($entry->id, $registered['entry']->id);
        $this->assertNull($catalog->read('archive', 'external.txt'));
        $this->assertSame(8, $external['size']);
        $this->assertNull($overlay->attachFile('archive', $external)['entry']);
        $this->assertDatabaseCount('media_manager_entries', 2);
    }

    public function test_catalog_renders_standard_helper_icons_and_can_export_a_local_preview(): void
    {
        $this->fixtureFile('Contratto Rossi.pdf', 'contract');
        $this->fixtureFile('Preventivo.xlsx', 'estimate');
        $this->fixtureFile('Dossier 2026/documento.txt');
        $this->fixtureFile('Fotografie/immagine.jpg');
        $this->catalog->importTree('archive');
        $this->fixtureFile('Appunti non indicizzati.txt');
        $this->fixtureFile('Da classificare/bozza.txt');

        $catalog = $this->get('/media-manager')->assertOk();
        $filesystem = $this->get('/media-manager/filesystem')->assertOk();

        $this->assertTrue($catalog->viewData('table')->getFieldByName('name')->isSortable());
        $this->assertFalse($catalog->viewData('table')->getFieldByName('helpers')->isSortable());
        $this->assertFalse($catalog->viewData('table')->hasReloadButton());
        $this->assertStringContainsString('Scrivi UUID', $catalog->viewData('table')->elements->last()->helpers);
        $this->assertStringNotContainsString('<form', $filesystem->getContent());

        if (getenv('MEDIA_MANAGER_RENDER_PREVIEW') === '1')
        {
            $directory = __DIR__.'/../runtime/browser-preview';
            @mkdir($directory, 0777, true);
            file_put_contents($directory.'/catalog.html', $catalog->getContent());
            file_put_contents($directory.'/filesystem.html', $filesystem->getContent());
            $entry = $this->catalog->at('archive', 'Contratto Rossi.pdf');
            $entry->update(['metadata' => ['descrizione' => 'Contratto cliente', 'anno' => 2026]]);
            $details = $this->get('/media-manager?entry='.$entry->id)->assertOk();
            file_put_contents($directory.'/details.html', $details->getContent());
        }
    }
}
