<?php

require_once(__DIR__.'/RRDBase.php');

class RRDDiskUsage extends RRDBase {

    protected $rrdFileName = 'diskusage.rrd';
    private $interval = 10;
    private $iterations = 2;
    private $devices = [];

    public function __construct($path = __DIR__, $debug = false, array $device = ['nbd0' => '/dev/nbd0']){
        parent::__construct($path, $debug);

        $this->devices = $device;
        $this->rrdFilePath = [];

        foreach ($this->devices as $device => $path) {
            $this->rrdFilePath[$device] = $this->path . DIRECTORY_SEPARATOR . 'diskusage-' . $device . '.rrd';
        }
        $this->touchGraph();
    }

    protected function touchGraph()
    {
        if (count($this->devices) < 1) {
            return;
        }
        foreach (array_keys($this->devices) as $device) {
            $this->createGraph($device);
        }
    }

    private function createGraph($device) {
        if (!file_exists($this->rrdFilePath[$device])) {
            $this->debug("Creating [{$this->rrdFilePath[$device]}]\n");
            if (!rrd_create($this->rrdFilePath[$device], [
                "-s",60,
                
                // bytes used
                "DS:BytesUsed:GAUGE:120:0:U",
                
                // %util
                "DS:Util:GAUGE:120:0:U",

                "RRA:AVERAGE:0.5:1:2880",
                "RRA:AVERAGE:0.5:30:672",
                "RRA:AVERAGE:0.5:120:732",
                "RRA:AVERAGE:0.5:720:1460",

                "RRA:MAX:0.5:1:2880",
                "RRA:MAX:0.5:30:672",
                "RRA:MAX:0.5:120:732",
                "RRA:MAX:0.5:720:1460"
            ])){
                $this->fail(rrd_error());
            }
        }
    }

    private function runCommand($cmd)
    {
        $tmpPathName = $this->path . DIRECTORY_SEPARATOR . "diskusage.".time().".tmp";
        $cmd .= ' > ' . $tmpPathName;
        $this->debug("Executing: [$cmd]\n");
        exec($cmd);
        if (!file_exists($tmpPathName)) {
            $this->fail("The file [$tmpPathName] could not be found.");
        }
        $stat = file_get_contents($tmpPathName);
        unlink($tmpPathName);
        return trim($stat);
    }

    private function collectForDevice($device, $path)
    {
        $stat = $this->runCommand("df {$path}");
        // ...
    }

    public function collect()
    {
        foreach ($this->devices as $device => $path) {
            $this->collectForDevice($device, $path);
        }
    }

    public function graph($period = 'day', $graphPath = __DIR__)
    {
        if (!file_exists($graphPath)) {
            $this->fail("The path [$graphPath] does not exist or is not readable.\n");
        }

        $colours = [
            '#2C3E50',
            '#0EAD9A',
            '#F8C82D',
            '#E84B3A',
            '#832D51',
            '#74525F',
            '#404148',
            '#6EC198',
            '#27AE60'
        ];

        // ...

    }
}

$p = new RRDDiskUsage(__DIR__, true, ['nbd0' => '/dev/nbd0', 'nbd1' => '/dev/nbd1']);
$p->collect();
$p->graph('hour', __DIR__ . '/../httpdocs/img');
$p->graph('day', __DIR__ . '/../httpdocs/img');
$p->graph('week', __DIR__ . '/../httpdocs/img');
$p->graph('month', __DIR__ . '/../httpdocs/img');
$p->graph('year', __DIR__ . '/../httpdocs/img');
