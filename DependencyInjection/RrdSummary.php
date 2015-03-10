<?php

namespace JimmyCleuren\Bundle\RrdBundle\DependencyInjection;

use JimmyCleuren\Bundle\RrdBundle\Exception\RrdDatabaseNotFoundException;
use JimmyCleuren\Bundle\RrdBundle\Exception\RrdException;

class RrdSummary
{
    private $container = null;
    private $path = null;
    private $files = array();
    private $datasources = array();

    public function __construct($container, $path)
    {
        $this->container = $container;
        $this->path = $path;
    }

    public function addFile($filename)
    {
        $this->files[] = $this->path . $filename;
    }

    public function addDatasource($name, $function, $type, $color, $legend)
    {
        $this->datasources[] = array('name' => $name, 'function' => $function, 'type' => $type, 'color' => $color, 'legend' => $legend);
        return $this;
    }

    public function graph($title, $start)
    {
        $exclude = array();
        $imageFile = tempnam($this->container->getParameter('tmp_folder'), 'image');
        $options = array(
            "--slope-mode",
            "--start", $start,
            "--title=$title",
            //"--vertical-label=User login attempts",
            "--lower=0",
        );

        foreach($this->files as $file) {
            foreach ($this->datasources as $datasource) {
                if(!file_exists($file)) {
                    $exclude[] = md5($file . "-" . $datasource['name']);
                } else {
                    $options[] = sprintf(
                        "DEF:%s=%s:%s:%s",
                        md5($file . "-" . $datasource['name']),
                        $file,
                        $datasource['name'],
                        strtoupper($datasource['function'])
                    );
                }
            }
        }

        foreach ($this->datasources as $datasource) {
            $sources = array();
            $first = true;
            foreach($this->files as $key => $file) {
                $id = md5($file . "-" . $datasource['name']);
                if(!in_array($id, $exclude)) {
                    if ($first) {
                        $sources[] = $id;
                        $first = false;
                    } else {
                        $sources[] = $id . ",+";
                    }
                }
            }
            if (count($sources) > 0) {
                $options[] = sprintf(
                    "CDEF:data_%s=%s,%s,*",
                    $datasource['name'],
                    implode(",", $sources),
                    1
                );
            }
        }

        foreach ($this->datasources as $datasource) {
            $options[] = sprintf(
                "%s:data_%s%s:%s",
                strtoupper($datasource['type']),
                $datasource['name'],
                $datasource['color'],
                $datasource['legend']
            );
            $options[] = sprintf(
                "GPRINT:data_%s:%s:%s",
                $datasource['name'],
                strtoupper($datasource['function']),
                "cur\:%6.2lf"
            );
            $options[] = "COMMENT:\\n";
        }

        var_dump($options);
        $return = rrd_graph($imageFile, $options);
        if (!$return) {
            throw new RrdException(rrd_error());
        }

        return $imageFile;
    }
}