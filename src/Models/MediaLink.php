<?php

namespace IlBronza\MediaManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

class MediaLink extends Model
{
    protected $table = 'media_manager_links';
    protected $guarded = [];
    public function entry(): BelongsTo { return $this->belongsTo(Entry::class); }
    public function linkable(): MorphTo { return $this->morphTo(); }
}
