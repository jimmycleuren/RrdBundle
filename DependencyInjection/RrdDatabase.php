<?php

namespace JimmyCleuren\Bundle\RrdBundle\DependencyInjection;

use JimmyCleuren\Bundle\RrdBundle\Exception\RrdDatabaseNotFoundException;
use JimmyCleuren\Bundle\RrdBundle\Exception\RrdException;

class RrdDatabase
{
    private $container = null;
    private $filename = null;
    private $config = null;

    public function __construct($container, $filename, $config, $create)
    {
        $this->container = $container;
        $this->filename = $filename;
        $this->config = $config;

        if (!$create && !file_exists($this->filename)) {
            throw new RrdDatabaseNotFoundException($this->filename);
        }
        if (file_exists($this->filename)) {
            $this->check();
        } else {
            $this->create();
        }
    }

    private function create()
    {
        $this->container->get('logger')->info("Creating RRD file ".$this->filename);
        $this->createPath();

        $start = date("U") - $this->config['step'];

        $options = array(
            "--start", $start,
            "--step", $this->config['step']
        );
        foreach ($this->config['datasources'] as $key => $value) {
            $options[] = sprintf(
                "DS:%s:%s:%s:%s:%s",
                $key,
                strtoupper($value['type']),
                $value['heartbeat'],
                $value['lower_limit'],
                $value['upper_limit']
            );
        }
        foreach ($this->config['archives'] as $value) {
            $options[] = sprintf(
                "RRA:%s:0.5:%s:%s",
                strtoupper($value['function']),
                $value['steps'],
                $value['rows']
            );
        }

        $return = rrd_create($this->filename, $options);
        if (!$return) {
            throw new RrdException(rrd_error());
        }
    }

    private function check()
    {
        $info = rrd_info($this->filename);

        if($info['step'] != $this->config['step']) {
            throw new RrdException("Steps are not equal, " . $this->config['step'] . " is configured, RRD file is using ".$info['step']);
        }

        foreach ($this->config['datasources'] as $key => $value) {
            $id = "ds[$key].type";
            if (!isset($info[$id]) || $info[$id] != strtoupper($value['type'])) {
                throw new RrdException("Type for datasource '$key'' is not equal");
            }
        }
    }

    public function update($timestamp, $data)
    {
        $template = array();
        $values = array($timestamp);

        foreach($data as $key => $value) {
            $template[] = $key;
            $values[] = $value;
        }
        $options = array("-t", implode(":", $template), implode(":", $values));

        $return = rrd_update($this->filename, $options);
        if (!$return) {
            throw new RrdException(rrd_error());
        }
    }

    public function graph($title, $start)
    {
        $imageFile = tempnam($this->container->getParameter('tmp_folder'), 'image');
        $options = array(
            "--slope-mode",
            "--start", $start,
            "--title=$title",
            //"--vertical-label=User login attempts",
            "--lower=0",
        );

        foreach ($this->config['datasources'] as $key => $value) {
            $options[] = sprintf(
                "DEF:%s=%s:%s:%s",
                $key,
                $this->filename,
                $key,
                strtoupper($value['graph_function'])
            );
        }
        foreach ($this->config['datasources'] as $key => $value) {
            $options[] = sprintf(
                "CDEF:data_%s=%s,%s,*",
                $key,
                $key,
                $this->config['step']
            );
        }
        foreach ($this->config['datasources'] as $key => $value) {
            $options[] = sprintf(
                "%s:data_%s%s:%s",
                strtoupper($value['graph_type']),
                $key,
                $value['graph_color'],
                $value['graph_legend']
            );
            $options[] = sprintf(
                "GPRINT:data_%s:%s:%s",
                $key,
                strtoupper($value['graph_function']),
                "cur\:%6.2lf"
            );
            $options[] = "COMMENT:\\n";
        }

        $return = rrd_graph($imageFile, $options);
        if (!$return) {
            throw new RrdException(rrd_error());
        }

        return $imageFile;
    }

    public function close()
    {

    }

    private function createPath()
    {
        $directories = explode( '/', $this->filename );
        array_pop( $directories );
        $base = '';

        foreach( $directories as $dir )
        {
            if($dir) {
                $path = sprintf('%s/%s', $base, $dir);
                if (!file_exists($path)) {
                    mkdir($path);
                }
                $base = $path;
            }
        }
    }
}