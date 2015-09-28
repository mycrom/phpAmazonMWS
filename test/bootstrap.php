<?php
if (!file_exists(__DIR__ . '/log.txt')) {
    throw new RuntimeException('log.txt not found!');
}

if (!file_exists(__DIR__ . '/test-config.php')) {
    throw new RuntimeException('test-config.php not found!');
}

if (!file_exists(__DIR__ . '/helperFunctions.php')) {
    throw new RuntimeException('Helper functions cannot be loaded');
}

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    throw new RuntimeException('Autoload not found. Did you run `composer install`?');
}

require_once __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/helperFunctions.php';