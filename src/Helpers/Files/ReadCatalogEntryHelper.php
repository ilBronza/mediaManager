<?php

namespace IlBronza\MediaManager\Helpers\Files;

use IlBronza\MediaManager\Models\Entry;
use RuntimeException;

class ReadCatalogEntryHelper
{
    /** Database-only details for one managed entry; also usable when storage is offline. */
    public function run(Entry $entry): array
    {
        if (!$entry->exists)
        {
            throw new RuntimeException('La voce deve essere già registrata nel catalogo.');
        }

        $entry->refresh()->load('links');

        return [
            'id' => $entry->id,
            'name' => $entry->name,
            'disk' => $entry->disk,
            'path' => $entry->path,
            'status' => $entry->status,
            'size' => $entry->size,
            'sha256' => $entry->sha256,
            'metadata' => $entry->metadata,
            'media_id' => $entry->spatie_media_id,
            'links' => $entry->links->map(fn ($link) => [
                'type' => class_basename($link->linkable_type),
                'id' => $link->linkable_id,
                'role' => $link->role,
            ])->all(),
        ];
    }
}
