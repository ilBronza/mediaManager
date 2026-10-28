<?php

namespace IlBronza\MediaManager\Helpers\Files;

use Closure;
use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Services\Catalog;
use RuntimeException;

class ManagedFileHelper
{
    public function __construct(private Catalog $catalog)
    {
    }

    /** Coordinates one managed file. HTTP callers authorize it; CLI callers are trusted administrators. */
    public function withFile(Entry $entry, Closure $operation): array
    {
        if (!$entry->exists)
        {
            throw new RuntimeException('Il file deve essere già registrato nel catalogo.');
        }

        $disk = $entry->disk;

        return $this->catalog->filesystem->locked($disk, function () use ($entry, $disk, $operation)
        {
            $entry->refresh();

            if ($entry->disk !== $disk || $entry->status === 'superseded' || $entry->isDirectory())
            {
                throw new RuntimeException('Selezionare un file attivo del catalogo.');
            }

            $this->catalog->assertIdle($disk);

            $absolutePath = $this->catalog->filesystem->absolute($disk, $entry->path);

            if (!is_file($absolutePath))
            {
                throw new RuntimeException('Il file non è presente nel percorso registrato.');
            }

            return $operation($entry, $absolutePath, $this->catalog);
        });
    }
}
