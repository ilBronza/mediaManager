<?php

namespace IlBronza\MediaManager\Integrations\Spatie;

use Spatie\MediaLibrary\MediaCollections\Events\{MediaHasBeenAdded, MediaHasBeenAddedEvent};

class TrackUploadedMedia
{
    public function handle(MediaHasBeenAdded|MediaHasBeenAddedEvent $event): void
    {
        if (!config('media-manager.auto_adopt', true) || !in_array($event->media->disk, config('media-manager.disks', []), true)) return;
        // This event runs after File Cabinet/Spatie has chosen disk, path and filename
        // and written the original. Catalogue availability must not cancel an upload.
        try { app(Bridge::class)->adopt($event->media, justUploaded: true); }
        catch (\Throwable $e) { report($e); }
    }
}
