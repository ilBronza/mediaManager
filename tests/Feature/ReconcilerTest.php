<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\MediaManager\Services\Reconciler;
use IlBronza\MediaManager\Tests\TestCase;

class ReconcilerTest extends TestCase
{
    public function test_external_rename_is_previewed_then_relinked_by_uuid(): void
    {
        $this->fixtureFile('old.pdf'); $entry = $this->catalog->import('archive','old.pdf',['note'=>'keep']);
        rename($this->root.'/old.pdf',$this->root.'/new.pdf');
        $report = app(Reconciler::class)->scan('archive');
        $this->assertSame('relocated', collect($report['entries'])->firstWhere('id',$entry->id)['status']);
        $this->assertSame('old.pdf',$entry->fresh()->path);
        app(Reconciler::class)->scan('archive',true);
        $this->assertSame('new.pdf',$entry->fresh()->path); $this->assertSame(['note'=>'keep'],$entry->fresh()->metadata);
    }
    public function test_duplicate_uuid_does_not_reassign_a_record(): void
    {
        $this->fixtureFile('old'); $entry = $this->catalog->import('archive','old');
        copy($this->root.'/old',$this->root.'/copy'); $this->identity->write($this->root.'/copy',$entry->id);
        app(Reconciler::class)->scan('archive',true);
        $this->assertSame('conflict',$entry->fresh()->status); $this->assertSame('old',$entry->fresh()->path);
    }
    public function test_hash_candidates_require_explicit_selection_even_when_unique(): void
    {
        $this->identity->supported = false; $this->fixtureFile('old'); $entry = $this->catalog->import('archive','old');
        rename($this->root.'/old',$this->root.'/new');
        app(Reconciler::class)->scan('archive',true);
        $this->assertSame('candidate',$entry->fresh()->status); $this->assertSame('old',$entry->fresh()->path);
        app(Reconciler::class)->recover($entry,'new');
        $this->assertSame('new',$entry->fresh()->path); $this->assertSame('present',$entry->fresh()->status);
    }
    public function test_identical_copies_are_all_reported_as_candidates(): void
    {
        $this->identity->supported = false; $this->fixtureFile('old'); $entry = $this->catalog->import('archive','old');
        rename($this->root.'/old',$this->root.'/one'); copy($this->root.'/one',$this->root.'/two');
        $report = app(Reconciler::class)->scan('archive',true);
        $this->assertCount(2,collect($report['entries'])->firstWhere('id',$entry->id)['candidates']);
        $this->assertSame('old',$entry->fresh()->path);
    }
    public function test_modified_content_is_not_accepted_as_a_new_baseline(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a'); $originalHash = $entry->sha256;
        $this->fixtureFile('a','changed'); app(Reconciler::class)->scan('archive',true);
        $this->assertSame('modified',$entry->fresh()->status); $this->assertSame($originalHash,$entry->fresh()->sha256);
    }
    public function test_missing_file_preserves_record(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a'); unlink($this->root.'/a');
        app(Reconciler::class)->scan('archive',true);
        $this->assertSame('missing',$entry->fresh()->status);
    }
    public function test_external_directory_move_preserves_parents_and_child_identity(): void
    {
        $this->fixtureFile('old/sub/a'); $this->catalog->importTree('archive');
        $folder = $this->catalog->at('archive','old'); $file = $this->catalog->at('archive','old/sub/a');
        rename($this->root.'/old',$this->root.'/new'); app(Reconciler::class)->scan('archive',true);
        $this->assertSame('new',$folder->fresh()->path); $this->assertSame('new/sub/a',$file->fresh()->path);
        $this->assertSame('new/sub',$file->fresh()->parent->path);
    }
    public function test_recovery_rejects_changed_or_already_indexed_candidate(): void
    {
        $this->fixtureFile('old'); $entry = $this->catalog->import('archive','old'); unlink($this->root.'/old'); $this->fixtureFile('new','different');
        $this->expectException(\RuntimeException::class); app(Reconciler::class)->recover($entry,'new');
    }
    public function test_unavailable_root_does_not_mark_everything_missing(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a');
        config(['filesystems.disks.archive.root'=>$this->root.'/unmounted']); app('filesystem')->forgetDisk('archive');
        try { app(Reconciler::class)->scan('archive',true); $this->fail('Expected failure'); }
        catch (\RuntimeException $e) { $this->assertSame('present',$entry->fresh()->status); }
    }

    public function test_replaced_mount_directory_is_not_treated_as_an_empty_archive(): void
    {
        $this->identity->supported = false; $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a');
        $replacement = $this->root.'/replacement'; mkdir($replacement);
        config(['filesystems.disks.archive.root'=>$replacement]);
        try { app(Reconciler::class)->scan('archive',true); $this->fail('Expected root mismatch'); }
        catch (\RuntimeException $e) { $this->assertSame('present',$entry->fresh()->status); }
    }

    public function test_import_cannot_create_a_new_record_for_a_missing_files_content(): void
    {
        $this->identity->supported = false; $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a');
        rename($this->root.'/a',$this->root.'/new');
        $this->expectException(\RuntimeException::class); $this->catalog->import('archive','new');
    }
}
