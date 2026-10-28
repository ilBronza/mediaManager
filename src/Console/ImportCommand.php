<?php

namespace IlBronza\MediaManager\Console;

use IlBronza\MediaManager\Services\Catalog;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'media-manager:import {disk} {path? : Import a single file/directory; omit to index the whole disk}';
    protected $description = 'Index existing files in place and assign filesystem UUIDs where supported';
    public function handle(Catalog $catalog): int
    {
        if ($path = $this->argument('path')) $this->info($catalog->import($this->argument('disk'), $path)->id);
        else $this->info('Indexed '.$catalog->importTree($this->argument('disk')).' entries.');
        return self::SUCCESS;
    }
}
