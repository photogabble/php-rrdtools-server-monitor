<?php

abstract class RRDBase {

    protected $rrdFileName = 'changeme.rrd';
    protected $rrdFilePath;
    protected $path;
    private $debug = false;

    public function __construct($path = __DIR__, $debug = false)
    {
        if (!file_exists($path)) {
            echo "The path [$path] does not exist or is not readable.\n";
            exit(1);
        }
        $this->path = $path;
        $this->debug = $debug;
        $this->rrdFilePath = $this->path . DIRECTORY_SEPARATOR . $this->rrdFileName;
        $this->touchGraph();
    }

    abstract protected function touchGraph();

    abstract public function collect();

    abstract public function graph($period = 'day', $graphPath = __DIR__);

    protected function debug($text)
    {
        if ($this->debug === true) {
            echo $text;
        }
    }

    protected function fail($text, $code = 1)
    {
        echo "[!] $text\n";
        exit($code);
    }
}
