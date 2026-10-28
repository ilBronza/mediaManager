<?php

namespace IlBronza\MediaManager\Events;

use IlBronza\MediaManager\Models\Entry;

class EntryRelocated
{
    public function __construct(public Entry $entry, public string $oldPath, public string $newPath, public bool $external = false) {}
}
