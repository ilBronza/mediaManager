<?php

namespace IlBronza\MediaManager\Http;

use IlBronza\MediaManager\Helpers\Browser\{BrowserTableHelper, BrowserRowsHelper, BrowserNavigationHelper, CatalogListingHelper};
use IlBronza\MediaManager\Models\Entry;
use IlBronza\MediaManager\Services\{Catalog, Mover, Reconciler};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use IlBronza\MediaManager\Helpers\Files\ReadCatalogEntryHelper;
use IlBronza\MediaManager\Integrations\FileCabinetContext;

class BrowserController
{
    public function index(
        Request $request,
        Catalog $catalog,
        BrowserTableHelper $tables,
        BrowserRowsHelper $rows,
        BrowserNavigationHelper $navigation,
        CatalogListingHelper $listing,
        ReadCatalogEntryHelper $detailsReader,
        FileCabinetContext $context
    )
    {
        $data = $request->validate([
            'disk' => 'sometimes|string',
            'folder' => 'nullable|uuid',
            'entry' => 'nullable|uuid',
        ]);
        $disks = array_values(array_filter(config('media-manager.disks', []), fn ($disk) => Gate::allows('media-manager.browse', [$disk])));
        abort_unless(count($disks), 403);
        $disk = $data['disk'] ?? $disks[0];
        abort_unless(in_array($disk, $disks, true), 403);
        // Catalog browsing remains available even when its storage is offline.
        if (!empty($data['folder']))
        {
            $parent = Entry::query()
                ->where('disk', $disk)
                ->where('status', '!=', 'superseded')
                ->findOrFail($data['folder']);
        }
        else
        {
            $parent = $catalog->at($disk, '');
        }
        if ($parent)
        {
            abort_unless($parent->isDirectory(), 404);
        }
        $path = $parent?->path ?? '';
        $source = 'entries';
        $breadcrumbs = $navigation->breadcrumbs($catalog, $disk, $path, $source);
        $details = null;
        $detailContexts = [];

        if (!empty($data['entry']))
        {
            $selected = Entry::query()
                ->where('disk', $disk)
                ->where('parent_id', $parent?->id)
                ->where('status', '!=', 'superseded')
                ->findOrFail($data['entry']);

            Gate::authorize('media-manager.view', [$selected]);

            $details = $detailsReader->run($selected);
            $detailContexts = $context->forEntry($selected);
        }

        $items = $listing->list($disk, $path)
            ->filter(fn (array $item) => Gate::allows('media-manager.view', [$item['entry']]))
            ->values();

        $folders = $items->where('kind', 'directory')->map(fn (array $item) => [
            'name' => $item['name'],
            'url' => route('media-manager.index', ['disk' => $disk, 'folder' => $item['entry']->id]),
        ]);
        $views = $navigation->views();
        $table = $tables->create($source, $disk, $path, $rows->make($source, $disk, $items));

        return view('media-manager::browser', compact('source', 'views', 'disk', 'disks', 'path', 'breadcrumbs', 'folders', 'table', 'details', 'detailContexts'));
    }

    public function directory(Request $request, Catalog $catalog)
    {
        $data = $request->validate(['disk' => 'required|string', 'path' => 'required|string|max:2048']);
        $this->authorizeDestination($catalog, $data['disk'], $data['path']);
        return $this->perform(fn () => $catalog->createDirectory($data['disk'], $data['path']));
    }

    public function upload(Request $request, Catalog $catalog)
    {
        $data = $request->validate(['disk' => 'required|string', 'path' => 'required|string|max:2048', 'file' => 'required|file|max:'.config('media-manager.max_upload_kb')]);
        $this->authorizeDestination($catalog, $data['disk'], $data['path']);
        return $this->perform(fn () => $catalog->upload($data['disk'], $data['path'], $request->file('file')->getRealPath()));
    }

    public function move(Request $request, Entry $entry, Mover $mover, Catalog $catalog)
    {
        Gate::authorize('media-manager.manage', [$entry->disk, $entry]);
        $data = $request->validate(['path' => 'required|string|max:2048']);
        $this->authorizeDestination($catalog, $entry->disk, $data['path']);
        if ($entry->isDirectory())
        {
            // Folder permissions cannot bypass a stricter descendant policy.
            $children = Entry::query()
                ->where('disk', $entry->disk)
                ->get();

            foreach ($children as $child)
            {
                if (str_starts_with($child->path, $entry->path.'/'))
                {
                    Gate::authorize('media-manager.manage', [$entry->disk, $child]);
                }
            }
        }
        return $this->perform(fn () => $mover->move($entry, $data['path']));
    }

    public function metadata(Request $request, Entry $entry)
    {
        Gate::authorize('media-manager.manage', [$entry->disk, $entry]);
        $data = $request->validate(['metadata' => 'required|json|max:65536']);
        $metadata = json_decode($data['metadata'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($metadata))
        {
            throw ValidationException::withMessages(['metadata' => 'Inserire un oggetto o un array JSON.']);
        }

        $entry->update(['metadata' => $metadata]);
        return back()->with('status', 'Metadati aggiornati.');
    }

    public function recover(Request $request, Entry $entry, Reconciler $reconciler, Catalog $catalog)
    {
        Gate::authorize('media-manager.manage', [$entry->disk, $entry]);
        $data = $request->validate(['path' => 'required|string|max:2048']);
        $this->authorizeDestination($catalog, $entry->disk, $data['path']);
        return $this->perform(fn () => $reconciler->recover($entry, $data['path']));
    }

    public function scan(Request $request, Reconciler $reconciler)
    {
        $data = $request->validate(['disk' => 'required|string']);
        // Separate disk-wide gate: a user allowed to edit one file cannot enumerate the entire disk.
        Gate::authorize('media-manager.reconcile', [$data['disk']]);
        return response()->json($reconciler->scan($data['disk']));
    }

    public function download(Entry $entry, Catalog $catalog)
    {
        Gate::authorize('media-manager.view', [$entry]);
        abort_if($entry->isDirectory(), 404);
        $catalog->assertRoot($entry->disk);
        abort_unless($entry->status === 'present', 409, 'Riconciliare il file prima del download.');
        $info = $catalog->filesystem->inspect($entry->disk, $entry->path);
        abort_unless($info['sha256'] === $entry->sha256, 409, 'Il contenuto è cambiato.');
        return response()->download($catalog->filesystem->absolute($entry->disk, $entry->path), $entry->name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function authorizeDestination(Catalog $catalog, string $disk, string $path): void
    {
        $catalog->filesystem->absolute($disk, $path);
        $parent = $catalog->at($disk, $catalog->parentPath($path));
        abort_unless($parent && $parent->isDirectory(), 422, 'La cartella di destinazione deve essere già indicizzata.');
        Gate::authorize('media-manager.manage', [$disk, $parent]);
    }

    private function perform(callable $callback)
    {
        try
        {
            $callback();
        }
        catch (\RuntimeException | \InvalidArgumentException $exception)
        {
            throw ValidationException::withMessages(['operation' => $exception->getMessage()]);
        }

        return back()->with('status', 'Operazione completata.');
    }
}
