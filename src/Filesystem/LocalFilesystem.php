<?php

namespace IlBronza\MediaManager\Filesystem;

use Closure;
use InvalidArgumentException;
use RuntimeException;

class LocalFilesystem
{
    public function normalize(string $path): string
    {
        if (str_contains($path, '\\') || preg_match('/[\x00-\x1f\x7f]/', $path) || str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path)) {
            throw new InvalidArgumentException('Expected a relative filesystem path.');
        }
        $parts = $path === '' ? [] : explode('/', $path);
        foreach ($parts as $part) {
            if (in_array($part, ['', '.', '..'], true) || str_starts_with($part, '.media-manager')) {
                throw new InvalidArgumentException('Invalid or reserved path component.');
            }
        }
        return $path;
    }

    public function root(string $disk): string
    {
        if (!in_array($disk, config('media-manager.disks', []), true) || config("filesystems.disks.$disk.driver") !== 'local') {
            throw new InvalidArgumentException('Disk is not an enabled local disk.');
        }
        // Constructing Flysystem's local adapter can create a missing root. Check the
        // configured directory first, so an unavailable mount is not treated as empty.
        $configured = rtrim((string) config("filesystems.disks.$disk.root"), '/');
        if ($configured === '') throw new RuntimeException('A dedicated existing storage root is required.');
        $root = realpath($configured);
        if ($root === false || !is_dir($root) || !is_readable($root) || is_link($configured)) {
            throw new RuntimeException('Storage root is unavailable or is a symbolic link.');
        }
        foreach (config('media-manager.disks', []) as $other) {
            if ($other === $disk) continue;
            $otherRoot = realpath((string) config("filesystems.disks.$other.root"));
            if ($otherRoot && ($otherRoot === $root || str_starts_with($root, $otherRoot.'/') || str_starts_with($otherRoot, $root.'/'))) {
                throw new RuntimeException('Managed disks must have disjoint storage roots.');
            }
        }
        return $root;
    }

    public function absolute(string $disk, string $path): string
    {
        $path = $this->normalize($path);
        $current = $this->root($disk);
        foreach ($path === '' ? [] : explode('/', $path) as $part) {
            $current .= '/'.$part;
            if (is_link($current)) throw new RuntimeException('Symbolic links are not managed.');
            if (file_exists($current)) {
                $real = realpath($current);
                $root = $this->root($disk);
                if ($real === false || !str_starts_with($real, $root.'/')) throw new RuntimeException('Path escapes the storage root.');
            }
        }
        return $current;
    }

    public function locked(string $disk, Closure $callback): mixed
    {
        $lockPath = $this->root($disk).'/.media-manager.lock';
        if (is_link($lockPath)) throw new RuntimeException('Unsafe lock path.');
        $handle = @fopen($lockPath, 'c');
        if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('Cannot lock storage.');
        try { return $callback(); }
        finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    public function inspect(string $disk, string $path): array
    {
        $absolute = $this->absolute($disk, $path);
        clearstatcache(true, $absolute);
        $before = @stat($absolute);
        if ($before === false) throw new RuntimeException("Cannot read $path.");
        if (is_dir($absolute)) return ['kind' => 'directory', 'sha256' => null, 'size' => null, 'device' => $before['dev'], 'inode' => $before['ino']];
        if (!is_file($absolute)) throw new RuntimeException('Only regular files and directories are supported.');
        $sha = @hash_file('sha256', $absolute);
        clearstatcache(true, $absolute);
        $after = @stat($absolute);
        if ($sha === false || !$after || array_intersect_key($before, array_flip(['dev', 'ino', 'size', 'mtime', 'ctime'])) !== array_intersect_key($after, array_flip(['dev', 'ino', 'size', 'mtime', 'ctime']))) {
            throw new RuntimeException("File changed while reading $path; retry later.");
        }
        return ['kind' => 'file', 'sha256' => $sha, 'size' => $after['size'], 'device' => $after['dev'], 'inode' => $after['ino']];
    }

    /** Read immediate children without hashing contents, tagging files or registering entries. */
    public function listDirectory(string $disk, string $path): array
    {
        $absolute = $this->absolute($disk, $path);
        $names = @scandir($absolute);
        if ($names === false) {
            throw new RuntimeException('Cannot list directory.');
        }

        $result = [];
        foreach ($names as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.media-manager')) {
                continue;
            }
            $childPath = $path === '' ? $name : $path.'/'.$name;
            // lstat does not follow symlinks, including links replaced during enumeration.
            $childAbsolute = $absolute.'/'.$name;
            clearstatcache(true, $childAbsolute);
            $stat = @lstat($childAbsolute);
            if ($stat === false) {
                throw new RuntimeException('Directory changed while listing; refresh and retry.');
            }
            $kind = match ($stat['mode'] & 0170000) {
                0040000 => 'directory',
                0100000 => 'file',
                default => null,
            };
            if ($kind === null) {
                continue;
            }
            $result[] = [
                'path' => $childPath,
                'name' => $name,
                'kind' => $kind,
                'size' => $kind === 'file' ? $stat['size'] : null,
                'modified_at' => $stat['mtime'],
            ];
        }

        usort($result, function (array $left, array $right): int {
            return strcmp($left['kind'], $right['kind'])
                ?: strnatcasecmp($left['name'], $right['name'])
                ?: strcmp($left['name'], $right['name']);
        });

        return $result;
    }

    /** Complete snapshot or exception: an unreadable subtree must never look like missing files. */
    public function scan(string $disk): array
    {
        $result = [];
        $walk = function (string $parent) use (&$walk, &$result, $disk): void {
            $names = @scandir($this->absolute($disk, $parent));
            if ($names === false) throw new RuntimeException('Incomplete scan: unreadable directory.');
            foreach ($names as $name) {
                if ($name === '.' || $name === '..' || str_starts_with($name, '.media-manager')) continue;
                $path = $parent === '' ? $name : "$parent/$name";
                // Skip links without following them; their targets are never indexed.
                if (is_link($this->root($disk).'/'.$path)) continue;
                $result[$path] = $this->inspect($disk, $path);
                if ($result[$path]['kind'] === 'directory') $walk($path);
            }
        };
        $result[''] = $this->inspect($disk, '');
        $walk('');
        return $result;
    }
}
