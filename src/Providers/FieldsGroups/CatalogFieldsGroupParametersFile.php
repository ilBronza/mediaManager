<?php

namespace IlBronza\MediaManager\Providers\FieldsGroups;

use IlBronza\Datatables\Providers\FieldsGroupParametersFile;

class CatalogFieldsGroupParametersFile extends FieldsGroupParametersFile
{
    public static function getFieldsGroup(): array
    {
        return [
            'fields' => [
                'id' => [
                    'type' => 'flat',
                    'rowId' => true,
                    'visible' => false,
                    'filterType' => 'none',
                    'translatedName' => 'ID',
                ],
                'kind' => [
                    'type' => 'flat',
                    'translatedName' => 'Tipo',
                    'order' => ['priority' => 0, 'type' => 'asc'],
                ],
                'name' => [
                    'type' => 'flat',
                    'translatedName' => 'Nome',
                    'order' => ['priority' => 1, 'type' => 'asc'],
                ],
                'path' => [
                    'type' => 'flat',
                    'translatedName' => 'Percorso',
                    'visible' => false,
                ],
                'size' => [
                    'type' => 'flat',
                    'translatedName' => 'Dimensione (byte)',
                    'nullLast' => true,
                ],
                'status' => [
                    'type' => 'flat',
                    'translatedName' => 'Ultima verifica',
                ],
                'linked' => [
                    'type' => 'flat',
                    'translatedName' => 'Collegamenti',
                ],
                'dossiers' => [
                    'type' => 'flat',
                    'translatedName' => 'Dossier',
                ],
                'helpers' => [
                    'type' => 'flat',
                    'translatedName' => 'Operazioni sul file',
                    'sortable' => false,
                    'filterType' => 'none',
                ],
            ],
        ];
    }
}
