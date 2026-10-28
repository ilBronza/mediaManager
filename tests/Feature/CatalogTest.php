<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Tests\TestCase;
use RuntimeException;

class CatalogTest extends TestCase
{
    public function test_import_mirrors_directories_and_retains_metadata_and_identity(): void
    {
        $this->fixtureFile('clients/rossi/contract.pdf');
        $entry = $this->catalog->import('archive', 'clients/rossi/contract.pdf', ['expiry'=>'2027-01-01']);
        $this->assertSame('clients/rossi', $entry->parent->path);
        $this->assertSame('clients', $entry->parent->parent->path);
        $this->assertSame('', $entry->parent->parent->parent->path);
        $this->assertSame(hash('sha256','document'), $entry->sha256);
        $this->assertSame($entry->id, $this->identity->read($this->root.'/clients/rossi/contract.pdf'));
        $this->assertSame($entry->id, $this->catalog->import('archive', $entry->path)->id);
        $this->assertSame(['expiry'=>'2027-01-01'], $entry->fresh()->metadata);
        $this->assertSame(4, Entry::count());
    }
    public function test_import_without_xattrs_still_tracks_hashes(): void
    {
        $this->identity->supported = false;
        $this->fixtureFile('a.txt'); $entry = $this->catalog->import('archive','a.txt');
        $this->assertFalse($entry->identity_tagged); $this->assertNotNull($entry->sha256);
    }
    public function test_tree_includes_empty_directories(): void
    {
        mkdir($this->root.'/empty'); $this->fixtureFile('nested/a');
        $this->assertSame(4, $this->catalog->importTree('archive'));
        $this->assertTrue($this->catalog->at('archive','empty')->isDirectory());
    }
    public function test_upload_never_overwrites_a_file(): void
    {
        $this->fixtureFile('existing','original'); $this->fixtureFile('upload','incoming');
        try { $this->catalog->upload('archive','existing',$this->root.'/upload'); $this->fail('Expected conflict'); }
        catch (RuntimeException $e) { $this->assertSame('original', file_get_contents($this->root.'/existing')); }
    }
    public function test_paths_cannot_escape_or_use_reserved_names(): void
    {
        foreach (['../secret','/etc/passwd','a/../secret','a\\secret','a//b','.media-manager.lock',"a\0b"] as $path) {
            try { $this->catalog->filesystem->absolute('archive', $path); $this->fail('Accepted '.$path); }
            catch (\InvalidArgumentException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
    }
    public function test_symbolic_links_are_not_followed(): void
    {
        $this->fixtureFile('real/file'); symlink($this->root.'/real', $this->root.'/alias');
        $this->assertArrayNotHasKey('alias', $this->catalog->filesystem->scan('archive'));
        $this->expectException(RuntimeException::class);
        $this->catalog->import('archive','alias/file');
    }
    public function test_disks_must_be_explicitly_enabled(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->catalog->filesystem->root('private');
    }
    public function test_import_does_not_silently_accept_replaced_contents(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a'); $this->fixtureFile('a','replacement');
        $this->expectException(RuntimeException::class); $this->catalog->import('archive','a');
    }

    public function test_overlapping_storage_roots_are_rejected(): void
    {
        config(['media-manager.disks'=>['archive','alias'],'filesystems.disks.alias'=>['driver'=>'local','root'=>$this->root]]);
        $this->expectException(RuntimeException::class); $this->catalog->filesystem->root('archive');
    }

    public function test_hard_link_cannot_create_two_entries_without_xattrs(): void
    {
        $this->identity->supported = false; $this->fixtureFile('a'); $this->catalog->import('archive','a');
        link($this->root.'/a',$this->root.'/alias');
        $this->expectException(RuntimeException::class); $this->catalog->import('archive','alias');
    }
}
