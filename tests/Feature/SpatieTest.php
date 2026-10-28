<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\MediaManager\Integrations\Spatie\Bridge;
use IlBronza\MediaManager\Services\{Mover, Reconciler};
use IlBronza\MediaManager\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

class Owner extends Model implements \Spatie\MediaLibrary\HasMedia
{
    use \Spatie\MediaLibrary\InteractsWithMedia;
    protected $table = 'media_manager_entries'; protected $keyType = 'string'; public $incrementing = false;
}

class SpatieTest extends TestCase
{
    protected bool $spatie = true;
    private function media(): Media
    {
        $this->mediaTable();
        $root = $this->catalog->import('archive','');
        $media = Media::create(['model_type'=>Owner::class,'model_id'=>$root->id,'collection_name'=>'documents','name'=>'contract','file_name'=>'contract.pdf', 'disk'=>'archive','conversions_disk'=>'archive','size'=>8,'mime_type'=>'application/pdf','manipulations'=>[],'custom_properties'=>['key'=>'value'],'generated_conversions'=>[],'responsive_images'=>[]]);
        $this->fixtureFile($media->id.'/contract.pdf');
        return $media;
    }
    public function test_adoption_preserves_media_id_owner_and_custom_properties(): void
    {
        $media = $this->media(); $entry = app(Bridge::class)->adopt($media);
        $this->assertSame((string)$media->id,$entry->spatie_media_id);
        $this->assertSame((string)$media->model_id,$entry->links()->first()->linkable_id);
        $this->assertSame('value',$media->fresh()->getCustomProperty('key'));
        $this->assertSame($entry->path,$media->fresh()->getCustomProperty('media_manager.location.path'));
        $this->assertSame($entry->id,app(Bridge::class)->adopt($media)->id);
    }
    public function test_existing_media_instance_resolves_new_physical_path_after_move(): void
    {
        $media = $this->media(); $entry = app(Bridge::class)->adopt($media);
        app(Mover::class)->move($entry,'renamed.pdf');
        $this->assertSame('renamed.pdf',$media->getPathRelativeToRoot());
        $this->assertSame($this->root.'/renamed.pdf',$media->getPath());
        $this->assertSame('/archive/renamed.pdf',$media->getUrl());
        $this->assertSame('contract.pdf',$media->fresh()->file_name);
        $this->assertSame('renamed.pdf',$media->fresh()->getCustomProperty('media_manager.location.path'));
        $this->assertSame('',PathGeneratorFactory::create($media)->getPath($media));
    }
    public function test_derivative_directories_follow_managed_folder_move(): void
    {
        $media = $this->media(); $entry = app(Bridge::class)->adopt($media);
        $this->fixtureFile($media->id.'/conversions/contract-thumb.jpg','thumb');
        app(Mover::class)->move($entry->parent,'renamed-folder');
        $this->assertSame('renamed-folder/conversions/',PathGeneratorFactory::create($media)->getPathForConversions($media));
        $this->assertFileExists($this->root.'/renamed-folder/conversions/contract-thumb.jpg');
    }
    public function test_unmanaged_media_keeps_original_spatie_path(): void
    {
        $media = $this->media(); $this->assertSame($media->id.'/contract.pdf',$media->getPathRelativeToRoot());
    }
    public function test_spatie_delete_removes_only_the_managed_file_and_its_catalogue_entry(): void
    {
        $media = $this->media(); $entry = app(Bridge::class)->adopt($media);
        $this->fixtureFile($media->id.'/other.pdf','keep');
        app(Mover::class)->move($entry,'moved.pdf');
        $media->delete();
        $this->assertFileDoesNotExist($this->root.'/moved.pdf');
        $this->assertFileExists($this->root.'/'.$media->id.'/other.pdf');
        $this->assertNull($entry->fresh());
        $this->assertNull($media->fresh());
    }
    public function test_spatie_filename_change_uses_the_current_location(): void
    {
        $media = $this->media(); $entry = app(Bridge::class)->adopt($media);
        app(Mover::class)->move($entry,'moved.pdf');
        $media->file_name = 'updated.pdf'; $media->save();
        $this->assertFileExists($this->root.'/updated.pdf');
        $this->assertFileDoesNotExist($this->root.'/moved.pdf');
        $this->assertSame('updated.pdf',$entry->fresh()->path);
        $this->assertSame('updated.pdf',$media->fresh()->getCustomProperty('media_manager.location.path'));
    }

    public function test_conversion_source_stream_uses_relocated_original(): void
    {
        $media = $this->media(); $entry = app(Bridge::class)->adopt($media);
        app(Mover::class)->move($entry,'renamed.pdf');
        $stream = app(\Spatie\MediaLibrary\MediaCollections\Filesystem::class)->getStream($media);
        $this->assertSame('document',stream_get_contents($stream)); fclose($stream);
    }

    public function test_derivatives_follow_external_folder_move(): void
    {
        $media = $this->media(); $entry = app(Bridge::class)->adopt($media);
        $this->fixtureFile($media->id.'/conversions/contract-thumb.jpg','thumb');
        rename($this->root.'/'.$media->id,$this->root.'/external');
        app(Reconciler::class)->scan('archive',true);
        $this->assertSame('external/conversions/',PathGeneratorFactory::create($media)->getPathForConversions($media));
        $this->assertSame('external/contract.pdf',$media->getPathRelativeToRoot());
        $this->assertSame('external/contract.pdf',$media->fresh()->getCustomProperty('media_manager.location.path'));
    }

    public function test_moving_derivative_folder_also_updates_an_original_stored_elsewhere(): void
    {
        $media = $this->media(); $entry = app(Bridge::class)->adopt($media); $folder = $entry->parent;
        $this->fixtureFile($media->id.'/conversions/contract-thumb.jpg','thumb');
        app(Mover::class)->move($entry,'elsewhere.pdf'); app(Mover::class)->move($folder,'derivatives');
        $this->assertSame('derivatives/conversions/',PathGeneratorFactory::create($media)->getPathForConversions($media));
        $this->assertSame('elsewhere.pdf',$media->getPathRelativeToRoot());
    }

    public function test_catalogue_and_media_location_roll_back_together(): void
    {
        $media = $this->media(); $entry = app(Bridge::class)->adopt($media);
        \Illuminate\Support\Facades\DB::beginTransaction();
        $entry->update(['path'=>'temporary.pdf']);
        $this->assertSame('temporary.pdf',$media->fresh()->getCustomProperty('media_manager.location.path'));
        \Illuminate\Support\Facades\DB::rollBack();
        $this->assertSame($media->id.'/contract.pdf',$entry->fresh()->path);
        $this->assertSame($media->id.'/contract.pdf',$media->fresh()->getCustomProperty('media_manager.location.path'));
    }
}
