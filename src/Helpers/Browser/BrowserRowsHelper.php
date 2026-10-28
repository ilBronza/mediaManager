<?php

namespace IlBronza\MediaManager\Helpers\Browser;

use IlBronza\MediaManager\Browser\BrowserRow;
use IlBronza\MediaManager\Helpers\Files\FileHelpersRegistry;
use IlBronza\MediaManager\Integrations\FileCabinetContext;
use Illuminate\Support\Collection;

class BrowserRowsHelper
{
    public function __construct(private FileCabinetContext $context, private FileHelpersRegistry $helpers)
    {
    }

    public function make(string $source, string $disk, Collection $items): Collection
    {
        return $items->map(fn (array $item) => $this->makeRow($source, $disk, $item));
    }

    public function makeRow(string $source, string $disk, array $item): BrowserRow
    {
        $entry = $item['entry'] ?? null;
        $url = null;

        if ($item['kind'] === 'directory')
        {
            if ($source === 'filesystem')
            {
                $url = route('media-manager.filesystem', ['disk' => $disk, 'path' => $item['path']]);
            }
            else
            {
                $url = route('media-manager.index', ['disk' => $disk, 'folder' => $entry->id]);
            }
        }

        $statusLabels = [
            'present' => 'Presente',
            'missing' => 'Mancante',
            'modified' => 'Modificato',
            'conflict' => 'Conflitto',
            'candidate' => 'Da ricollegare',
        ];

        $status = $entry ? ($statusLabels[$entry->status] ?? $entry->status) : '';
        $linked = $entry && ($entry->spatie_media_id || $entry->links_count > 0);
        $contexts = $entry ? $this->context->forEntry($entry) : [];
        $helperButtons = '';

        if ($source === 'entries' && $entry)
        {
            $helperButtons = view('media-manager::cells.helpers', [
                'entry' => $entry,
                'helpers' => $this->helpers->all(),
            ])->render();
        }

        return new BrowserRow([
            'id' => 'file_'.hash('sha256', $disk."\0".$item['path']),
            'kind' => $item['kind'] === 'directory' ? 'Cartella' : 'File',
            'name' => view('media-manager::cells.name', ['item' => $item, 'url' => $url])->render(),
            'path' => e($item['path']),
            'size' => $item['size'],
            'modified_at' => isset($item['modified_at']) ? date('Y-m-d H:i:s', $item['modified_at']) : '',
            'catalog' => $entry ? '<span class="uk-label">A database</span>' : '',
            'status' => e($status),
            'linked' => $linked ? 'Collegato' : '',
            'dossiers' => view('media-manager::cells.dossiers', ['contexts' => $contexts])->render(),
            'helpers' => $helperButtons,
        ]);
    }
}
