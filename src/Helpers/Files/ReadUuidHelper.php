<?php

namespace IlBronza\MediaManager\Helpers\Files;

use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Services\Catalog;

class ReadUuidHelper
{
    public function __construct(private ManagedFileHelper $files)
    {
    }

    public function run(Entry $entry): array
    {
        return $this->files->withFile($entry, function (Entry $entry, string $absolutePath, Catalog $catalog)
        {
            $uuid = $catalog->identities->read($absolutePath);

            return [
                'operation' => 'read-uuid',
                'entry_id' => $entry->id,
                'path' => $entry->path,
                'uuid' => $uuid,
                'matches' => $uuid === $entry->id,
            ];
        });
    }
}
