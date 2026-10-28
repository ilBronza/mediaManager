<?php

namespace IlBronza\MediaManager\Helpers\Browser;

use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Services\Catalog;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CatalogListingHelper
{
    public function __construct(private Catalog $catalog)
    {
    }

    /** Read one catalog location without accessing its physical storage. */
    public function read(string $disk, string $path): ?array
    {
        $entry = $this->catalog->at($disk, $path);

        if (!$entry)
        {
            return null;
        }

        $entry->loadCount('links');

        return $this->describe($entry);
    }

    /** Database-only listing. Callers authorize the returned entries before exposing them. */
    public function list(string $disk, string $path = ''): Collection
    {
        $parent = $this->catalog->at($disk, $path);

        if (!$parent)
        {
            return collect();
        }

        if (!$parent->isDirectory())
        {
            throw new InvalidArgumentException('Expected a catalog directory.');
        }

        $entries = Entry::query()
            ->where('disk', $disk)
            ->where('parent_id', $parent->id)
            ->where('status', '!=', 'superseded')
            ->withCount('links')
            ->orderBy('kind')
            ->orderBy('path')
            ->get();

        return $entries->map(fn (Entry $entry) => $this->describe($entry));
    }

    private function describe(Entry $entry): array
    {
        return [
            'path' => $entry->path,
            'name' => $entry->name,
            'kind' => $entry->kind,
            'size' => $entry->size,
            'entry' => $entry,
        ];
    }
}
