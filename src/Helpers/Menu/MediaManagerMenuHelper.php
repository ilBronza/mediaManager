<?php

namespace IlBronza\MediaManager\Helpers\Menu;

use IlBronza\MediaManager\Helpers\Authorization\MediaManagerAccessHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class MediaManagerMenuHelper
{
    public function __construct(private MediaManagerAccessHelper $access)
    {
    }

    public function register(): void
    {
        if (!config('media-manager.routes') || !$this->access->allows(Auth::user()))
        {
            return;
        }

        $definitions = [
            'catalog' => [
                'text' => 'media-manager::menu.catalog',
                'icon' => 'list',
                'route' => 'media-manager.index',
                'gate' => 'media-manager.browse',
            ],
            'filesystem' => [
                'text' => 'media-manager::menu.filesystem',
                'icon' => 'folder-open',
                'route' => 'media-manager.filesystem',
                'gate' => 'media-manager.filesystem',
            ],
        ];

        $items = [];

        foreach ($definitions as $name => $definition)
        {
            if (!Route::has($definition['route']))
            {
                continue;
            }

            foreach (config('media-manager.disks', []) as $disk)
            {
                if (Gate::allows($definition['gate'], [$disk]))
                {
                    $items[$name] = [
                        'name' => 'mediaManager.'.$name,
                        'text' => $definition['text'],
                        'icon' => $definition['icon'],
                        'href' => route($definition['route'], ['disk' => $disk]),
                        'roles' => $this->access->roles(),
                    ];

                    break;
                }
            }
        }

        if (!$items)
        {
            return;
        }

        $menu = app('menu');
        $group = $menu->provideButton([
            'name' => 'mediaManager',
            'text' => 'media-manager::menu.title',
            'icon' => 'folder-open',
            'roles' => $this->access->roles(),
        ]);

        foreach ($items as $parameters)
        {
            if (!$group->getChildren()->contains('name', $parameters['name']))
            {
                $group->addChild($menu->provideButton($parameters));
            }
        }
    }
}
