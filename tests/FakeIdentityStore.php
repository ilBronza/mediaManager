<?php

namespace IlBronza\MediaManager\Tests;

use IlBronza\MediaManager\Contracts\IdentityStore;

class FakeIdentityStore implements IdentityStore
{
    public array $tags = [];
    public bool $supported = true;
    private function key(string $path): string { $stat = stat($path); return $stat['dev'].':'.$stat['ino']; }
    public function read(string $absolutePath): ?string { return $this->supported ? ($this->tags[$this->key($absolutePath)] ?? null) : null; }
    public function write(string $absolutePath, string $uuid): bool { if (!$this->supported) return false; $this->tags[$this->key($absolutePath)] = $uuid; return true; }
}
