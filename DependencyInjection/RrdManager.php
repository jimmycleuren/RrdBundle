<?php

namespace JimmyCleuren\Bundle\RrdBundle\DependencyInjection;

class RrdManager
{
    private $container;
    private $path;

    public function __construct($container)
    {
        $this->container = $container;
        $this->path = $this->container->getParameter('jimmy_cleuren_rrd.path')."/";
    }

    public function open($filename, $create = true)
    {
        return new RrdDatabase($this->path.$filename, $create);
    }
}