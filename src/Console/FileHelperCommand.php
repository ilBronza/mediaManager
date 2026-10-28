<?php

namespace IlBronza\MediaManager\Console;

use IlBronza\MediaManager\Helpers\Files\FileHelpersRegistry;
use IlBronza\MediaManager\Models\Entry;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

class FileHelperCommand extends Command
{
    protected $signature = 'media-manager:file-helper {entry : UUID del catalogo} {helper : sha256, read-uuid o write-uuid}';

    protected $description = 'Esegue un singolo helper su un file già registrato nel catalogo';

    public function handle(FileHelpersRegistry $registry): int
    {
        $entry = Entry::query()->find($this->argument('entry'));

        if (!$entry)
        {
            $this->error('File non presente nel catalogo.');

            return self::FAILURE;
        }

        try
        {
            $result = $registry->run($this->argument('helper'), $entry);
        }
        catch (InvalidArgumentException | RuntimeException $exception)
        {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $result['matches'] ? self::SUCCESS : self::FAILURE;
    }
}
