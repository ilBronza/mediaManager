<?php

namespace IlBronza\MediaManager;

use IlBronza\MediaManager\Helpers\Menu\MediaManagerMenuHelper;

class MediaManager
{
    public function __construct(private MediaManagerMenuHelper $menu)
    {
    }

    /** Entry point consumed by config('menu.serviceProviders'). */
    public function manageMenuButtons(): void
    {
        $this->menu->register();
    }
}
