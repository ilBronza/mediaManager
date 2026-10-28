<?php

namespace IlBronza\MediaManager\Http;

use IlBronza\MediaManager\Helpers\Browser\{BrowserTableHelper, BrowserRowsHelper, BrowserNavigationHelper, FilesystemListingHelper, CatalogOverlayHelper};
use IlBronza\MediaManager\Services\Catalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

class FilesystemBrowserController
{
    public function index(
        Request $request,
        Catalog $catalog,
        BrowserTableHelper $tables,
        BrowserRowsHelper $rows,
        BrowserNavigationHelper $navigation,
        FilesystemListingHelper $listing,
        CatalogOverlayHelper $overlay
    )
    {
        $data = $request->validate([
            'disk' => 'sometimes|string',
            'path' => 'nullable|string|max:2048',
        ]);
        $disks = array_values(array_filter(
            config('media-manager.disks', []),
            fn ($disk) => Gate::allows('media-manager.filesystem', [$disk])
        ));
        abort_unless(count($disks), 403);
        $disk = $data['disk'] ?? $disks[0];
        abort_unless(in_array($disk, $disks, true), 403);
        $path = $data['path'] ?? '';

        try
        {
            $catalog->filesystem->normalize($path);
            $breadcrumbs = $navigation->breadcrumbs($catalog, $disk, $path, 'filesystem');
            $absolute = $catalog->filesystem->absolute($disk, $path);
            abort_unless(is_dir($absolute), 404);
            $physical = $listing->list($disk, $path);
        }
        catch (InvalidArgumentException $exception)
        {
            abort(422, $exception->getMessage());
        }
        catch (RuntimeException $exception)
        {
            // Authorization/HTTP exceptions must keep their own status codes.
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface)
            {
                throw $exception;
            }
            abort(409, $exception->getMessage());
        }

        $visible = $overlay->attach($disk, $physical)
            ->filter(fn (array $item) => !$item['entry'] || Gate::allows('media-manager.view', [$item['entry']]))
            ->values();

        $source = 'filesystem';
        $views = $navigation->views();
        $folders = $visible->where('kind', 'directory')->map(fn ($item) => [
            'name' => $item['name'],
            'url' => route('media-manager.filesystem', ['disk' => $disk, 'path' => $item['path']]),
        ]);
        $table = $tables->create($source, $disk, $path, $rows->make($source, $disk, $visible));

        return view('media-manager::browser', compact('source', 'views', 'disk', 'disks', 'path', 'breadcrumbs', 'folders', 'table'));
    }
}
