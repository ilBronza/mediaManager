<?php

namespace IlBronza\MediaManager\Services;

use IlBronza\MediaManager\Contracts\IdentityStore;
use IlBronza\MediaManager\Filesystem\LocalFilesystem;
use IlBronza\MediaManager\Models\{Entry, MediaLink, Operation};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class Catalog
{
    public function __construct(public LocalFilesystem $filesystem, public IdentityStore $identities) {}

    public function assertIdle(string $disk): void
    {
        $this->assertRoot($disk);
        if (Operation::where('disk', $disk)->where('state', '!=', 'completed')->exists()) {
            throw new RuntimeException('Resume outstanding media-manager operations before changing this disk.');
        }
    }

    public function assertRoot(string $disk): void
    {
        $observed = $this->filesystem->inspect($disk, '');
        $root = $this->at($disk, '');
        if (!$root) return;
        $uuid = $this->identities->read($this->filesystem->root($disk));
        if ($uuid === $root->id) return;
        if ($uuid || ($root->root_identity && $root->root_identity !== ['device'=>$observed['device'], 'inode'=>$observed['inode']])) {
            throw new RuntimeException('Storage root identity changed; verify the mount before reconciling.');
        }
    }

    public function at(string $disk, string $path): ?Entry
    {
        $path = $this->filesystem->normalize($path);
        return Entry::where('disk', $disk)->where('path_hash', hash('sha256', $path))->first();
    }

    public function import(string $disk, string $path, array $metadata = []): Entry
    {
        return $this->filesystem->locked($disk, function () use ($disk, $path, $metadata) {
            $this->assertIdle($disk);
            return DB::transaction(fn () => $this->register($disk, $path, $metadata));
        });
    }

    /** Internal: caller holds the disk lock. Import does not rewrite existing identities or hashes. */
    public function register(string $disk, string $path, array $metadata = [], bool $newIdentity = false): Entry
    {
        $info = $this->filesystem->inspect($disk, $path);
        if ($existing = $this->at($disk, $path)) {
            $tag = $this->identities->read($this->filesystem->absolute($disk, $path));
            if ($tag && $tag !== $existing->id) throw new RuntimeException('Identity at the indexed path has changed; reconcile first.');
            if ($existing->kind !== $info['kind'] || $existing->sha256 !== $info['sha256']) throw new RuntimeException('Existing entry has changed; reconcile first.');
            return $existing;
        }
        $absolute = $this->filesystem->absolute($disk, $path);
        $tag = $newIdentity ? null : $this->identities->read($absolute);
        if ($tag && Entry::find($tag)) throw new RuntimeException('Known UUID at a different path; reconcile before importing.');
        if ($info['kind'] === 'file') {
            foreach (Entry::where('disk', $disk)->where('status', '!=', 'superseded')->where('sha256', $info['sha256'])->get() as $candidate) {
                if (!file_exists($this->filesystem->absolute($disk, $candidate->path))) throw new RuntimeException('Content matches a missing entry; use reconciliation before importing.');
                $candidateInfo = $this->filesystem->inspect($disk, $candidate->path);
                if ($candidateInfo['device'] === $info['device'] && $candidateInfo['inode'] === $info['inode']) throw new RuntimeException('This file is already indexed through another path or hard link.');
            }
        }
        $parent = $path === '' ? null : $this->register($disk, $this->parentPath($path));
        $entry = Entry::create([
            'id' => $tag ?? (string) Str::uuid(), 'disk' => $disk, 'path' => $path,
            'parent_id' => $parent?->id, 'kind' => $info['kind'], 'sha256' => $info['sha256'],
            'size' => $info['size'], 'metadata' => $metadata, 'status' => 'present', 'last_seen_at' => now(),
            'root_identity' => $path === '' ? ['device'=>$info['device'], 'inode'=>$info['inode']] : null,
        ]);
        $entry->update(['identity_tagged' => $this->identities->write($absolute, $entry->id)]);
        return $entry;
    }

    public function importTree(string $disk): int
    {
        return $this->filesystem->locked($disk, function () use ($disk) {
            $this->assertIdle($disk);
            $snapshot = $this->filesystem->scan($disk);
            foreach (Entry::where('disk', $disk)->where('status', '!=', 'superseded')->get() as $known) {
                if (!isset($snapshot[$known->path]) || $known->kind !== $snapshot[$known->path]['kind'] || $known->sha256 !== $snapshot[$known->path]['sha256']) {
                    throw new RuntimeException('Catalogue diverged from disk; reconcile before importing new files.');
                }
            }
            $tags = [];
            foreach ($snapshot as $path => $info) {
                $tag = $this->identities->read($this->filesystem->absolute($disk, $path));
                if ($tag && isset($tags[$tag])) throw new RuntimeException('Duplicate UUIDs found; reconcile before importing.');
                if ($tag) $tags[$tag] = true;
            }
            return DB::transaction(function () use ($snapshot, $disk) {
                foreach ($snapshot as $path => $info) $this->register($disk, $path);
                return count($snapshot);
            });
        });
    }

    public function createDirectory(string $disk, string $path): Entry
    {
        return $this->filesystem->locked($disk, function () use ($disk, $path) {
            $this->assertIdle($disk);
            $absolute = $this->filesystem->absolute($disk, $path);
            if ($path === '' || file_exists($absolute) || $this->at($disk, $path)) throw new RuntimeException('Destination already exists.');
            $parent = $this->filesystem->absolute($disk, $this->parentPath($path));
            if (!is_dir($parent) || !@mkdir($absolute, 0770)) throw new RuntimeException('Cannot create directory; parent must exist.');
            // A DB failure leaves a discoverable physical directory; never remove user data on failure.
            return DB::transaction(fn () => $this->register($disk, $path));
        });
    }

    public function upload(string $disk, string $path, string $source): Entry
    {
        return $this->filesystem->locked($disk, function () use ($disk, $path, $source) {
            $this->assertIdle($disk);
            if ($this->at($disk, $path)) throw new RuntimeException('Destination already indexed.');
            $absolute = $this->filesystem->absolute($disk, $path);
            $input = @fopen($source, 'rb');
            if (!$input) throw new RuntimeException('Cannot read uploaded file.');
            $output = @fopen($absolute, 'xb');
            if (!$output) { fclose($input); throw new RuntimeException('Cannot create file or destination already exists.'); }
            try {
                if (stream_copy_to_stream($input, $output) === false || !fflush($output)) throw new RuntimeException('Upload copy failed.');
            } catch (\Throwable $e) {
                fclose($output); fclose($input); @unlink($absolute); throw $e;
            }
            fclose($output); fclose($input);
            return DB::transaction(fn () => $this->register($disk, $path));
        });
    }

    public function link(Entry $entry, Model $model, string $role = 'attachment'): MediaLink
    {
        if (!$model->exists || !$model->getKey() || strlen($role) > 80 || $role === '') throw new RuntimeException('A persisted model and valid role are required.');
        return MediaLink::firstOrCreate(['entry_id' => $entry->id, 'linkable_type' => $model->getMorphClass(), 'linkable_id' => (string) $model->getKey(), 'role' => $role]);
    }

    public function parentPath(string $path): string
    {
        $parent = dirname($path);
        return $parent === '.' ? '' : $parent;
    }

    public function relocateDerivativeDirectories(string $disk, string $source, string $destination): void
    {
        // Derivatives can remain in a folder after their original has moved elsewhere.
        foreach (Entry::whereNotNull('spatie_media_id')->get() as $entry) {
            $paths = $entry->spatie_paths;
            if (($paths['disk'] ?? null) !== $disk) continue;
            $changed = false;
            foreach (['conversions', 'responsive'] as $key) {
                if (str_starts_with($paths[$key] ?? '', $source.'/')) {
                    $paths[$key] = $destination.substr($paths[$key], strlen($source));
                    $changed = true;
                }
            }
            if ($changed) $entry->update(['spatie_paths' => $paths]);
        }
    }
}
