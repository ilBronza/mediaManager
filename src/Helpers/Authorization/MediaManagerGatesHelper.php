<?php

namespace IlBronza\MediaManager\Helpers\Authorization;

use IlBronza\MediaManager\Models\Entry;
use Illuminate\Support\Facades\Gate;

class MediaManagerGatesHelper
{
    public function __construct(private MediaManagerAccessHelper $access)
    {
    }

    public function register(): void
    {
        foreach (['browse', 'filesystem', 'reconcile'] as $ability)
        {
            $name = 'media-manager.'.$ability;

            if (!Gate::has($name))
            {
                Gate::define($name, fn ($user, string $disk) => $this->access->allowsDisk($user, $disk));
            }
        }

        if (!Gate::has('media-manager.view'))
        {
            Gate::define('media-manager.view', fn ($user, Entry $entry) => $this->access->allowsDisk($user, $entry->disk));
        }

        if (!Gate::has('media-manager.manage'))
        {
            Gate::define('media-manager.manage', function ($user, string $disk, Entry $entry)
            {
                return $entry->disk === $disk && $this->access->allowsDisk($user, $disk);
            });
        }

        // Dossier authorization belongs to the host application and is never granted implicitly.
    }
}
