<?php

namespace IlBronza\MediaManager\Browser;

use Illuminate\Support\Fluent;

/** Read-only table projection; a physical file is never turned into an Eloquent record. */
class BrowserRow extends Fluent
{
    public function getKeyName(): string
    {
        return 'id';
    }

    public function getKey(): string
    {
        return $this->id;
    }
}
