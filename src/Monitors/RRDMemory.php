<?php

namespace Carbontwelve\Monitor\Monitors;

class RRDMemory extends RRDBase {

    protected $rrdFileName = 'meminfo.rrd';

    public function touchGraph()
    {
        if (!file_exists($this->rrdFilePath)) {
            $this->debug("Creating [$this->rrdFilePath]\n");
            if (!rrd_create($this->rrdFilePath, [
                "-s",60,
                "DS:memTotal:GAUGE:120:0:U",
                "DS:memUsed:GAUGE:120:0:U",
                "DS:memFree:GAUGE:120:0:U",
                "DS:memBuffCache:GAUGE:120:0:U",
                "DS:memBuff:GAUGE:120:0:U",
                "DS:memCache:GAUGE:120:0:U",
                "DS:memAvailable:GAUGE:120:0:U",
                "DS:swapTotal:GAUGE:120:0:U",
                "DS:swapUsed:GAUGE:120:0:U",
                "DS:swapFree:GAUGE:120:0:U",

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
            $this->debug("Created [$this->rrdFilePath]\n");
        }
    }

    private function runCommand($cmd)
    {
        $tmpPathName = $this->path . DIRECTORY_SEPARATOR . "meminfo.".time().".tmp";
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

    public function collect()
    {

        $memTotal = 0;
        $memFree = 0;
        $memBuffers = 0;
        $memCached = 0;
        $memAvailable = 0;

        $swapTotal = 0;
        $swapFree = 0;

        // Usefull explanaction here:
        // @see https://access.redhat.com/solutions/406773

        $stat = $this->runCommand('cat /proc/meminfo');
        foreach (explode("\n", $stat) as $row) {
            if (preg_match('/^MemFree:\s+(\d+)/', $row, $matches) === 1) {
                $memFree = $matches[1];
            }
            if (preg_match('/^MemTotal:\s+(\d+)/', $row, $matches) === 1) {
                $memTotal = $matches[1];
            }
            if (preg_match('/^Buffers:\s+(\d+)/', $row, $matches) === 1) {
                $memBuffers = $matches[1];
            }
            if (preg_match('/^Cached:\s+(\d+)/', $row, $matches) === 1) {
                $memCached = $matches[1];
            }
            if (preg_match('/^MemAvailable:\s+(\d+)/', $row, $matches) === 1) {
                $memAvailable = $matches[1];
            }
            if (preg_match('/^SwapTotal:\s+(\d+)/', $row, $matches) === 1) {
                $swapTotal = $matches[1];
            }
            if (preg_match('/^SwapFree:\s+(\d+)/', $row, $matches) === 1) {
                $swapFree = $matches[1];
            }
        }

        // Store all values as bytes and let RRDTool's sort out the KB, MB, etc...
        $memTotal = $memTotal * 1024;
        $memFree = $memFree * 1024;
        $memBuffers = $memBuffers * 1024;
        $memCached = $memCached * 1024;
        $memAvailable = $memAvailable * 1024;

        $memBufferCache = $memCached + $memBuffers;

        $memUsed = $memTotal - ($memFree + $memBufferCache);
        $swapUsed = $swapTotal - $swapFree;

        $this->debug("\ttotal\t\tused\t\tfree\t\tbuff/cache\tavailable\n");
        $this->debug("Mem:\t$memTotal\t$memUsed\t$memFree\t$memBufferCache\t$memAvailable\n");
        $this->debug("Swap:\t$swapTotal\t\t$swapUsed\t\t$swapFree\n");

        if (!rrd_update($this->rrdFilePath, [
            "-t",
            "memTotal:memUsed:memFree:memBuffCache:memAvailable:swapTotal:swapUsed:swapFree:memBuff:memCache",
            "N:$memTotal:$memUsed:$memFree:$memBufferCache:$memAvailable:$swapTotal:$swapUsed:$swapFree:$memBuffers:$memCached"
        ])){
            $this->fail(rrd_error());
        }
    }

    public function graph($period = 'day', $graphPath = __DIR__)
    {
        if (!file_exists($graphPath)) {
            $this->fail("The path [$graphPath] does not exist or is not readable.\n");
        }

        // Overall Memory Usage Graph
        if(!rrd_graph($graphPath . '/memory_usage_' . $period . '.png', [
            "-s","-1$period",
            "-t Memory usage in the last $period",
            "-z",
            "--lazy",
            "-h", "150", "-w", "700",
            "-l 0",
            "-a", "PNG",
            "-v RAM (bytes)",
            "--pango-markup",

            "DEF:total={$this->rrdFilePath}:memTotal:AVERAGE",

            "DEF:available={$this->rrdFilePath}:memAvailable:AVERAGE",

            "DEF:used={$this->rrdFilePath}:memUsed:AVERAGE",
            "DEF:maxUsage={$this->rrdFilePath}:memUsed:MAX",

            "DEF:free={$this->rrdFilePath}:memFree:AVERAGE",
            "DEF:maxFree={$this->rrdFilePath}:memFree:MAX",

            "DEF:buffers={$this->rrdFilePath}:memBuff:AVERAGE",
            "DEF:maxBuffers={$this->rrdFilePath}:memBuff:MAX",

            "DEF:cache={$this->rrdFilePath}:memCache:AVERAGE",
            "DEF:maxCache={$this->rrdFilePath}:memCache:MAX",

            'COMMENT: \l',
            "COMMENT:               ",
            "COMMENT:Current    ",
            "COMMENT:Minimum    ",
            "COMMENT:Maximum    ",
            'COMMENT:Average    \l',
            "COMMENT:   ",

            "AREA:used#832D51:Used   :STACK",
            "GPRINT:used:LAST:%5.1lf %sB   ",
            "GPRINT:used:MIN:%5.1lf %sB   ",
            "GPRINT:maxUsage:MAX:%5.1lf %sB   ",
            'GPRINT:used:AVERAGE:%5.1lf %sB   \l',

            "COMMENT:   ",

            "AREA:buffers#E84B3A:Buffers:STACK",
            "GPRINT:buffers:LAST:%5.1lf %sB   ",
            "GPRINT:buffers:MIN:%5.1lf %sB   ",
            "GPRINT:maxBuffers:MAX:%5.1lf %sB   ",
            'GPRINT:buffers:AVERAGE:%5.1lf %sB   \l',

            "COMMENT:   ",

            "AREA:cache#F8C82D:Cache:STACK",
            "GPRINT:cache:LAST:%5.1lf %sB   ",
            "GPRINT:cache:MIN:%5.1lf %sB   ",
            "GPRINT:maxCache:MAX:%5.1lf %sB   ",
            'GPRINT:cache:AVERAGE:%5.1lf %sB   \l',

            "COMMENT:   ",

            "AREA:free#6EC198:Free :STACK",
            "GPRINT:free:LAST:%5.1lf %sB   ",
            "GPRINT:free:MIN:%5.1lf %sB   ",
            "GPRINT:maxFree:MAX:%5.1lf %sB   ",
            'GPRINT:free:AVERAGE:%5.1lf %sB   \l',

            "COMMENT:   ",

            "LINE1:total#21392D:Total",
            'GPRINT:total:LAST:%5.1lf %sB   \l',

            "COMMENT:   ",

            "LINE1:available#0EAD9A:Available",
            'GPRINT:available:LAST:%5.1lf %sB   \l',

            'COMMENT:<span foreground="#ABABAB" size="x-small">'. date('D M jS H') . '\:' . date('i') . '\:' . date('s') .'</span>\r'

        ])) {
            $this->fail('Error writing connections graph for period '. $period  .' ['. rrd_error() .']');
        }
    }
}

// $p = new RRDMemory(__DIR__, true);
// $p->collect();
// $p->graph('hour', __DIR__ . '/../httpdocs/img');
// $p->graph('day', __DIR__ . '/../httpdocs/img');
// $p->graph('week', __DIR__ . '/../httpdocs/img');
// $p->graph('month', __DIR__ . '/../httpdocs/img');
// $p->graph('year', __DIR__ . '/../httpdocs/img');
