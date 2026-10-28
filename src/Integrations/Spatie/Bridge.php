<?php

namespace IlBronza\MediaManager\Integrations\Spatie;

use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Services\Catalog;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

class Bridge
{
    public function __construct(private Catalog $catalog) {}

    public function adopt(Media $media, bool $justUploaded = false): Entry
    {
        if (!config('media-manager.spatie')) throw new RuntimeException('Enable the Spatie bridge before adopting media.');
        if ($media->getConnection()->getName() !== (new Entry)->getConnection()->getName()) throw new RuntimeException('Spatie and the catalogue must use the same database connection.');
        if ($existing = $this->entry($media)) return $existing;
        return $this->catalog->filesystem->locked($media->disk, function () use ($media, $justUploaded) {
            $this->catalog->assertIdle($media->disk);
            $generator = PathGeneratorFactory::create($media);
            $path = $media->getPathRelativeToRoot();
            return DB::transaction(function () use ($media, $generator, $path, $justUploaded) {
                $previous = $this->catalog->at($media->disk, $path);
                $replaced = $justUploaded && $previous && $previous->spatie_media_id && $previous->spatie_media_id !== (string) $media->getKey();
                if ($replaced) {
                    // This is evidence of a new File Cabinet upload, not a hash-based guess.
                    // The old media (possibly soft deleted) must no longer own this path.
                    $previous->update(['status' => 'superseded']);
                }
                $entry = $this->catalog->register($media->disk, $path, [], $replaced);
                if ($entry->spatie_media_id && $entry->spatie_media_id !== (string) $media->getKey()) throw new RuntimeException('Physical file is already linked to another media.');
                $entry->update(['spatie_media_id' => (string) $media->getKey(), 'spatie_paths' => [
                    'conversions' => $generator->getPathForConversions($media),
                    'responsive' => $generator->getPathForResponsiveImages($media),
                    'disk' => $media->conversions_disk ?: $media->disk,
                ]]);
                if ($media->model) $this->catalog->link($entry, $media->model, $media->collection_name ?: 'attachment');
                return $entry;
            });
        });
    }

    public function entry(Media $media): ?Entry
    {
        return Entry::where('spatie_media_id', (string) $media->getKey())->first();
    }
}
