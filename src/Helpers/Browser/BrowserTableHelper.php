<?php

namespace IlBronza\MediaManager\Helpers\Browser;

use IlBronza\Datatables\Datatables;
use IlBronza\MediaManager\Browser\BrowserRow;
use IlBronza\MediaManager\Browser\BrowserDatatable;
use Illuminate\Support\Collection;

class BrowserTableHelper
{
    public function create(string $source, string $disk, string $path, Collection $rows): Datatables
    {
        $parameters = config("media-manager.models.$source.fieldsGroupsFiles.index");
        // Same factory and field-group format used by CRUDIndexTrait. Client-side
        // search, ordering and pagination receive the WHOLE authorized directory.
        $table = BrowserDatatable::create(
            'media-manager-'.$source.'-'.substr(hash('sha256', $disk."\0".$path), 0, 16),
            ['index' => $parameters::getTracedFieldsGroup()],
            fn () => $rows,
            false,
            [],
            BrowserRow::class,
            false
        );
        $table->setArrayTable();
        $table->setHasSorting(true);
        $table->setPageLength(50);
        // Let the shared UIkit panel scroll horizontally. Datatables' viewport
        // height fitter can collapse rows when the sidebar stacks above the table.
        $table->setScrollX(false);
        $table->setScrollY(false);
        $table->setColumnDisplayKey('media-manager-'.$source);
        $table->caption = false;
        $table->addHtmlClass('uk-table uk-table-small uk-table-hover uk-table-middle');

        return $table;
    }
}
