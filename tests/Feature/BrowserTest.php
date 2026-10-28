<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\MediaManager\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

class BrowserTest extends TestCase
{
    public function test_browser_renders_real_folders_and_escapes_names(): void
    {
        $this->fixtureFile('<script>.txt'); $this->fixtureFile('folder/a'); $this->catalog->importTree('archive');
        $this->get('/media-manager')->assertOk()->assertSee('folder')->assertSee('&lt;script&gt;.txt',false)->assertDontSee('<script>.txt',false);
    }
    public function test_browse_gate_is_required(): void
    {
        Gate::define('media-manager.browse',fn ($user = null,$disk = null) => false);
        $this->get('/media-manager')->assertForbidden();
    }
    public function test_direct_download_respects_entry_permission(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a');
        Gate::define('media-manager.view',fn ($user = null,$entry = null) => false);
        $this->get('/media-manager/entries/'.$entry->id.'/download')->assertForbidden();
    }
    public function test_download_is_attachment_and_checks_modified_content(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a');
        $this->get('/media-manager/entries/'.$entry->id.'/download')->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->fixtureFile('a','replacement'); $this->get('/media-manager/entries/'.$entry->id.'/download')->assertStatus(409);
    }
    public function test_move_requires_both_source_and_destination_permission(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a'); $parent = $this->catalog->createDirectory('archive','restricted');
        Gate::define('media-manager.manage',fn ($user = null,$disk = null,$item = null) => $item?->id !== $parent->id);
        $this->post('/media-manager/entries/'.$entry->id.'/move',['path'=>'restricted/a'])->assertForbidden();
        $this->assertFileExists($this->root.'/a');
    }
    public function test_metadata_updates_do_not_modify_bytes(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a');
        $this->patch('/media-manager/entries/'.$entry->id.'/metadata',['metadata'=>'{"note":"Document"}'])->assertRedirect();
        $this->assertSame(['note'=>'Document'],$entry->fresh()->metadata); $this->assertSame('document',file_get_contents($this->root.'/a'));
    }
}
