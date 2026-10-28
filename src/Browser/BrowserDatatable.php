<?php

namespace IlBronza\MediaManager\Browser;

use IlBronza\Datatables\Datatables;

class BrowserDatatable extends Datatables
{
    public function hasReloadButton(): bool
    {
        // The inherited button calls ajax.reload(). Array tables refresh through
        // the browser's "Aggiorna" link, which also reloads the directory navigation.
        return $this->isAjaxTable() && parent::hasReloadButton();
    }
}
