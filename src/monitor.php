<?php

namespace Carbontwelve\Monitor;

use Carbontwelve\Monitor\Monitors\RRDBase;
use Carbontwelve\Monitor\Monitors\RRDCpu;
use Carbontwelve\Monitor\Monitors\RRDDiskIO;
use Carbontwelve\Monitor\Monitors\RRDDiskUsage;
use Carbontwelve\Monitor\Monitors\RRDMemory;
use Carbontwelve\Monitor\Monitors\RRDNetwork;
use Carbontwelve\Monitor\Monitors\RRDNginx;

Class Monitor {

    private $monitors = [
        RRDCpu::class,
        RRDDiskIO::class,
        RRDDiskUsage::class,
        RRDMemory::class,
        RRDNetwork::class,
        RRDNginx::class,
    ];

    private $timestamp;

    private $config = [];

    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'basePath' => __DIR__ . DIRECTORY_SEPARATOR . 'RRDStore',
            'outputPath' => __DIR__ . DIRECTORY_SEPARATOR . 'public',
            'debug' => true
        ];

        $config = array_merge($defaultConfig, $config);

        $this->timestamp = date('c');

        $liveMonitors = [];

        foreach ($this->monitors as $monitorName) {
            /** @var RRDBase $monitor */
            $monitor = new $monitorName($defaultConfig['basePath'], $defaultConfig['debug']);
            $className = explode('\\', get_class($monitor));
            $className = end($className);
            $defaultMonitorConfig = [
                'enabled' => false,
            ];
            $monitorConfig = array_merge($defaultMonitorConfig,$monitor->getConfiguration());

            if (isset($config[$className])){
                $continue = $monitor->setConfiguration(array_merge($monitorConfig, $config[$className]));
            }else{
                $continue = $monitor->setConfiguration($monitorConfig);
            }

            if (!$continue) {
                continue;
            }

            if ($monitor->getConfiguration('enabled', false) === false) {
                continue;
            }

            $liveMonitors[$monitorName] = $monitor;
        }

        $this->monitors = $liveMonitors;
        $this->config = $config;
    }

    private function touchGraphs()
    {
        /** @var RRDBase $monitor */
        foreach ($this->monitors as $monitor) {
            $monitor->touchGraph();
        }
    }

    private function collect()
    {
        /** @var RRDBase $monitor */
        foreach ($this->monitors as $monitor) {
            $monitor->collect();
        }
    }

    private function generateGraphs($periods = ['hour', 'day', 'week', 'month', 'year']) {
        /** @var RRDBase $monitor */
        foreach ($this->monitors as $monitor) {
            foreach ($periods as $period) {
                $monitor->graph($period, $this->config['outputPath']);
            }
        }
    }

    private function writeHTML() {

    }

    public function run() {
        $this->touchGraphs();
        $this->collect();
        $this->generateGraphs();
        $this->writeHTML();
    }
}