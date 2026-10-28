<?php

namespace IlBronza\MediaManager\Services;

use IlBronza\MediaManager\Events\EntryRelocated;
use IlBronza\MediaManager\Models\Entry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Reconciler
{
    public function __construct(private Catalog $catalog) {}

    /** Read-only by default; hashes never trigger an automatic association. */
    public function scan(string $disk, bool $apply = false): array
    {
        return $this->catalog->filesystem->locked($disk, function () use ($disk, $apply) {
            $this->catalog->assertIdle($disk);
            $snapshot = $this->catalog->filesystem->scan($disk);
            $tags = [];
            foreach ($snapshot as $path => &$info) {
                $info['uuid'] = $this->catalog->identities->read($this->catalog->filesystem->absolute($disk, $path));
                if ($info['uuid']) $tags[$info['uuid']][] = $path;
            }
            unset($info);
            $entries = Entry::where('disk', $disk)->where('status', '!=', 'superseded')->get();
            $claimed = $entries->keyBy('path');
            $report = ['entries' => [], 'unindexed' => [], 'duplicate_uuids' => array_filter($tags, fn ($paths) => count($paths) > 1), 'applied' => $apply];
            foreach ($entries as $entry) {
                $paths = $tags[$entry->id] ?? [];
                $result = ['id' => $entry->id, 'path' => $entry->path, 'status' => 'missing', 'candidates' => []];
                if (count($paths) > 1) {
                    $result['status'] = 'conflict';
                    $result['candidates'] = $paths;
                } elseif (count($paths) === 1) {
                    $target = $paths[0];
                    $info = $snapshot[$target];
                    $result['candidates'] = [$target];
                    if ($info['kind'] !== $entry->kind || (isset($claimed[$target]) && $claimed[$target]->id !== $entry->id)) {
                        $result['status'] = 'conflict';
                    } elseif ($info['sha256'] !== $entry->sha256) {
                        $result['status'] = 'modified';
                    } else {
                        $result['status'] = $target === $entry->path ? 'present' : 'relocated';
                    }
                } elseif (isset($snapshot[$entry->path])) {
                    $info = $snapshot[$entry->path];
                    if ($info['uuid'] || $info['kind'] !== $entry->kind) $result['status'] = 'conflict';
                    else $result['status'] = $info['sha256'] === $entry->sha256 ? 'present' : 'modified';
                } elseif (!$entry->isDirectory()) {
                    foreach ($snapshot as $path => $info) {
                        if ($info['kind'] === 'file' && !$info['uuid'] && !isset($claimed[$path]) && $info['sha256'] === $entry->sha256) $result['candidates'][] = $path;
                    }
                    if ($result['candidates']) $result['status'] = 'candidate';
                }
                $report['entries'][] = $result;
            }
            foreach ($snapshot as $path => $info) if (!isset($claimed[$path])) $report['unindexed'][] = $path;
            if ($apply) {
                // Parents first; a child can then resolve its new directory without inventing another identity.
                usort($report['entries'], function ($a, $b) use ($entries) {
                    $ae = $entries->firstWhere('id', $a['id']); $be = $entries->firstWhere('id', $b['id']);
                    return [$ae->isDirectory() ? 0 : 1, substr_count($a['candidates'][0] ?? $a['path'], '/')] <=> [$be->isDirectory() ? 0 : 1, substr_count($b['candidates'][0] ?? $b['path'], '/')];
                });
                $events = [];
                DB::transaction(function () use ($report, $disk, &$events) {
                    foreach ($report['entries'] as $result) {
                        $entry = Entry::findOrFail($result['id']);
                        if ($result['status'] === 'relocated') {
                            $events[] = $this->relocate($entry, $result['candidates'][0]);
                        } else {
                            $attributes = ['status' => $result['status']];
                            if ($result['status'] === 'present') $attributes['last_seen_at'] = now();
                            $entry->update($attributes);
                        }
                    }
                });
                foreach ($events as $event) event($event);
            }
            return $report;
        });
    }

    /** Explicit selection of a hash candidate. It never accepts changed content or overwrites another entry. */
    public function recover(Entry $entry, string $path): Entry
    {
        return $this->catalog->filesystem->locked($entry->disk, function () use ($entry, $path) {
            $this->catalog->assertIdle($entry->disk);
            $entry->refresh();
            if ($entry->isDirectory()) throw new RuntimeException('Directory recovery requires a preserved UUID.');
            if (file_exists($this->catalog->filesystem->absolute($entry->disk, $entry->path))) throw new RuntimeException('Old path still exists; this may be a copy.');
            $info = $this->catalog->filesystem->inspect($entry->disk, $path);
            if ($info['kind'] !== 'file' || $info['sha256'] !== $entry->sha256) throw new RuntimeException('Candidate content does not match.');
            $tag = $this->catalog->identities->read($this->catalog->filesystem->absolute($entry->disk, $path));
            if ($tag && $tag !== $entry->id) throw new RuntimeException('Candidate belongs to another identity.');
            if ($this->catalog->at($entry->disk, $path)) throw new RuntimeException('Candidate is already indexed.');
            $event = DB::transaction(fn () => $this->relocate($entry, $path));
            $entry->update(['identity_tagged' => $this->catalog->identities->write($this->catalog->filesystem->absolute($entry->disk, $path), $entry->id)]);
            event($event);
            return $entry->fresh();
        });
    }

    private function relocate(Entry $entry, string $path): EntryRelocated
    {
        $info = $this->catalog->filesystem->inspect($entry->disk, $path);
        if ($info['kind'] !== $entry->kind || $info['sha256'] !== $entry->sha256) throw new RuntimeException('Candidate changed during reconciliation.');
        $occupied = $this->catalog->at($entry->disk, $path);
        if ($occupied && $occupied->id !== $entry->id) throw new RuntimeException('Destination is indexed by another entry.');
        $old = $entry->path;
        $parent = $path === '' ? null : $this->catalog->register($entry->disk, $this->catalog->parentPath($path));
        if ($entry->isDirectory()) $this->catalog->relocateDerivativeDirectories($entry->disk, $old, $path);
        $entry->update(['path' => $path, 'parent_id' => $parent?->id, 'status' => 'present', 'last_seen_at' => now()]);
        return new EntryRelocated($entry->fresh(), $old, $path, true);
    }
}
