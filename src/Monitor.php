<?php

namespace Carbontwelve\Monitor;

use Carbontwelve\Monitor\Libs\View;
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
            'storagePath' => __DIR__ . DIRECTORY_SEPARATOR . 'RRDStore',
            'outputPath' => __DIR__ . DIRECTORY_SEPARATOR . 'public',
            'debug' => true
        ];

        if (! file_exists($defaultConfig['storagePath'])) {
            echo "Storage Path does not exist or is not writable." . PHP_EOL;
            exit(1);
        }

        if (! file_exists($defaultConfig['outputPath'])) {
            echo "Output Path does not exist or is not writable." . PHP_EOL;
            exit(1);
        }

        $config = array_merge($defaultConfig, $config);

        $this->timestamp = date('c');

        $liveMonitors = [];

        foreach ($this->monitors as $monitorName) {
            /** @var RRDBase $monitor */
            $monitor = new $monitorName($defaultConfig['storagePath'], $defaultConfig['debug']);
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

    private function generateGraphs($periods = []) {
        /** @var RRDBase $monitor */
        foreach ($this->monitors as $monitor) {
            foreach ($periods as $period) {
                $monitor->graph($period, $this->config['outputPath'] . '/img');
            }
        }
    }

    private function writeHTML($periods = []) {
        $graphs = [];

        /** @var RRDBase $monitor */
        foreach ($this->monitors as $name => $monitor) {
            $graphs[$name] = $monitor->getGraphName();
        }

        $view = new View(__DIR__ . DIRECTORY_SEPARATOR . 'View' . DIRECTORY_SEPARATOR . 'dashboard.php');
        file_put_contents($this->config['outputPath'] . DIRECTORY_SEPARATOR . 'index.html', $view->render([
            'periods' => $periods,
            'graphs' => $graphs,
            'timestamp' => $this->timestamp
        ]));
    }

    public function run() {
        $periods = ['hour', 'day', 'week', 'month', 'year'];
        $this->touchGraphs();
        $this->collect();
        $this->generateGraphs($periods);
        $this->writeHTML($periods);
    }
}
