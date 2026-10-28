<?php

namespace IlBronza\MediaManager\Integrations\Spatie;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

class ManagedUrlGenerator extends DefaultUrlGenerator
{
    public function getPathRelativeToRoot(): string
    {
        $entry = app(Bridge::class)->entry($this->media);
        if ($entry && $entry->status !== 'present') throw new \RuntimeException('Reconcile this media before serving it.');
        if ($entry && $this->conversion === null) return $entry->path;
        if ($entry && $this->conversion !== null) return $entry->spatie_paths['conversions'].$this->conversion->getConversionFile($this->media);
        return parent::getPathRelativeToRoot();
    }

    public function getResponsiveImagesDirectoryUrl(): string
    {
        $entry = app(Bridge::class)->entry($this->media);
        if (!$entry) return parent::getResponsiveImagesDirectoryUrl();
        return rtrim(\Illuminate\Support\Facades\Storage::disk($entry->spatie_paths['disk'])->url($entry->spatie_paths['responsive']), '/').'/';
    }
}
