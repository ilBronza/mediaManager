<?php

namespace IlBronza\MediaManager\Console;

use IlBronza\MediaManager\Models\Operation;
use IlBronza\MediaManager\Services\Mover;
use Illuminate\Console\Command;

class ResumeCommand extends Command
{
    protected $signature = 'media-manager:resume {operation : Operation UUID}';
    protected $description = 'Resume an interrupted move without overwriting an unrelated file';
    public function handle(Mover $mover): int
    {
        $this->info($mover->resume(Operation::findOrFail($this->argument('operation')))->state);
        return self::SUCCESS;
    }
}
