<?php

namespace IlBronza\MediaManager\Helpers\Browser;

use IlBronza\MediaManager\Services\Catalog;
use Illuminate\Support\Collection;
use RuntimeException;

class FilesystemListingHelper
{
    public function __construct(private Catalog $catalog)
    {
    }

    /** Read physical metadata for one path without hashing or indexing it. */
    public function read(string $disk, string $path): array
    {
        $this->catalog->assertRoot($disk);

        $absolutePath = $this->catalog->filesystem->absolute($disk, $path);

        clearstatcache(true, $absolutePath);

        $information = @lstat($absolutePath);
        $kind = $information ? ($information['mode'] & 0170000) : null;

        if (!in_array($kind, [0040000, 0100000], true))
        {
            throw new RuntimeException('File or directory unavailable.');
        }

        return [
            'path' => $path,
            'name' => $path === '' ? $disk : basename($path),
            'kind' => $kind === 0040000 ? 'directory' : 'file',
            'size' => $kind === 0100000 ? $information['size'] : null,
            'modified_at' => $information['mtime'],
        ];
    }

    /** One physical directory; no hashes, xattr writes or catalog mutations. Caller authorizes disk access. */
    public function list(string $disk, string $path = ''): Collection
    {
        $this->catalog->assertRoot($disk);

        return collect($this->catalog->filesystem->listDirectory($disk, $path));
    }
}
