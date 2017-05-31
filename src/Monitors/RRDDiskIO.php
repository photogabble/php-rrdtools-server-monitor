<?php

namespace Carbontwelve\Monitor\Monitors;

class RRDDiskIO extends RRDBase {

    protected $rrdFileName = 'diskio.rrd';

    protected $graphName = 'disk_usage_%period%.png';

    protected $configuration = [
        'interval' => 10,
        'iterations' => 2,
        'devices' => []
    ];

    protected function configurationLoaded()
    {
        if (count($this->configuration['devices']) < 1) {
            return false;
        }
        $this->rrdFilePath = [];
        foreach ($this->configuration['devices'] as $device) {
            $this->rrdFilePath[$device] = $this->path . DIRECTORY_SEPARATOR . 'diskio-' . $device . '.rrd';
        }
        return parent::configurationLoaded();
    }

    public function touchGraph()
    {
        if (count($this->configuration['devices']) < 1) {
            return;
        }
        foreach ($this->configuration['devices'] as $device) {
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
        $stat = $this->runCommand("iostat -dxk {$device} {$this->configuration['interval']} {$this->configuration['iterations']}");
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

        $update = end($collection);

        $keys = [];
        $values = [];
        $mapping = [
            'rrqm/s' => 'RRMerge',
            'wrqm/s' => 'WRMerge',
            'r/s' => 'RReq',
            'w/s' => 'WReq',
            'rkB/s' => 'RByte',
            'wkB/s' => 'WByte',
            'avgrq-sz' => 'RSize',
            'avgqu-sz' => 'QLength',
            'await' => 'WTime',
            'r_await' => 'RWTime',
            'w_await' => 'WWTime',
            'svctm' => 'STime',
            '%util' => 'Util'
        ];

        foreach ($mapping as $from => $to){
            if (isset($update[$from])) {
                array_push($keys, $to);
                if (in_array($from,['rkB/s', 'wkB/s'])) {
                    array_push($values, (float) ($update[$from] * 1024)); // We want these at Bytes not KB
                    continue;
                }
                array_push($values, (float) $update[$from]);
            }
        }

        if (!rrd_update($this->rrdFilePath[$device], [
            "-t",
            implode(':', $keys),
            'N:' . implode(':', $values)
        ])){
            $this->fail(rrd_error());
        }
    }

    public function collect()
    {
        foreach ($this->configuration['devices'] as $device) {
            $this->collectForDevice($device);
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

        $config = [
            "-s","-1$period",
            "-t Disk Utilization in the last $period",
            "-z",
            "--lazy",
            "-h", "150", "-w", "700",
            "-l 0",
            "-a", "PNG",
            "--pango-markup",
            "--lower-limit=0",
            "--units-exponent=0",
            "-v Utilization %"
        ];

        foreach ($this->configuration['devices'] as $device) {
            array_push($config, "DEF:{$device}Avg={$this->rrdFilePath[$device]}:Util:AVERAGE");
            array_push($config, "DEF:{$device}Max={$this->rrdFilePath[$device]}:Util:MAX");
        }

        foreach ($this->configuration['devices'] as $device) {
            $colour = array_shift($colours);
            $config = array_merge($config, [
                "LINE2:{$device}Avg{$colour}:" . $device . ' Utilization',
                "GPRINT:{$device}Avg:LAST:   Current\\: %5.2lf%%",
                "GPRINT:{$device}Avg:MIN:  Min\\: %5.2lf%%",
                "GPRINT:{$device}Max:MAX:  Max\\: %5.2lf%%",
                "GPRINT:{$device}Avg:AVERAGE: Avg\\: %5.2lf%%\\n",
            ]);
        }

        array_push($config, 'COMMENT:<span foreground="#ABABAB" size="x-small">'. date('D M jS H') . '\:' . date('i') . '\:' . date('s') .'</span>\r');

        if(!rrd_graph($graphPath . DIRECTORY_SEPARATOR . $this->getGraphName($period), $config)) {
            $this->fail('Error writing Disk IO graph for period '. $period  .' ['. rrd_error() .']');
        }

    }
}

// $p = new RRDDiskIO(__DIR__, true, ['nb0', 'nb1']);
// $p->collect();
// $p->graph('hour', __DIR__ . '/../httpdocs/img');
// $p->graph('day', __DIR__ . '/../httpdocs/img');
// $p->graph('week', __DIR__ . '/../httpdocs/img');
// $p->graph('month', __DIR__ . '/../httpdocs/img');
// $p->graph('year', __DIR__ . '/../httpdocs/img');
