<?php

namespace IlBronza\MediaManager\Integrations\Spatie;

use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class ManagedPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $entry = app(Bridge::class)->entry($media);
        if ($entry) {
            $directory = dirname($entry->path);
            return $directory === '.' ? '' : $directory.'/';
        }
        // A new media has no entry: File Cabinet's generator always chooses its destination.
        return $this->legacy($media)->getPath($media);
    }

    public function getPathForConversions(Media $media): string
    {
        $entry = app(Bridge::class)->entry($media);
        return $entry ? $entry->spatie_paths['conversions'] : $this->legacy($media)->getPathForConversions($media);
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        $entry = app(Bridge::class)->entry($media);
        return $entry ? $entry->spatie_paths['responsive'] : $this->legacy($media)->getPathForResponsiveImages($media);
    }

    private function legacy(Media $media): PathGenerator
    {
        foreach (config('media-manager.legacy_custom_path_generators', []) as $model => $generator) {
            if ($media->model_type === $model || is_a($media->model_type, $model, true) || is_a((string) Relation::getMorphedModel($media->model_type), $model, true)) return app($generator);
        }
        return app(config('media-manager.legacy_path_generator'));
    }
}
