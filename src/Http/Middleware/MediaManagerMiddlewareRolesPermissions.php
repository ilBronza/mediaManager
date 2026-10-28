<?php

namespace IlBronza\MediaManager\Http\Middleware;

use IlBronza\CRUD\Middleware\CRUDBasePackageMiddlewareRolesPermissions;
use IlBronza\MediaManager\Helpers\Authorization\MediaManagerAccessHelper;
use Illuminate\Http\Request;

class MediaManagerMiddlewareRolesPermissions extends CRUDBasePackageMiddlewareRolesPermissions
{
    protected string $configPackageName = 'media-manager';

    protected function getRolesForCurrentRoute(Request $request): array
    {
        // The same package-wide role requirement applies to every HTTP endpoint.
        return app(MediaManagerAccessHelper::class)->roles();
    }
}
