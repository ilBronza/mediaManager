<?php

$runtime = __DIR__.'/runtime';
if (!is_dir($runtime.'/bootstrap')) {
    $source = dirname((new ReflectionClass(Orchestra\Testbench\TestCase::class))->getFileName(), 2).'/laravel';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($files as $file) {
        $destination = $runtime.substr($file->getPathname(), strlen($source));
        if ($file->isDir()) { if (!is_dir($destination)) mkdir($destination, 0777, true); }
        elseif (!is_file($destination)) copy($file->getPathname(), $destination);
    }
}
