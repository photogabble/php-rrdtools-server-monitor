<?php

namespace Carbontwelve\Monitor\Monitors;

abstract class RRDBase
{
    protected $rrdFileName = 'changeme.rrd';
    protected $graphName = 'changeme_%period%.png';
    protected $rrdFilePath;
    protected $path;
    private $debug = false;

    protected $configuration = [];

    protected function getGraphName ($period) {
        if (strpos($this->graphName, 'changeme') !== false) {
            echo 'Graph name needs defining for ['. get_class($this) .']' . PHP_EOL;
            exit(1);
        }

        return str_replace('%period%', $period, $this->graphName);
    }

    /**
     * @param null|string $key
     * @param null|mixed $default
     * @return mixed
     */
    public function getConfiguration($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->configuration;
        }

        if (!isset($this->configuration[$key])) {
            return $default;
        }

        return $this->configuration[$key];
    }

    public function setConfiguration(array $configuration)
    {
        $this->configuration = $configuration;
        return $this->configurationLoaded();
    }

    public function __construct($path = __DIR__, $debug = false)
    {
        if (!file_exists($path)) {
            echo "The path [$path] does not exist or is not readable.\n";
            exit(1);
        }
        $this->path = $path;
        $this->debug = $debug;
        $this->rrdFilePath = $this->path . DIRECTORY_SEPARATOR . $this->rrdFileName;
        //$this->touchGraph();
    }

    // Function to call after configuration is set
    protected function configurationLoaded() {
        return true;
    }

    abstract public function touchGraph();

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
