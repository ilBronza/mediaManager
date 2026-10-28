<?php

namespace IlBronza\MediaManager\Integrations\Spatie;

use IlBronza\MediaManager\Models\Entry;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Actual location of this particular media, never an upload rule for its owner. */
class MediaLocation
{
    public const PROPERTY = 'media_manager.location';

    public function payload(Entry $entry): array
    {
        $directory = dirname($entry->path);
        return [
            'entry_id' => $entry->id,
            'disk' => $entry->disk,
            'directory' => $directory === '.' ? '' : $directory,
            'file_name' => basename($entry->path),
            'path' => $entry->path,
            'status' => $entry->status,
            'derivatives' => $entry->spatie_paths,
        ];
    }

    /** Called inside the catalogue transaction; bypass Spatie's filesystem observers. */
    public function sync(Entry $entry): void
    {
        if (!$entry->spatie_media_id || !config('media-manager.spatie')) return;
        $class = config('media-library.media_model');
        $media = $class::withoutGlobalScopes()->whereKey($entry->spatie_media_id)->lockForUpdate()->first();
        if (!$media) return;
        $media->setCustomProperty(self::PROPERTY, $this->payload($entry));
        $media->saveQuietly();
    }

    public function mergeInto(Media $media, Entry $entry): void
    {
        $media->setCustomProperty(self::PROPERTY, $this->payload($entry));
    }
}
