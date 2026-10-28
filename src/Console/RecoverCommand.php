<?php

namespace IlBronza\MediaManager\Console;

use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Services\Reconciler;
use Illuminate\Console\Command;

class RecoverCommand extends Command
{
    protected $signature = 'media-manager:recover {entry : Entry UUID} {path : Selected relative path}';
    protected $description = 'Explicitly reconnect a missing file to a verified hash candidate';
    public function handle(Reconciler $reconciler): int
    {
        $this->info($reconciler->recover(Entry::findOrFail($this->argument('entry')), $this->argument('path'))->path);
        return self::SUCCESS;
    }
}
