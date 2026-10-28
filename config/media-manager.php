<?php

return [
    // Names from the host application's config/filesystems.php, e.g. ['documents', 'images'].
    // This prototype currently implements local disks only. No storage is selected by default.
    'disks' => [],
    'routes' => true,
    'prefix' => 'media-manager',
    'middleware' => ['web', 'auth'],
    // Either role grants package access; per-disk/per-entry gates may restrict it further.
    'defaultRoles' => ['administrator', 'mediaManager'],
    // Reuse the host's UIkit assets, menu and templating. Custom layouts must yield 'content'.
    'layout' => 'uikittemplate::app',
    // Same fieldsGroupsFiles convention as the CRUD package. Replace either provider in the host.
    'models' => [
        'entries' => [
            'fieldsGroupsFiles' => [
                'index' => \IlBronza\MediaManager\Providers\FieldsGroups\CatalogFieldsGroupParametersFile::class,
            ],
        ],
        'filesystem' => [
            'fieldsGroupsFiles' => [
                'index' => \IlBronza\MediaManager\Providers\FieldsGroups\FilesystemFieldsGroupParametersFile::class,
            ],
        ],
    ],
    'max_upload_kb' => 51200,
    // Uses ext-xattr when installed; otherwise identities are recovered by hash proposals.
    'identity_store' => \IlBronza\MediaManager\Filesystem\XattrIdentityStore::class,
    // Optional bridge. Enable before adopting existing Spatie records.
    'spatie' => false,
    // Index only AFTER Spatie/File Cabinet has written each new media in its own chosen location.
    'auto_adopt' => true,
];
