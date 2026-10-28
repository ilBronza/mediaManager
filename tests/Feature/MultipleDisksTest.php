<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\MediaManager\Tests\TestCase;
use Illuminate\Support\Facades\{File, Gate};

class MultipleDisksTest extends TestCase
{
    public function test_separate_disks_can_have_the_same_relative_path(): void
    {
        $images = $this->root.'-images'; mkdir($images);
        try {
            config(['media-manager.disks'=>['archive','images'],'filesystems.disks.images'=>['driver'=>'local','root'=>$images]]);
            $this->fixtureFile('same.txt','document'); file_put_contents($images.'/same.txt','picture');
            $document = $this->catalog->import('archive','same.txt');
            $image = $this->catalog->import('images','same.txt');
            $this->assertNotSame($document->id,$image->id);
            $this->assertNotSame($document->sha256,$image->sha256);
            $this->assertSame('archive',$document->parent->disk);
            $this->assertSame('images',$image->parent->disk);
            Gate::define('media-manager.browse',fn ($user=null,$disk=null)=>in_array($disk,['archive','images']));
            $this->get('/media-manager?disk=images')->assertOk()->assertSee('same.txt');
            $this->assertSame($document->id,$this->catalog->at('archive','same.txt')->id);
        } finally { File::deleteDirectory($images); }
    }
}
