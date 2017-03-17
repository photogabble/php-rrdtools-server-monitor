<?php

require_once(__DIR__.'/RRDBase.php');

class RRDDiskIO extends RRDBase {

    protected $rrdFileName = 'diskio.rrd';

    protected function touchGraph()
    {
        // ...
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

    public function collect()
    {
        // ...
    }

    public function graph($period = 'day', $graphPath = __DIR__)
    {
        if (!file_exists($graphPath)) {
            $this->fail("The path [$graphPath] does not exist or is not readable.\n");
        }
    }
}

$p = new RRDDiskIO(__DIR__, true);
$p->collect();
$p->graph('hour', __DIR__ . '/../httpdocs/img');
