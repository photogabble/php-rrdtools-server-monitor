<?php
date_default_timezone_set('GMT');
require_once __DIR__ . "/vendor/autoload.php";

$monitor = new \Carbontwelve\Monitor\Monitor([
    'RRDCpu' => [
        'enabled' => true,
        'interval' => 1
    ],
    'RRDDiskIO' => [
        'enabled' => true,
        'interval' => 10,
        'devices' => [
            'nb0',
            'nb1'
        ]
    ],
    'RRDDiskUsage' => [
        'enabled' => true,
        'devices' => [
            'nbd0' => '/dev/nbd0'
        ]
    ],
    'RRDMemory' => [
        'enabled' => true,
    ],
    'RRDNetwork' => [
        'enabled' => true,
    ],
    'RRDNginx' => [
        'enabled' => false,
        'statsUrl' => 'http://127.0.0.1/nginx_status'
    ]
]);
