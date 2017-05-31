<?php

namespace Carbontwelve\Monitor\Monitors;

class RRDDiskUsage extends RRDBase
{
    protected $configuration = [
        'devices' => []
    ];

    protected $graphName = 'disk_consumption_%period%.png';

    protected function configurationLoaded()
    {
        if (count($this->configuration['devices']) < 1) {
            return false;
        }
        $this->rrdFilePath = [];
        foreach ($this->configuration['devices'] as $device => $path) {
            $this->rrdFilePath[$device] = $this->path . DIRECTORY_SEPARATOR . 'diskusage-' . $device . '.rrd';
        }
        return parent::configurationLoaded();
    }

    public function touchGraph()
    {
        if (count($this->configuration['devices']) < 1) {
            return;
        }
        foreach (array_keys($this->configuration['devices']) as $device) {
            $this->createGraph($device);
        }
    }

    private function createGraph($device)
    {
        if (!file_exists($this->rrdFilePath[$device])) {
            $this->debug("Creating [{$this->rrdFilePath[$device]}]\n");
            if (!rrd_create($this->rrdFilePath[$device], [
                "-s",
                60,

                // bytes used
                "DS:BytesUsed:GAUGE:120:0:U",

                // bytes available
                "DS:BytesAvailable:GAUGE:120:0:U",

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
            ])
            ) {
                $this->fail(rrd_error());
            }
        }
    }

    private function runCommand($cmd)
    {
        $tmpPathName = $this->path . DIRECTORY_SEPARATOR . "diskusage." . time() . ".tmp";
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
        $stat = explode("\n", $stat);
        $stat = end($stat); // Only interested in last line
        $stat = preg_replace('!\s+!', ' ', $stat);
        $stat = explode(" ", $stat);

        if (count($stat) < 6) {
            $this->fail("Error parsing output of df\n");
        }

        $stats = [
            'Util' => $stat[4],
            'BytesUsed' => $stat[2] * 1024,
            'BytesAvailable' => $stat[3] * 1024
        ];

        if (strpos($stats['Util'], '%') !== false) {
            $stats['Util'] = substr($stats['Util'], 0, strpos($stats['Util'], '%'));
        }

        if (!rrd_update($this->rrdFilePath[$device], [
            "-t",
            implode(':', array_keys($stats)),
            'N:' . implode(':', array_values($stats))
        ])
        ) {
            $this->fail(rrd_error());
        }
    }

    public function collect()
    {
        foreach ($this->configuration['devices'] as $device => $path) {
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

        $config = [
            "-s",
            "-1$period",
            "-t Disk Consumption in the last $period",
            "-z",
            "--lazy",
            "-h",
            "150",
            "-w",
            "700",
            "-l 0",
            "-b",
            "1024",
            "-a",
            "PNG",
            "--pango-markup",
            "--lower-limit=0",
            "--units-exponent=0",
            "-v Consumption %"
        ];

        foreach ($this->configuration['devices'] as $device => $path) {
            array_push($config, "DEF:{$device}TotalBytesUsed={$this->rrdFilePath[$device]}:BytesUsed:LAST");

            array_push($config, "DEF:{$device}AvgUtil={$this->rrdFilePath[$device]}:Util:AVERAGE");
            array_push($config, "DEF:{$device}MaxUtil={$this->rrdFilePath[$device]}:Util:MAX");
        }

        foreach ($this->configuration['devices'] as $device => $path) {
            $colour = array_shift($colours);
            $config = array_merge($config, [
                "LINE2:{$device}AvgUtil{$colour}:" . $device,
                "GPRINT:{$device}AvgUtil:LAST:   Current\\: %5.2lf%%",
                "GPRINT:{$device}AvgUtil:MIN:  Min\\: %5.2lf%%",
                "GPRINT:{$device}MaxUtil:MAX:  Max\\: %5.2lf%%",
                "GPRINT:{$device}AvgUtil:AVERAGE: Avg\\: %5.2lf%%",
                "GPRINT:{$device}TotalBytesUsed:LAST: Used\\: %5.2lf %sB\\n",
            ]);
        }

        array_push($config,
            'COMMENT:<span foreground="#ABABAB" size="x-small">' . date('D M jS H') . '\:' . date('i') . '\:' . date('s') . '</span>\r');

        if (!rrd_graph($graphPath . DIRECTORY_SEPARATOR . $this->getGraphName($period), $config)) {
            $this->fail('Error writing Disk Usage graph for period ' . $period . ' [' . rrd_error() . ']');
        }
    }
}

// $p = new RRDDiskUsage(__DIR__, true, ['nbd0' => '/dev/nbd0', 'nbd1' => '/dev/nbd1']);
// $p->collect();
// $p->graph('hour', __DIR__ . '/../httpdocs/img');
// $p->graph('day', __DIR__ . '/../httpdocs/img');
// $p->graph('week', __DIR__ . '/../httpdocs/img');
// $p->graph('month', __DIR__ . '/../httpdocs/img');
// $p->graph('year', __DIR__ . '/../httpdocs/img');
