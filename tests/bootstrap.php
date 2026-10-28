<?php

$vendor = is_file(__DIR__.'/../vendor/autoload.php') ? __DIR__.'/../vendor' : __DIR__.'/../../.ibtest/vendor';
$loader = require $vendor.'/autoload.php';
$loader->addPsr4('IlBronza\\MediaManager\\', __DIR__.'/../src/', true);
$loader->addPsr4('IlBronza\\MediaManager\\Tests\\', __DIR__.'/', true);
if (is_dir(__DIR__.'/../../FileCabinet/src')) $loader->addPsr4('IlBronza\\FileCabinet\\', __DIR__.'/../../FileCabinet/src/', true);
if (is_dir(__DIR__.'/../../Crud/src')) $loader->addPsr4('IlBronza\\CRUD\\', __DIR__.'/../../Crud/src/', true);
foreach (['UikitTemplate', 'Datatables', 'Buttons', 'Form', 'FormField', 'Menu', 'Ukn'] as $package) {
    if (is_dir(__DIR__.'/../../'.$package.'/src')) {
        $loader->addPsr4('IlBronza\\'.$package.'\\', __DIR__.'/../../'.$package.'/src/', true);
    }
}
require __DIR__.'/runtime.php';
