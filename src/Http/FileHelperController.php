<?php

namespace IlBronza\MediaManager\Http;

use IlBronza\MediaManager\Helpers\Files\FileHelpersRegistry;
use IlBronza\MediaManager\Models\Entry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use InvalidArgumentException;

class FileHelperController
{
    public function run(Entry $entry, string $helper, FileHelpersRegistry $registry)
    {
        $definition = $registry->get($helper);

        Gate::authorize('media-manager.view', [$entry]);

        if ($definition['writes'])
        {
            Gate::authorize('media-manager.manage', [$entry->disk, $entry]);
        }

        try
        {
            $result = $registry->run($helper, $entry);
        }
        catch (RuntimeException | InvalidArgumentException $exception)
        {
            throw ValidationException::withMessages(['helper' => $exception->getMessage()]);
        }

        return back()->with('file_helper_result', $result);
    }
}
