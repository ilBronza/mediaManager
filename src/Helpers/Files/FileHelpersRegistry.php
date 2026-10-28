<?php

namespace IlBronza\MediaManager\Helpers\Files;

use IlBronza\MediaManager\Models\Entry;
use InvalidArgumentException;

class FileHelpersRegistry
{
    /** One declaration drives both the per-file icons and the CLI dispatcher. */
    public function all(): array
    {
        return [
            'sha256' => [
                'label' => 'Calcola SHA-256',
                'icon' => 'hashtag',
                'class' => CalculateSha256Helper::class,
                'writes' => false,
            ],
            'read-uuid' => [
                'label' => 'Leggi UUID',
                'icon' => 'search',
                'class' => ReadUuidHelper::class,
                'writes' => false,
            ],
            'write-uuid' => [
                'label' => 'Scrivi UUID',
                'icon' => 'tag',
                'class' => WriteUuidHelper::class,
                'writes' => true,
            ],
        ];
    }

    public function get(string $name): array
    {
        $helpers = $this->all();

        if (!isset($helpers[$name]))
        {
            throw new InvalidArgumentException('Helper non riconosciuto.');
        }

        return $helpers[$name];
    }

    public function run(string $name, Entry $entry): array
    {
        $definition = $this->get($name);

        return app($definition['class'])->run($entry);
    }
}
