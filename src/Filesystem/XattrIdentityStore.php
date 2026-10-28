<?php

namespace IlBronza\MediaManager\Filesystem;

use IlBronza\MediaManager\Contracts\IdentityStore;
use Illuminate\Support\Str;

class XattrIdentityStore implements IdentityStore
{
    // PECL xattr adds the user namespace itself on Linux.
    private const ATTRIBUTE = 'ilbronza.media_uuid';

    public function read(string $absolutePath): ?string
    {
        if (!function_exists('xattr_get')) return null;
        $value = @xattr_get($absolutePath, self::ATTRIBUTE);
        return is_string($value) && Str::isUuid($value) ? strtolower($value) : null;
    }

    public function write(string $absolutePath, string $uuid): bool
    {
        if (!function_exists('xattr_set')) return false;
        return @xattr_set($absolutePath, self::ATTRIBUTE, $uuid) && $this->read($absolutePath) === $uuid;
    }
}
