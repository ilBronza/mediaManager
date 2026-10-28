<?php

namespace IlBronza\MediaManager\Tests\Fixtures;

use IlBronza\Menu\Menu;

class HostMenu extends Menu
{
    public function loadItemsFromProject(): void
    {
        // This isolated package test has no App\Providers\Helpers\MenuItems.
        // Keep the actual service-provider loader and menu rendering pipeline.
    }
}
