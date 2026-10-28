<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\CRUD\Models\Media;
use IlBronza\FileCabinet\Helpers\MediaPathGenerators\{FilecabinetMediaPathGenerator, MediaPathGeneratorSingleFolder};
use IlBronza\MediaManager\Integrations\Spatie\Bridge;
use IlBronza\MediaManager\Services\Reconciler;
use IlBronza\MediaManager\Tests\{TestCase, Fixtures\CabinetRow};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrudSoftDeleteTest extends TestCase
{
    protected bool $spatie = true;
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('media-library.media_model',Media::class);
        $app['config']->set('media-library.custom_path_generators',[CabinetRow::class=>FilecabinetMediaPathGenerator::class]);
        $app['config']->set('filecabinet.media.mediaPathGenerators.singleFolder',MediaPathGeneratorSingleFolder::class);
        $app['config']->set('media-library.image_generators',[]);
    }
    protected function setUp(): void
    {
        if (!class_exists(Media::class) || !class_exists(FilecabinetMediaPathGenerator::class)) $this->markTestSkipped('Optional CRUD/FileCabinet packages are not installed.');
        parent::setUp(); $this->mediaTable();
        Schema::table('media',fn (Blueprint $table)=>$table->softDeletes());
        Schema::create('cabinet_rows',function (Blueprint $table) { $table->id(); $table->string('upload_folder'); $table->timestamps(); });
    }
    public function test_soft_delete_and_same_path_replacement_follow_crud_lifecycle(): void
    {
        $row = CabinetRow::create(['upload_folder'=>'dossier']);
        $this->fixtureFile('incoming.txt','first');
        $first = $row->addMedia($this->root.'/incoming.txt')->usingFileName('document.txt')->toMediaCollection('document','archive');
        $oldEntry = app(Bridge::class)->entry($first);
        $row->clearMediaCollection('document');
        $this->assertTrue($first->fresh()->trashed());
        $this->assertFileExists($this->root.'/dossier/document.txt');
        $this->fixtureFile('incoming.txt','second');
        $second = $row->addMedia($this->root.'/incoming.txt')->usingFileName('document.txt')->toMediaCollection('document','archive');
        $newEntry = app(Bridge::class)->entry($second);
        $this->assertNotNull($newEntry);
        $this->assertNotSame($oldEntry->id,$newEntry->id);
        $this->assertSame('superseded',$oldEntry->fresh()->status);
        $this->assertSame($newEntry->id,$this->catalog->at('archive','dossier/document.txt')->id);
        $report = app(Reconciler::class)->scan('archive',true);
        $this->assertFalse(collect($report['entries'])->contains('id',$oldEntry->id));
        $this->assertSame('present',$newEntry->fresh()->status);
        $first->forceDelete();
        $this->assertSame('second',file_get_contents($this->root.'/dossier/document.txt'));
        $this->assertNotNull($second->fresh());
        $this->assertNull($oldEntry->fresh());
    }
}
