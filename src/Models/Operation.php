<?php

namespace IlBronza\MediaManager\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    use HasUuids;
    protected $table = 'media_manager_operations';
    protected $guarded = [];
    protected $attributes = ['state' => 'prepared'];
    protected $casts = ['snapshot' => 'array'];
}
