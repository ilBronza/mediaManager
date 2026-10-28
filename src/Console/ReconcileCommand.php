<?php

namespace IlBronza\MediaManager\Console;

use IlBronza\MediaManager\Services\Reconciler;
use Illuminate\Console\Command;

class ReconcileCommand extends Command
{
    protected $signature = 'media-manager:reconcile {disk} {--apply : Apply unambiguous UUID moves and record statuses}';
    protected $description = 'Scan the filesystem; preview by default, never auto-associate hash candidates';
    public function handle(Reconciler $reconciler): int
    {
        $report = $reconciler->scan($this->argument('disk'), (bool) $this->option('apply'));
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return collect($report['entries'])->contains(fn ($row) => !in_array($row['status'], ['present', 'relocated'], true)) || $report['duplicate_uuids'] ? self::FAILURE : self::SUCCESS;
    }
}
