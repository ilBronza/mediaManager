<?php

namespace IlBronza\MediaManager\Helpers\Files;

use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Services\Catalog;
use RuntimeException;

class WriteUuidHelper
{
    public function __construct(private ManagedFileHelper $files)
    {
    }

    /** Persist the existing Entry UUID only after checking the file and its current identity. */
    public function run(Entry $entry): array
    {
        return $this->files->withFile($entry, function (Entry $entry, string $absolutePath, Catalog $catalog)
        {
            $information = $catalog->filesystem->inspect($entry->disk, $entry->path);

            if ($entry->status !== 'present' || $information['sha256'] !== $entry->sha256)
            {
                throw new RuntimeException('Il file è cambiato: riconciliarlo prima di scrivere l’UUID.');
            }

            $currentUuid = $catalog->identities->read($absolutePath);

            if ($currentUuid !== null && $currentUuid !== $entry->id)
            {
                throw new RuntimeException('Il file contiene già un UUID diverso. Nessun metadato modificato.');
            }

            $written = $catalog->identities->write($absolutePath, $entry->id);

            if (!$written || $catalog->identities->read($absolutePath) !== $entry->id)
            {
                throw new RuntimeException('UUID non scritto: verificare il supporto degli attributi estesi e i permessi.');
            }

            $entry->update(['identity_tagged' => true]);

            return [
                'operation' => 'write-uuid',
                'entry_id' => $entry->id,
                'path' => $entry->path,
                'uuid' => $entry->id,
                'matches' => true,
            ];
        });
    }
}
