<?php

namespace IlBronza\MediaManager\Helpers\Browser;

use IlBronza\MediaManager\Services\Catalog;
use Illuminate\Support\Facades\Gate;

class BrowserNavigationHelper
{
    public function breadcrumbs(Catalog $catalog, string $disk, string $path, string $source): array
    {
        $breadcrumbs = [];
        $current = '';
        $components = $path === '' ? [] : explode('/', $path);

        array_unshift($components, '');

        foreach ($components as $name)
        {
            $current = $current === '' ? $name : $current.'/'.$name;
            $entry = $catalog->at($disk, $current);

            if ($entry)
            {
                Gate::authorize('media-manager.view', [$entry]);
            }

            if ($source === 'filesystem')
            {
                $url = route('media-manager.filesystem', ['disk' => $disk, 'path' => $current]);
            }
            else
            {
                $url = route('media-manager.index', ['disk' => $disk, 'folder' => $entry?->id]);
            }

            $breadcrumbs[] = [
                'name' => $name === '' ? $disk : $name,
                'path' => $current,
                'url' => $url,
            ];
        }

        return $breadcrumbs;
    }

    public function views(): array
    {
        $definitions = [
            'entries' => [
                'label' => 'Catalogo',
                'gate' => 'media-manager.browse',
                'route' => 'media-manager.index',
            ],
            'filesystem' => [
                'label' => 'Filesystem',
                'gate' => 'media-manager.filesystem',
                'route' => 'media-manager.filesystem',
            ],
        ];

        $views = [];

        foreach ($definitions as $source => $definition)
        {
            $disks = [];

            foreach (config('media-manager.disks', []) as $disk)
            {
                if (Gate::allows($definition['gate'], [$disk]))
                {
                    $disks[] = $disk;
                }
            }

            if ($disks)
            {
                $views[$source] = [
                    'label' => $definition['label'],
                    'disks' => $disks,
                    'route' => $definition['route'],
                ];
            }
        }

        return $views;
    }
}
