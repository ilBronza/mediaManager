<?php

namespace IlBronza\MediaManager\Providers\FieldsGroups;

class FilesystemFieldsGroupParametersFile extends CatalogFieldsGroupParametersFile
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
                'modified_at' => [
                    'type' => 'flat',
                    'translatedName' => 'Modificato il',
                ],
                'catalog' => [
                    'type' => 'flat',
                    'translatedName' => 'Catalogo',
                ],
                'linked' => [
                    'type' => 'flat',
                    'translatedName' => 'Collegamenti',
                ],
                'dossiers' => [
                    'type' => 'flat',
                    'translatedName' => 'Dossier',
                ],
            ],
        ];
    }
}
