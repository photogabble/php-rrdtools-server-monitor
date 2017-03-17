<?php

require_once(__DIR__.'/RRDBase.php');

class RRDNetwork extends RRDBase {

    protected $rrdFileName = 'ipconfig.rrd';

    protected function touchGraph()
    {
        if (!file_exists($this->rrdFilePath)) {
            $this->debug("Creating [$this->rrdFilePath]\n");
            if (!rrd_create($this->rrdFilePath, [
                "-s",60,
                "DS:in:DERIVE:120:0:U",
                "DS:out:DERIVE:120:0:U",
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
        $tmpPathName = $this->path . DIRECTORY_SEPARATOR . "ipconfig.".time().".tmp";
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
        $trafficInBytes = $this->runCommand('ifconfig eth0 | grep bytes | cut -d":" -f2|cut -d" " -f1');
        $trafficOutBytes = $this->runCommand('ifconfig eth0 | grep bytes | cut -d":" -f3|cut -d" " -f1');

        $this->debug("Traffic in/out: $trafficInBytes/$trafficOutBytes bytes.\n");

        if (!rrd_update($this->rrdFilePath, [
            "-t",
            "in:out",
            "N:$trafficInBytes:$trafficOutBytes"
        ])){
            $this->fail(rrd_error());
        }
    }

    public function graph($period = 'day', $graphPath = __DIR__)
    {
        if (!file_exists($graphPath)) {
            $this->fail("The path [$graphPath] does not exist or is not readable.\n");
        }

        if(!rrd_graph($graphPath . '/network_' . $period . '.png', [
            "-s","-1$period",
            "-t eth0 traffic in the last $period",
            "--lazy",
            "-h", "150", "-w", "700",
            "-l 0",
            "-a", "PNG",
            "-v bytes/sec",
            "--slope-mode",
            "DEF:in={$this->rrdFilePath}:in:AVERAGE",
            "DEF:maxin={$this->rrdFilePath}:in:MAX",
            "DEF:out={$this->rrdFilePath}:out:AVERAGE",
            "DEF:maxout={$this->rrdFilePath}:out:MAX",

            "CDEF:out_neg=out,-1,*",
            "CDEF:maxout_neg=maxout,-1,*",
            "AREA:in#27AE60:Incoming",
            "LINE1:maxin#74525F",

            "GPRINT:in:MAX:  Max\\: %6.1lf %s",
            "GPRINT:in:AVERAGE: Avg\\: %6.1lf %S",
            "GPRINT:in:LAST: Current\\: %6.1lf %SBytes/sec\\n",

            "AREA:out_neg#2C3E50:Outgoing",
            "LINE1:maxout_neg#832D51",

            "GPRINT:maxout:MAX:  Max\\: %6.1lf %S",
            "GPRINT:out:AVERAGE: Avg\\: %6.1lf %S",
            "GPRINT:out:LAST: Current\\: %6.1lf %SBytes/sec\\n",

            "HRULE:0#000000"
        ])) {
            $this->fail('Error writing network graph for period '. $period  .' ['. rrd_error() .']');
        }
    }
}

$p = new RRDNetwork(__DIR__, true);
$p->collect();
$p->graph('hour', __DIR__ . '/../httpdocs/img');
$p->graph('day', __DIR__ . '/../httpdocs/img');
$p->graph('week', __DIR__ . '/../httpdocs/img');
$p->graph('month', __DIR__ . '/../httpdocs/img');
$p->graph('year', __DIR__ . '/../httpdocs/img');
