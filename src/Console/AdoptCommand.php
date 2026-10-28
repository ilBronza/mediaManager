<?php

namespace IlBronza\MediaManager\Console;

use IlBronza\MediaManager\Integrations\Spatie\Bridge;
use Illuminate\Console\Command;

class AdoptCommand extends Command
{
    protected $signature = 'media-manager:adopt {media : Existing Spatie media ID}';
    protected $description = 'Track an existing Spatie media without copying it or changing its owner/ID';
    public function handle(Bridge $bridge): int
    {
        $class = config('media-library.media_model');
        $this->info($bridge->adopt($class::findOrFail($this->argument('media')))->id);
        return self::SUCCESS;
    }
}
