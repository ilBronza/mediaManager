<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\MediaManager\Helpers\Files\CalculateSha256Helper;
use IlBronza\MediaManager\Helpers\Files\ReadUuidHelper;
use IlBronza\MediaManager\Helpers\Files\WriteUuidHelper;
use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

class FileHelpersTest extends TestCase
{
    public function test_single_file_hash_helper_observes_changes_without_replacing_the_baseline(): void
    {
        $this->fixtureFile('document.txt');
        $entry = $this->catalog->import('archive', 'document.txt');
        $record = $entry->fresh()->toArray();
        $this->fixtureFile('document.txt', 'new embedded metadata');

        $result = app(CalculateSha256Helper::class)->run($entry);

        $this->assertFalse($result['matches']);
        $this->assertSame(hash('sha256', 'new embedded metadata'), $result['sha256']);
        $this->assertSame($record, $entry->fresh()->toArray());
    }

    public function test_write_and_read_uuid_helpers_reuse_the_entry_id_without_changing_bytes(): void
    {
        $this->identity->supported = false;
        $this->fixtureFile('document.txt');
        $entry = $this->catalog->import('archive', 'document.txt');
        $this->identity->supported = true;

        $written = app(WriteUuidHelper::class)->run($entry);
        $read = app(ReadUuidHelper::class)->run($entry);

        $this->assertSame($entry->id, $written['uuid']);
        $this->assertSame($entry->id, $read['uuid']);
        $this->assertTrue($entry->fresh()->identity_tagged);
        $this->assertSame('document', file_get_contents($this->root.'/document.txt'));
        $this->assertSame(hash('sha256', 'document'), $entry->sha256);
    }

    public function test_uuid_writer_refuses_changed_content(): void
    {
        $this->fixtureFile('a');
        $entry = $this->catalog->import('archive', 'a');
        $this->fixtureFile('a', 'changed');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Il file è cambiato');

        app(WriteUuidHelper::class)->run($entry);
    }

    public function test_uuid_writer_does_not_overwrite_a_different_identity(): void
    {
        $this->fixtureFile('a');
        $entry = $this->catalog->import('archive', 'a');
        $foreignUuid = (string) Str::uuid();
        $this->identity->write($this->root.'/a', $foreignUuid);

        $this->post('/media-manager/entries/'.$entry->id.'/helpers/write-uuid')
            ->assertSessionHasErrors('helper');

        $this->assertSame($foreignUuid, $this->identity->read($this->root.'/a'));
    }

    public function test_uuid_failure_does_not_report_success(): void
    {
        $this->identity->supported = false;
        $this->fixtureFile('a');
        $entry = $this->catalog->import('archive', 'a');

        $this->post('/media-manager/entries/'.$entry->id.'/helpers/write-uuid')
            ->assertSessionHasErrors('helper');

        $this->assertFalse($entry->fresh()->identity_tagged);
    }

    public function test_helpers_require_a_persisted_file_record(): void
    {
        $this->fixtureFile('unindexed.txt');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('già registrato');

        app(CalculateSha256Helper::class)->run(new Entry(['disk' => 'archive', 'path' => 'unindexed.txt']));
    }

    public function test_catalog_icons_and_endpoints_respect_read_and_write_permissions(): void
    {
        $this->fixtureFile('a');
        $entry = $this->catalog->import('archive', 'a');
        Gate::define('media-manager.manage', fn ($user = null, $disk = null, $entry = null) => false);

        $response = $this->get('/media-manager')->assertOk();
        $helpers = $response->viewData('table')->elements->first()->helpers;
        $this->assertStringContainsString('Calcola SHA-256', $helpers);
        $this->assertStringContainsString('Leggi UUID', $helpers);
        $this->assertStringNotContainsString('Scrivi UUID', $helpers);

        $this->post('/media-manager/entries/'.$entry->id.'/helpers/sha256')
            ->assertRedirect()->assertSessionHas('file_helper_result.matches', true);
        $this->post('/media-manager/entries/'.$entry->id.'/helpers/write-uuid')->assertForbidden();

        Gate::define('media-manager.view', fn ($user = null, $entry = null) => false);
        $this->post('/media-manager/entries/'.$entry->id.'/helpers/read-uuid')->assertForbidden();
        $this->post('/media-manager/entries/'.$entry->id.'/helpers/not-an-action')->assertNotFound();
    }

    public function test_helpers_are_available_from_artisan_for_one_file(): void
    {
        $this->fixtureFile('a');
        $entry = $this->catalog->import('archive', 'a');

        $this->artisan('media-manager:file-helper', ['entry' => $entry->id, 'helper' => 'sha256'])->assertSuccessful();
        $this->artisan('media-manager:file-helper', ['entry' => $entry->id, 'helper' => 'read-uuid'])->assertSuccessful();
        $this->artisan('media-manager:file-helper', ['entry' => $entry->id, 'helper' => 'write-uuid'])->assertSuccessful();

        $this->fixtureFile('a', 'changed');

        $this->artisan('media-manager:file-helper', ['entry' => $entry->id, 'helper' => 'sha256'])->assertFailed();
        $this->assertSame(hash('sha256', 'document'), $entry->fresh()->sha256);
        $this->artisan('media-manager:file-helper', ['entry' => 'not-a-record', 'helper' => 'sha256'])->assertFailed();
    }
}
