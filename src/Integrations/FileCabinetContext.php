<?php

namespace IlBronza\MediaManager\Integrations;

use IlBronza\MediaManager\Models\Entry;
use Illuminate\Support\Facades\Gate;

class FileCabinetContext
{
    /** No mandatory FileCabinet dependency. Dossier URLs are returned only after the host gate allows access. */
    public function forEntry(Entry $entry): array
    {
        $result = [];
        foreach ($entry->links()->with('linkable')->get() as $link) {
            $row = $link->linkable;
            if (!$row || !is_a($row, 'IlBronza\\FileCabinet\\Models\\Dossierrow')) continue;
            $dossier = $row->getDossier();
            if (!$dossier || !Gate::allows('media-manager.view-dossier', [$dossier, $entry])) continue;
            $result[] = ['row_id' => $row->getKey(), 'dossier_id' => $dossier->getKey(), 'label' => $row->getName(), 'url' => $dossier->getShowUrl()];
        }
        return $result;
    }
}
