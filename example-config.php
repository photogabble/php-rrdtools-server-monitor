<?php

return [
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
        'enabled' => true,
        'statsUrl' => 'http://127.0.0.1/nginx_status'
    ]
];
