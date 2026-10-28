<?php

namespace IlBronza\MediaManager\Helpers\Browser;

use IlBronza\MediaManager\Models\Entry;
use Illuminate\Support\Collection;

class CatalogOverlayHelper
{
    public function attachFile(string $disk, array $file): array
    {
        $entry = Entry::query()
            ->where('disk', $disk)
            ->where('path_hash', hash('sha256', $file['path']))
            ->withCount('links')
            ->first();

        $file['entry'] = $entry;

        return $file;
    }

    /** Annotate exact disk/path matches. This is not identity recovery or content verification. */
    public function attach(string $disk, Collection $physical): Collection
    {
        $known = collect();

        // Keep each query below database parameter limits, even for large directories.
        foreach ($physical->pluck('path')->chunk(500) as $paths)
        {
            $pathHashes = $paths->map(fn (string $path) => hash('sha256', $path));

            $entries = Entry::query()
                ->where('disk', $disk)
                ->whereIn('path_hash', $pathHashes->all())
                ->withCount('links')
                ->get()
                ->keyBy('path_hash');

            $known = $known->merge($entries);
        }

        return $physical->map(function (array $item) use ($known)
        {
            $item['entry'] = $known->get(hash('sha256', $item['path']));

            return $item;
        });
    }
}
