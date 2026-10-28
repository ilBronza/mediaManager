<?php

namespace IlBronza\MediaManager\Traits;

use IlBronza\MediaManager\Models\MediaLink;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasManagedMedia
{
    public function managedMediaLinks(): MorphMany
    {
        return $this->morphMany(MediaLink::class, 'linkable');
    }
}
