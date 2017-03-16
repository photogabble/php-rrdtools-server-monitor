<?php

abstract class RRDBase {

    protected $interval;
    protected $path;

    private $debug = false;

    public function __construct($path = __DIR__, $interval = 10, $debug = false)
    {
        if (!file_exists($path)) {
            echo "The path [$path] does not exist or is not readable.\n";
            exit(1);
        }
        $this->interval = $interval;
        $this->path = $path;
        $this->debug = $debug;
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
