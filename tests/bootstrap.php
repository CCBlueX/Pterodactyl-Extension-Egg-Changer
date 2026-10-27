<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefixes = ['EggChanger\\Tests\\' => __DIR__.'/', 'EggChanger\\' => __DIR__.'/../src/'];

    foreach ($prefixes as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory.str_replace('\\', '/', mb_substr($class, mb_strlen($prefix))).'.php';

            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
