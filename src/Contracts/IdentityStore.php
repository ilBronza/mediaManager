<?php

namespace IlBronza\MediaManager\Contracts;

interface IdentityStore
{
    public function read(string $absolutePath): ?string;
    /** False means unsupported/unwritable. Never claim a tag was persisted without reading it back. */
    public function write(string $absolutePath, string $uuid): bool;
}
