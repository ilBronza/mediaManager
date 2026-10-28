<?php

namespace IlBronza\MediaManager\Services;

use IlBronza\MediaManager\Events\EntryRelocated;
use IlBronza\MediaManager\Models\{Entry, Operation};
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Mover
{
    public function __construct(private Catalog $catalog) {}

    /** Same local disk only. Journal persists BEFORE any filesystem mutation. */
    public function move(Entry $entry, string $destination): Operation
    {
        return $this->catalog->filesystem->locked($entry->disk, function () use ($entry, $destination) {
            $entry->refresh();
            $this->catalog->assertIdle($entry->disk);
            $destination = $this->catalog->filesystem->normalize($destination);
            if ($entry->path === '' || $destination === '' || $destination === $entry->path || str_starts_with($destination, $entry->path.'/')) throw new RuntimeException('Invalid move destination.');
            if ($entry->status !== 'present') throw new RuntimeException('Reconcile this entry before moving it.');
            $target = $this->catalog->filesystem->absolute($entry->disk, $destination);
            if (file_exists($target) || $this->catalog->at($entry->disk, $destination)) throw new RuntimeException('Destination already exists.');
            $parent = $this->catalog->at($entry->disk, $this->catalog->parentPath($destination));
            if (!$parent || !$parent->isDirectory() || $parent->status !== 'present' || !is_dir(dirname($target))) throw new RuntimeException('Destination parent must be an indexed directory.');
            $info = $this->catalog->filesystem->inspect($entry->disk, $entry->path);
            if ($info['sha256'] !== $entry->sha256) throw new RuntimeException('Source changed; reconcile first.');
            $rows = Entry::where('disk', $entry->disk)->where('status', '!=', 'superseded')->get()->filter(fn ($row) => $row->id === $entry->id || ($entry->isDirectory() && str_starts_with($row->path, $entry->path.'/')));
            $snapshots = [];
            foreach ($rows as $row) {
                $observed = $this->catalog->filesystem->inspect($entry->disk, $row->path);
                if ($row->status !== 'present' || $row->kind !== $observed['kind'] || $row->sha256 !== $observed['sha256']) throw new RuntimeException('A descendant changed; reconcile before moving the folder.');
                $snapshots[] = ['id' => $row->id, 'path' => $row->path, 'identity' => $observed];
            }
            $snapshot = ['identity' => $info, 'parent_id' => $parent->id, 'entries' => $snapshots];
            $operation = Operation::create(['entry_id' => $entry->id, 'disk' => $entry->disk, 'source' => $entry->path, 'destination' => $destination, 'snapshot' => $snapshot]);
            return $this->execute($operation);
        });
    }

    public function resume(Operation $operation): Operation
    {
        return $this->catalog->filesystem->locked($operation->disk, function () use ($operation) {
            $this->catalog->assertRoot($operation->disk);
            return $this->execute($operation->fresh());
        });
    }

    private function matches(Operation $operation, string $path): bool
    {
        $absolute = $this->catalog->filesystem->absolute($operation->disk, $path);
        if (!file_exists($absolute)) return false;
        $actual = $this->catalog->filesystem->inspect($operation->disk, $path);
        $expected = $operation->snapshot['identity'];
        return $actual['kind'] === $expected['kind'] && $actual['device'] === $expected['device'] && $actual['inode'] === $expected['inode'] && $actual['sha256'] === $expected['sha256'];
    }

    private function execute(Operation $operation): Operation
    {
        if ($operation->state === 'completed') return $operation;
        try {
            $source = $this->catalog->filesystem->absolute($operation->disk, $operation->source);
            $destination = $this->catalog->filesystem->absolute($operation->disk, $operation->destination);
            $directory = $operation->snapshot['identity']['kind'] === 'directory';
            if ($operation->state === 'prepared' || $operation->state === 'failed') {
                if (!$this->matches($operation, $operation->destination)) {
                    if (file_exists($destination) || !$this->matches($operation, $operation->source)) throw new RuntimeException('Source or destination changed; manual resolution required.');
                    // Files: exclusive hard link prevents overwriting a concurrent destination.
                    // Directories: same-filesystem rename; all writers must respect the disk lock.
                    $ok = $directory ? @rename($source, $destination) : @link($source, $destination);
                    if (!$ok) throw new RuntimeException('Filesystem move failed (same filesystem/hard-link support required).');
                }
                if (!$this->matches($operation, $operation->destination)) throw new RuntimeException('Destination verification failed.');
                foreach ($operation->snapshot['entries'] as $row) {
                    if (!isset($row['identity'])) continue;
                    $info = $this->catalog->filesystem->inspect($operation->disk, $operation->destination.substr($row['path'], strlen($operation->source)));
                    if ($info !== $row['identity']) throw new RuntimeException('A descendant changed during the move; retained for inspection.');
                }
                DB::transaction(function () use ($operation) {
                    foreach ($operation->snapshot['entries'] as $row) {
                        $entry = Entry::findOrFail($row['id']);
                        if ($entry->path !== $row['path']) throw new RuntimeException('Catalogue changed during move.');
                        $newPath = $operation->destination.substr($row['path'], strlen($operation->source));
                        $attributes = ['path' => $newPath];
                        if ($entry->id === $operation->entry_id) $attributes['parent_id'] = $operation->snapshot['parent_id'];
                        $entry->update($attributes);
                    }
                    if ($operation->snapshot['identity']['kind'] === 'directory') $this->catalog->relocateDerivativeDirectories($operation->disk, $operation->source, $operation->destination);
                    $operation->update(['state' => 'cleanup', 'error' => null]);
                });
            }
            if (!$this->matches($operation, $operation->destination)) throw new RuntimeException('Destination changed before cleanup; source retained.');
            if (!$directory && file_exists($source)) {
                if (!$this->matches($operation, $operation->source)) throw new RuntimeException('Source changed before cleanup; retained for inspection.');
                if (!@unlink($source)) throw new RuntimeException('Cannot remove original hard link; resume cleanup later.');
            }
            $operation->update(['state' => 'completed', 'error' => null]);
        } catch (\Throwable $e) {
            // Never reset cleanup to prepared: the database may already point to destination.
            $operation->update(['error' => $e->getMessage()]);
            throw $e;
        }
        foreach ($operation->snapshot['entries'] as $row) {
            $entry = Entry::findOrFail($row['id']);
            event(new EntryRelocated($entry, $row['path'], $entry->path));
        }
        return $operation->fresh();
    }
}
