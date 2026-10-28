<?php

namespace IlBronza\MediaManager\Helpers\Files;

use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Services\Catalog;

class CalculateSha256Helper
{
    public function __construct(private ManagedFileHelper $files)
    {
    }

    /** Observe the current hash without replacing the stored baseline. */
    public function run(Entry $entry): array
    {
        return $this->files->withFile($entry, function (Entry $entry, string $absolutePath, Catalog $catalog)
        {
            $information = $catalog->filesystem->inspect($entry->disk, $entry->path);

            return [
                'operation' => 'sha256',
                'entry_id' => $entry->id,
                'path' => $entry->path,
                'sha256' => $information['sha256'],
                'stored_sha256' => $entry->sha256,
                'matches' => $information['sha256'] === $entry->sha256,
            ];
        });
    }
}
