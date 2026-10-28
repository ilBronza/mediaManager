<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\Datatables\Datatables;
use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

class FilesystemBrowserTest extends TestCase
{
    public function test_views_share_layout_but_show_different_sources_without_registering_files(): void
    {
        $this->fixtureFile('known.txt');
        $known = $this->catalog->import('archive', 'known.txt');
        $this->fixtureFile('loose.txt');
        $records = Entry::all()->toArray();
        $tags = $this->identity->tags;

        $catalog = $this->get('/media-manager')->assertOk();
        $catalog->assertSee('data-media-manager-browser="entries"', false);
        $catalog->assertSee('known.txt')->assertDontSee('loose.txt');
        $catalog->assertSee('data-test-host-layout', false);

        $filesystem = $this->get('/media-manager/filesystem')->assertOk();
        $filesystem->assertSee('data-media-manager-browser="filesystem"', false);
        $filesystem->assertSee('known.txt')->assertSee('loose.txt');
        $filesystem->assertSee('data-test-host-layout', false);

        $rows = $filesystem->viewData('table')->elements->keyBy('path');
        $this->assertStringContainsString('A database', $rows['known.txt']->catalog);
        $this->assertSame('', $rows['known.txt']->linked);
        $this->assertSame('', $rows['loose.txt']->catalog);
        $this->assertSame('', $rows['loose.txt']->helpers);
        $this->assertSame($records, Entry::all()->toArray());
        $this->assertSame($tags, $this->identity->tags);
        $this->assertSame($known->id, $this->catalog->at('archive', 'known.txt')->id);
    }

    public function test_physical_browser_navigates_unindexed_nested_and_empty_directories(): void
    {
        $this->fixtureFile('external/sub/file.txt');
        mkdir($this->root.'/empty');

        $this->get('/media-manager/filesystem?path=external%2Fsub')
            ->assertOk()
            ->assertSee('file.txt')
            ->assertSee('Cartella superiore');

        $response = $this->get('/media-manager/filesystem?path=empty')->assertOk();
        $this->assertCount(0, $response->viewData('table')->elements);
        $this->assertDatabaseCount('media_manager_entries', 0);
        $this->assertSame([], $this->identity->tags);
    }

    public function test_catalog_keeps_missing_records_and_is_available_when_storage_is_offline(): void
    {
        $this->fixtureFile('missing.txt');
        $entry = $this->catalog->import('archive', 'missing.txt');
        $entry->update(['status' => 'missing']);
        unlink($this->root.'/missing.txt');

        $this->get('/media-manager')->assertOk()->assertSee('missing.txt')->assertSee('Mancante');
        $this->get('/media-manager/filesystem')->assertOk()->assertDontSee('missing.txt');

        config(['filesystems.disks.archive.root' => $this->root.'/offline']);

        $this->get('/media-manager')->assertOk()->assertSee('missing.txt');
        $this->get('/media-manager/filesystem')->assertStatus(409);
        $this->assertDirectoryDoesNotExist($this->root.'/offline');
    }

    public function test_filesystem_permission_is_separate_and_known_entries_remain_protected(): void
    {
        $this->fixtureFile('secret/file.txt');
        $this->catalog->importTree('archive');
        $secret = $this->catalog->at('archive', 'secret');

        Gate::define('media-manager.filesystem', fn ($user = null, $disk = null) => false);
        $this->get('/media-manager/filesystem')->assertForbidden();
        $this->get('/media-manager')->assertOk();

        Gate::define('media-manager.filesystem', fn ($user = null, $disk = null) => $disk === 'archive');
        Gate::define('media-manager.view', fn ($user = null, $entry = null) => $entry->id !== $secret->id);

        $this->get('/media-manager/filesystem')->assertOk()->assertDontSee('secret');
        $this->get('/media-manager/filesystem?path=secret')->assertForbidden();
        $this->get('/media-manager?folder='.$secret->id)->assertForbidden();
    }

    public function test_path_traversal_links_and_reserved_files_are_not_browsable(): void
    {
        $this->fixtureFile('visible.txt');
        $this->fixtureFile('.media-manager-hidden');
        symlink($this->root.'/visible.txt', $this->root.'/alias');
        symlink($this->root, $this->root.'/loop');

        $this->get('/media-manager/filesystem')->assertOk()
            ->assertSee('visible.txt')->assertDontSee('alias')->assertDontSee('loop')->assertDontSee('.media-manager-hidden');
        $this->get('/media-manager/filesystem?path=..')->assertStatus(422);
        $this->get('/media-manager/filesystem?path=loop')->assertStatus(409);
        $this->get('/media-manager/filesystem?path=absent')->assertNotFound();
        $this->get('/media-manager/filesystem?disk=other')->assertForbidden();
    }

    public function test_datatable_gets_every_row_for_search_and_sorting_and_has_standard_field_groups(): void
    {
        for ($index = 0; $index < 65; $index++)
        {
            $this->fixtureFile('file-'.$index.'.txt');
        }

        $response = $this->get('/media-manager/filesystem')->assertOk();
        $table = $response->viewData('table');

        $this->assertInstanceOf(Datatables::class, $table);
        $this->assertTrue($table->hasSorting());
        $this->assertSame(50, $table->getPageLength());
        $this->assertCount(65, $table->elements);
        $this->assertSame('text', $table->getFieldByName('name')->getFilterType());
        $response->assertSee('wannabedatatable')->assertSee('uk-width-1-4@m');
    }

    public function test_numeric_names_and_superseded_records_are_matched_correctly(): void
    {
        $this->fixtureFile('123');
        $this->catalog->import('archive', '123');
        $this->fixtureFile('old.txt');
        $old = $this->catalog->import('archive', 'old.txt');
        $old->update(['status' => 'superseded']);

        $response = $this->get('/media-manager/filesystem')->assertOk();
        $rows = $response->viewData('table')->elements->keyBy('path');

        $this->assertStringContainsString('A database', $rows['123']->catalog);
        $this->assertSame('', $rows['old.txt']->catalog);
        $this->get('/media-manager')->assertOk()->assertDontSee('old.txt');
    }
}
