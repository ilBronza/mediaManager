<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\FileCabinet\Helpers\MediaPathGenerators\{FilecabinetMediaPathGenerator, MediaPathGeneratorSingleFolder};
use IlBronza\MediaManager\Integrations\Spatie\Bridge;
use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Services\Mover;
use IlBronza\MediaManager\Tests\TestCase;
use IlBronza\MediaManager\Tests\Fixtures\CabinetRow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

class FileCabinetPlacementTest extends TestCase
{
    protected bool $spatie = true;
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('media-library.custom_path_generators', [CabinetRow::class=>FilecabinetMediaPathGenerator::class]);
        $app['config']->set('filecabinet.media.mediaPathGenerators.singleFolder', MediaPathGeneratorSingleFolder::class);
        // Exercise real upload/storage/collection behaviour without optional image binaries.
        $app['config']->set('media-library.image_generators', []);
    }
    protected function setUp(): void
    {
        if (!class_exists(FilecabinetMediaPathGenerator::class)) $this->markTestSkipped('Optional FileCabinet package is not installed.');
        parent::setUp();
        $this->mediaTable();
        Schema::create('cabinet_rows',function (Blueprint $table) { $table->id(); $table->string('upload_folder'); $table->timestamps(); });
    }
    private function upload(CabinetRow $row, string $content, string $disk = 'archive')
    {
        $this->fixtureFile('incoming.txt',$content);
        return $row->addMedia($this->root.'/incoming.txt')->usingFileName('filecabinet-name.txt')->toMediaCollection('document',$disk);
    }
    public function test_new_upload_after_manager_move_uses_filecabinet_rules_again(): void
    {
        $row = CabinetRow::create(['upload_folder'=>'clienti/rossi']);
        $first = $this->upload($row,'first');
        $this->assertSame('clienti/rossi/filecabinet-name.txt',$first->getPathRelativeToRoot());
        $entry = app(Bridge::class)->entry($first);
        $this->assertNotNull($entry);
        $this->catalog->createDirectory('archive','archivio');
        app(Mover::class)->move($entry,'archivio/spostato.txt');
        $this->assertSame('clienti/rossi',$row->fresh()->upload_folder);
        $this->assertSame('archivio/spostato.txt',$first->getPathRelativeToRoot());
        $this->assertSame('archivio/',PathGeneratorFactory::create($first)->getPath($first));
        $this->assertSame('archivio/spostato.txt',$first->fresh()->getCustomProperty('media_manager.location.path'));
        $second = $this->upload($row,'second');
        $this->assertSame('clienti/rossi/filecabinet-name.txt',$second->getPathRelativeToRoot());
        $this->assertSame('second',file_get_contents($this->root.'/clienti/rossi/filecabinet-name.txt'));
        $this->assertFileDoesNotExist($this->root.'/archivio/spostato.txt');
        $this->assertNull($first->fresh());
        $this->assertNull($entry->fresh());
        $this->assertNotNull(app(Bridge::class)->entry($second));
    }
    public function test_upload_rule_change_affects_only_the_next_new_file(): void
    {
        $row = CabinetRow::create(['upload_folder'=>'prima']);
        $first = $this->upload($row,'first');
        $row->update(['upload_folder'=>'dopo']);
        $this->assertSame('prima/filecabinet-name.txt',$first->getPathRelativeToRoot());
        $second = $this->upload($row,'second');
        $this->assertSame('dopo/filecabinet-name.txt',$second->getPathRelativeToRoot());
    }

    public function test_single_file_replacement_at_the_same_path_keeps_the_new_file(): void
    {
        $row = CabinetRow::create(['upload_folder'=>'dossier']);
        $first = $this->upload($row,'first');
        $entry = app(Bridge::class)->entry($first);
        $second = $this->upload($row,'replacement');
        $this->assertSame('replacement',file_get_contents($this->root.'/dossier/filecabinet-name.txt'));
        $this->assertNull($entry->fresh());
        $this->assertNull($first->fresh());
        $this->assertNotSame($entry->id,app(Bridge::class)->entry($second)->id);
    }
    public function test_filecabinet_can_clear_a_moved_collection_before_reupload(): void
    {
        $row = CabinetRow::create(['upload_folder'=>'dossier']);
        $first = $this->upload($row,'first');
        $entry = app(Bridge::class)->entry($first); app(Mover::class)->move($entry,'relocated.txt');
        $row->clearMediaCollection('document');
        $this->assertFileDoesNotExist($this->root.'/relocated.txt');
        $this->assertSame(0,$row->media()->count());
        $second = $this->upload($row,'replacement');
        $this->assertSame('dossier/filecabinet-name.txt',$second->getPathRelativeToRoot());
    }
    public function test_replacement_keeps_other_dossier_files_in_the_same_folder(): void
    {
        $row = CabinetRow::create(['upload_folder'=>'shared']);
        $first = $this->upload($row,'first');
        $this->fixtureFile('shared/other.txt','unrelated');
        $first->delete();
        $this->assertFileExists($this->root.'/shared/other.txt');
        $this->assertFileDoesNotExist($this->root.'/shared/filecabinet-name.txt');
    }
    public function test_disks_not_enabled_in_the_manager_keep_normal_filecabinet_uploads(): void
    {
        config(['media-manager.disks'=>['unrelated']]);
        $row = CabinetRow::create(['upload_folder'=>'dossier']);
        $media = $this->upload($row,'valid');
        $this->assertSame('dossier/filecabinet-name.txt',$media->getPathRelativeToRoot());
        $this->assertFileExists($this->root.'/dossier/filecabinet-name.txt');
        $this->assertSame(0,Entry::count());
    }

    public function test_tracking_failure_does_not_cancel_filecabinet_upload(): void
    {
        $bridge = \Mockery::mock(Bridge::class);
        $bridge->shouldReceive('adopt')->once()->andThrow(new \RuntimeException('Catalogue unavailable'));
        app()->instance(Bridge::class,$bridge);
        $bridge->shouldReceive('entry')->andReturn(null);
        $row = CabinetRow::create(['upload_folder'=>'dossier']);
        $media = $this->upload($row,'valid');
        $this->assertSame('dossier/filecabinet-name.txt',$media->getPathRelativeToRoot());
        $this->assertFileExists($this->root.'/dossier/filecabinet-name.txt');
        $this->assertSame(0,Entry::count());
    }

    public function test_filecabinet_selects_the_disk_for_each_new_upload(): void
    {
        $otherRoot = $this->root.'-second'; mkdir($otherRoot);
        try {
            config(['media-manager.disks'=>['archive','second'],'filesystems.disks.second'=>['driver'=>'local','root'=>$otherRoot,'throw'=>true]]);
            $row = CabinetRow::create(['upload_folder'=>'dossier']);
            $first = $this->upload($row,'first','archive');
            app(Mover::class)->move(app(Bridge::class)->entry($first),'moved.txt');
            $second = $this->upload($row,'second','second');
            $this->assertSame('second',$second->disk);
            $this->assertSame('second',app(Bridge::class)->entry($second)->disk);
            $this->assertSame('dossier/filecabinet-name.txt',$second->getPathRelativeToRoot());
            $this->assertFileExists($otherRoot.'/dossier/filecabinet-name.txt');
            $this->assertFileDoesNotExist($this->root.'/moved.txt');
            $this->assertSame('dossier',$row->fresh()->upload_folder);
        } finally { \Illuminate\Support\Facades\File::deleteDirectory($otherRoot); }
    }
}
