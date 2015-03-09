<?php

namespace JimmyCleuren\Bundle\RrdBundle\DependencyInjection;

use JimmyCleuren\Bundle\RrdBundle\Exception\RrdException;

class RrdManager
{
    private $container;
    private $path;
    private $types = array();

    public function __construct($container)
    {
        $this->container = $container;
        $this->path = $this->container->getParameter('jimmy_cleuren_rrd.path')."/";
        $this->types = $this->container->getParameter('jimmy_cleuren_rrd.types');

        if (!file_exists($this->path)) {
            mkdir($this->path);
        }
    }

    public function open($filename, $type, $create = true)
    {
        if (!isset($this->types[$type])) {
            throw new RrdException("Config for RRD type $type is not defined");
        }
        return new RrdDatabase($this->container, $this->path.$filename, $this->types[$type], $create);
    }

    public function createSummary()
    {
        return new RrdSummary($this->container, $this->path);
    }
}