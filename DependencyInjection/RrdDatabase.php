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
        $this->createDataSources();

        if (!$create && !file_exists($this->filename)) {
            throw new RrdDatabaseNotFoundException($this->filename);
        }
        if (file_exists($this->filename)) {
            $this->check();
        } else {
            $this->create();
        }
    }

    private function createDataSources()
    {
        foreach ($this->config['datasources'] as $key => $value) {
            if(preg_match("/([\d]+)\.\.([\d]+)/", $key, $matches)) {
                for($i = $matches[1]; $i <= $matches[2]; $i++) {
                    $this->config['datasources'][$i] = $value;
                }
                unset($this->config['datasources'][$key]);
            }
        }
    }

    private function create()
    {
        $this->container->get('logger')->info("Creating RRD file ".$this->filename);
        $this->createPath();

        $start = date("U") - 1;

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
                throw new RrdException(sprintf(
                    "Type for datasource '%s' is not equal (%s vs %s)",
                    $key,
                    $info[$id],
                    strtoupper($value['type'])
                ));
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

    public function graph($title, $start, $datasources = array())
    {
        $imageFile = tempnam($this->container->getParameter('tmp_folder'), 'image');
        $options = array(
            //"--slope-mode",
            "--start", $start,
            "--title=$title",
            //"--vertical-label=User login attempts",
            "--lower=0",
        );

        foreach ($this->config['datasources'] as $key => $value) {
            if(count($datasources) == 0 || in_array($key, $datasources)) {
                $options[] = sprintf(
                    "DEF:%s=%s:%s:%s",
                    $key,
                    $this->filename,
                    $key,
                    strtoupper($value['graph_function'])
                );
            }
        }
        foreach ($this->config['datasources'] as $key => $value) {
            if(count($datasources) == 0 || in_array($key, $datasources)) {
                $options[] = sprintf(
                    "%s:%s%s:%s",
                    strtoupper($value['graph_type']),
                    $key,
                    $value['graph_color'],
                    $value['graph_legend']
                );
                $options[] = sprintf(
                    "GPRINT:%s:%s:%s",
                    $key,
                    strtoupper($value['graph_function']),
                    "cur\:%6.2lf"
                );
                $options[] = "COMMENT:\\n";
            }
        }

        $return = rrd_graph($imageFile, $options);
        $error = rrd_error();
        if (!$return || $error) {
            throw new RrdException($error);
        }

        return $imageFile;
    }

    public function close()
    {

    }

    public function getTotal($datasource, $start, $end, $function = "average")
    {
        if(!isset($this->config['datasources'][$datasource])) {
            throw new RrdException("Datasource $datasource not found");
        }

        $result = rrd_graph("/dev/null", array(
            "--start", $start,
            "--end", $end,
            "DEF:total=$this->filename:$datasource:" . strtoupper($function),
            "VDEF:result=total,TOTAL",
            "PRINT:result:%lf"
        ) );

        if(!$result) {
            throw new RrdException(rrd_error());
        }

        return $result['calcpr'][0];
    }

    public function getPercentile($datasource, $start, $end, $percentile = 95, $function = "max")
    {
        if(!isset($this->config['datasources'][$datasource])) {
            throw new RrdException("Datasource $datasource not found");
        }

        $result = rrd_graph("/dev/null", array(
            "--start", $start,
            "--end", $end,
            "DEF:total=$this->filename:$datasource:" . strtoupper($function),
            "VDEF:result=total,$percentile,PERCENT",
            "PRINT:result:%lf"
        ) );

        if(!$result) {
            throw new RrdException(rrd_error());
        }

        return $result['calcpr'][0];
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