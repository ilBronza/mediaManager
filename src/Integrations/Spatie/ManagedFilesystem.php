<?php

namespace IlBronza\MediaManager\Integrations\Spatie;

use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Services\{Catalog, Mover};
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ManagedFilesystem extends Filesystem
{
    public function getStream(Media $media)
    {
        $entry = app(Bridge::class)->entry($media);
        if (!$entry) return parent::getStream($media);
        $catalog = app(Catalog::class);
        $catalog->assertRoot($entry->disk);
        $info = $catalog->filesystem->inspect($entry->disk, $entry->path);
        if ($entry->status !== 'present' || $info['sha256'] !== $entry->sha256) throw new \RuntimeException('Reconcile media before reading it.');
        $stream = fopen($catalog->filesystem->absolute($entry->disk, $entry->path), 'rb');
        if (!$stream) throw new \RuntimeException('Cannot read managed media.');
        return $stream;
    }

    public function removeAllFiles(Media $media): void
    {
        $entry = app(Bridge::class)->entry($media);
        if (!$entry) { parent::removeAllFiles($media); return; }
        $catalog = app(Catalog::class);
        $catalog->filesystem->locked($entry->disk, function () use ($media, $entry, $catalog) {
            $catalog->assertIdle($entry->disk);
            $entry->refresh();
            if ($entry->status === 'superseded') {
                // A later upload already owns these bytes. Deleting the old media must
                // never delete the replacement, including after a delayed forceDelete.
                $entry->delete();
                return;
            }
            // File Cabinet still owns replacement/deletion. Remove only this media's
            // exact files, never the directory selected for other dossier rows.
            $files = [[$entry->disk, $entry->path]];
            foreach ($media->getGeneratedConversions() as $name => $generated) {
                if ($generated) $files[] = [$entry->spatie_paths['disk'], $media->getPathRelativeToRoot($name)];
            }
            foreach ($media->responsive_images ?? [] as $responsive) {
                foreach ($responsive['urls'] ?? [] as $filename) {
                    if (basename($filename) !== $filename) throw new \RuntimeException('Invalid responsive image filename.');
                    $files[] = [$entry->spatie_paths['disk'], $entry->spatie_paths['responsive'].$filename];
                }
            }
            foreach ($files as [$disk, $path]) {
                if (in_array($disk, config('media-manager.disks', []), true)) $catalog->filesystem->absolute($disk, $path);
                $storage = $this->filesystem->disk($disk);
                if ($storage->exists($path) && !$storage->delete($path)) throw new \RuntimeException('Cannot remove media file.');
                Entry::where('disk', $disk)->where('path_hash', hash('sha256', $path))->get()->each->delete();
            }
        });
    }

    public function syncMediaPath(Media $media): void
    {
        // Upload policies may evolve without relocating an existing managed file.
        if (!app(Bridge::class)->entry($media)) parent::syncMediaPath($media);
    }

    public function syncFileNames(Media $media): void
    {
        $entry = app(Bridge::class)->entry($media);
        if (!$entry) { parent::syncFileNames($media); return; }
        if (basename($media->file_name) !== $media->file_name) throw new \RuntimeException('Invalid filename.');
        $directory = dirname($entry->path);
        $destination = ($directory === '.' ? '' : $directory.'/').$media->file_name;
        if ($destination !== $entry->path) app(Mover::class)->move($entry, $destination);
        $this->renameConversionFiles($media);
        app(MediaLocation::class)->mergeInto($media, $entry->fresh());
    }
}
