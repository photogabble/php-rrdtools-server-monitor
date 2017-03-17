<?php

require_once(__DIR__.'/RRDBase.php');

class RRDDiskIO extends RRDBase {

    protected $rrdFileName = 'diskio.rrd';
    private $interval = 10;
    private $iterations = 2;
    private $devices = [];

    public function __construct($path = __DIR__, $debug = false, array $device = ['nb0']){
        parent::__construct($path, $debug);

        $this->devices = $device;
        $this->rrdFilePath = [];

        foreach ($this->devices as $device) {
            $this->rrdFilePath[$device] = $this->path . DIRECTORY_SEPARATOR . 'diskio-' . $device . '.rrd';
        }
        $this->touchGraph();
    }

    protected function touchGraph()
    {
        if (count($this->devices) < 1) {
            return;
        }
        foreach ($this->devices as $device) {
            $this->createGraph($device);
        }
    }

    private function createGraph($device) {
        if (!file_exists($this->rrdFilePath[$device])) {
            $this->debug("Creating [{$this->rrdFilePath[$device]}]\n");
            if (!rrd_create($this->rrdFilePath[$device], [
                "-s",60,
                // rrqm/s wrqm/s
                "DS:RRMerge:GAUGE:120:0:U",
                "DS:WRMerge:GAUGE:120:0:U",

                // r/s w/s
                "DS:RReq:GAUGE:120:0:U",
                "DS:WReq:GAUGE:120:0:U",

                // rBytes/s wBytes/s
                "DS:RByte:GAUGE:120:0:U",
                "DS:WByte:GAUGE:120:0:U",

                // avgrq-sz (Bytes)
                "DS:RSize:GAUGE:120:0:U",

                // avgqu-sz
                "DS:QLength:GAUGE:120:0:U",

                // await
                "DS:WTime:GAUGE:120:0:U",

                // r_await
                "DS:RWTime:GAUGE:120:0:U",

                // w_await
                "DS:WWTime:GAUGE:120:0:U",

                // svctm
                "DS:STime:GAUGE:120:0:U",

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
        $tmpPathName = $this->path . DIRECTORY_SEPARATOR . "diskio.".time().".tmp";
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

    private function collectForDevice($device)
    {
        $stat = $this->runCommand("iostat -dxk {$device} {$this->interval} {$this->iterations}");
        $collect = false;
        $keys = [];
        $collection = [];

        foreach (explode("\n", $stat) as $row) {
            if (empty($row)) {
                continue;
            }

            $row = preg_replace('!\s+!', ' ', $row);

            if (strpos($row, 'Device:') !== false) {
                $collect = true;
                $keys = explode(' ', $row);
                foreach ($keys as $key) {
                    $stats[$key] = 0;
                }
                continue;
            }

            if ($collect === true) {
                $values = explode(' ', $row);
                array_push($collection, array_combine($keys, $values));
                $this->debug($row . "\n");
            }
        }

        // var_dump($collection);
    }

    public function collect()
    {
        foreach ($this->devices as $device) {
            $this->collectForDevice($device);
        }
    }

    public function graph($period = 'day', $graphPath = __DIR__)
    {
        if (!file_exists($graphPath)) {
            $this->fail("The path [$graphPath] does not exist or is not readable.\n");
        }
    }
}

$p = new RRDDiskIO(__DIR__, true, ['nb0', 'nb1']);
$p->collect();
$p->graph('hour', __DIR__ . '/../httpdocs/img');
