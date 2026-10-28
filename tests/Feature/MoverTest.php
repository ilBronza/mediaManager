<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\MediaManager\Models\Operation;
use IlBronza\MediaManager\Services\Mover;
use IlBronza\MediaManager\Tests\TestCase;

class MoverTest extends TestCase
{
    public function test_move_preserves_uuid_and_bytes_and_completes_journal(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a'); $this->catalog->createDirectory('archive','archive');
        $operation = app(Mover::class)->move($entry,'archive/renamed');
        $this->assertSame('completed',$operation->state); $this->assertFileDoesNotExist($this->root.'/a');
        $this->assertSame('document',file_get_contents($this->root.'/archive/renamed'));
        $this->assertSame($entry->id,$this->identity->read($this->root.'/archive/renamed'));
        $this->assertSame('archive/renamed',$entry->fresh()->path);
    }
    public function test_folder_move_updates_all_descendant_paths(): void
    {
        $this->fixtureFile('old/sub/a'); $this->catalog->importTree('archive');
        $folder = $this->catalog->at('archive','old'); $file = $this->catalog->at('archive','old/sub/a');
        app(Mover::class)->move($folder,'new');
        $this->assertSame('new/sub/a',$file->fresh()->path); $this->assertFileExists($this->root.'/new/sub/a');
    }
    public function test_existing_target_is_never_overwritten(): void
    {
        $this->fixtureFile('a','a'); $this->fixtureFile('b','b'); $entry = $this->catalog->import('archive','a');
        try { app(Mover::class)->move($entry,'b'); $this->fail('Expected conflict'); }
        catch (\RuntimeException $e) { $this->assertSame('a',file_get_contents($this->root.'/a')); $this->assertSame('b',file_get_contents($this->root.'/b')); }
    }
    public function test_interrupted_move_resumes_after_filesystem_step(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a');
        $op = Operation::create(['entry_id'=>$entry->id,'disk'=>'archive','source'=>'a','destination'=>'b','snapshot'=>[
            'identity'=>$this->catalog->filesystem->inspect('archive','a'), 'parent_id'=>$entry->parent_id, 'entries'=>[['id'=>$entry->id,'path'=>'a']],
        ]]);
        link($this->root.'/a',$this->root.'/b');
        app(Mover::class)->resume($op);
        $this->assertSame('b',$entry->fresh()->path); $this->assertSame('completed',$op->fresh()->state); $this->assertFileDoesNotExist($this->root.'/a');
        $this->assertSame('completed',app(Mover::class)->resume($op)->state);
    }
    public function test_recovery_cannot_claim_an_unrelated_destination(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a');
        $op = Operation::create(['entry_id'=>$entry->id,'disk'=>'archive','source'=>'a','destination'=>'b','snapshot'=>[
            'identity'=>$this->catalog->filesystem->inspect('archive','a'), 'parent_id'=>$entry->parent_id, 'entries'=>[['id'=>$entry->id,'path'=>'a']],
        ]]);
        $this->fixtureFile('b');
        try { app(Mover::class)->resume($op); $this->fail('Expected conflict'); }
        catch (\RuntimeException $e) { $this->assertSame('a',$entry->fresh()->path); $this->assertFileExists($this->root.'/a'); }
    }
    public function test_folder_cannot_be_moved_into_itself(): void
    {
        $this->fixtureFile('folder/sub/a'); $this->catalog->importTree('archive');
        $this->expectException(\RuntimeException::class); app(Mover::class)->move($this->catalog->at('archive','folder'),'folder/sub/nested');
    }

    public function test_folder_move_rejects_a_modified_descendant(): void
    {
        $this->fixtureFile('folder/a'); $this->catalog->importTree('archive'); $this->fixtureFile('folder/a','changed');
        try { app(Mover::class)->move($this->catalog->at('archive','folder'),'new'); $this->fail('Expected changed descendant'); }
        catch (\RuntimeException $e) { $this->assertFileExists($this->root.'/folder/a'); $this->assertSame(0,Operation::count()); }
    }

    public function test_cleanup_failure_can_be_resumed_without_repeating_the_database_move(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a'); $info = $this->catalog->filesystem->inspect('archive','a');
        link($this->root.'/a',$this->root.'/b'); $entry->update(['path'=>'b']);
        $op = Operation::create(['entry_id'=>$entry->id,'disk'=>'archive','source'=>'a','destination'=>'b','state'=>'cleanup','snapshot'=>[
            'identity'=>$info,'parent_id'=>$entry->parent_id,'entries'=>[['id'=>$entry->id,'path'=>'a']],
        ]]);
        app(Mover::class)->resume($op);
        $this->assertSame('b',$entry->fresh()->path); $this->assertFileDoesNotExist($this->root.'/a');
    }
}
