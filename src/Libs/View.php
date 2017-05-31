<?php

namespace Carbontwelve\Monitor\Libs;

class View {

    /** @var string */
    private $viewPath;
    /**
     * @param $viewPath
     */
    public function __construct( $viewPath )
    {
        $this->viewPath = $viewPath;
    }
    /**
     * @return bool
     */
    public function exists()
    {
        return file_exists($this->viewPath);
    }
    /**
     * @param array $with
     * @return string
     * @throws \Exception
     */
    public function render( array $with = [] )
    {
        if ( ! $this->exists() ){ throw new \Exception('View file ['. $this->viewPath .'] could not be found.'); }
        ob_start();
        extract($with, EXTR_OVERWRITE);
        /** @noinspection PhpIncludeInspection */
        require( $this->viewPath );
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }
}