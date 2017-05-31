<?php
date_default_timezone_set('GMT');
require_once __DIR__ . "/vendor/autoload.php";

if (! file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'config.php')){
    echo 'Cant find config.php in path ['. __DIR__ .']' . PHP_EOL;
    exit(1);
}

$configuration = include __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
$monitor = new \Carbontwelve\Monitor\Monitor($configuration);
$monitor->run();