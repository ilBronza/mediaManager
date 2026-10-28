<?php

namespace IlBronza\MediaManager\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\{HasMedia, InteractsWithMedia};

class CabinetRow extends Model implements HasMedia
{
    use InteractsWithMedia;
    protected $table = 'cabinet_rows';
    protected $guarded = [];
    public function getFormrow(): CabinetFormrow { return new CabinetFormrow($this); }
    public function registerMediaCollections(): void { $this->addMediaCollection('document')->singleFile(); }
}

class CabinetFormrow
{
    public function __construct(private CabinetRow $row) {}
    public function getModel(): self { return $this; }
    public function getSpecialParameter(string $name, mixed $default = null): mixed
    {
        return ['folderType'=>'singleFolder','folderName'=>$this->row->upload_folder,'folderPrefix'=>'','folderSuffix'=>''][$name] ?? $default;
    }
}
