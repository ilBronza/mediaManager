<?php

namespace IlBronza\MediaManager\Integrations\Spatie;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ManagedMediaObserver
{
    public function saving(Media $media): void
    {
        // Preserve the reserved location when the host changes other custom properties.
        // Never veto File Cabinet's normal replacement/deletion lifecycle.
        if ($media->exists && ($entry = app(Bridge::class)->entry($media))) app(MediaLocation::class)->mergeInto($media, $entry);
    }
}
