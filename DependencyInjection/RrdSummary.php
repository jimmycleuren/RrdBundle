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
        if (!in_array($this->path . $filename, $this->files)) {
            $this->files[] = $this->path . $filename;
        }
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
            "--lower-limit=0",
        );

        foreach($this->files as $file) {
            foreach ($this->datasources as $datasource) {
                $info = rrd_lastupdate($file);
                if(!file_exists($file)) {
                    $exclude[] = md5($file . "-" . $datasource['name']);
                } elseif($info['data'][0] == 'U') {
                    $exclude[] = md5($file . "-" . $datasource['name']);
                } else {
                    //var_dump($file, rrd_fetch($file, array( "AVERAGE", "--resolution", "60", "--start", "-4h", "--end", "start+1h" ))['data']['up']);
                    //var_dump($file, rrd_lastupdate($file));
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

        $return = rrd_graph($imageFile, $options);
        if (!$return) {
            throw new RrdException(rrd_error());
        }

        return $imageFile;
    }

    public function getTotal($start, $end, $function = "average")
    {
        $options = array(
            "--start", $start,
            "--end", $end,
        );

        foreach ($this->files as $file) {
            foreach ($this->datasources as $datasource) {
                $options[] = sprintf(
                    "DEF:%s=%s:%s:%s",
                    "total_" . $datasource['name'] . "_" . md5($file),
                    $file,
                    $datasource['name'],
                    strtoupper($function)
                );
            }
        }

        $sources = array();
        $first = true;
        foreach($this->files as $key => $file) {
            foreach ($this->datasources as $datasource) {
                if ($first) {
                    $sources[] = "total_" . $datasource['name'] . "_" . md5($file);
                    $first = false;
                } else {
                    $sources[] = "total_" . $datasource['name'] . "_" . md5($file) . ",+";
                }
            }
        }
        if (count($sources) > 0) {
            $options[] = sprintf(
                "CDEF:total=%s,%s,*",
                implode(",", $sources),
                1
            );
        }

        $options[] = "VDEF:result=total,TOTAL";
        $options[] = "PRINT:result:%lf";

        //var_dump($options);

        $result = rrd_graph("/dev/null", $options);

        if(!$result) {
            throw new RrdException(rrd_error());
        }

        return $result['calcpr'][0];
    }

    public function getPercentile($start, $end, $percentile = 95, $function = "max")
    {
        $options = array(
            "--start", $start,
            "--end", $end,
        );

        foreach ($this->files as $file) {
            foreach ($this->datasources as $datasource) {
                $options[] = sprintf(
                    "DEF:%s=%s:%s:%s",
                    "total_" . $datasource['name'] . "_" . md5($file),
                    $file,
                    $datasource['name'],
                    strtoupper($function)
                );
            }
        }

        $sources = array();
        $first = true;
        foreach($this->files as $key => $file) {
            foreach ($this->datasources as $datasource) {
                if ($first) {
                    $sources[] = "total_" . $datasource['name'] . "_" . md5($file);
                    $first = false;
                } else {
                    $sources[] = "total_" . $datasource['name'] . "_" . md5($file) . ",+";
                }
            }
        }
        if (count($sources) > 0) {
            $options[] = sprintf(
                "CDEF:total=%s,%s,*",
                implode(",", $sources),
                1
            );
        }

        $options[] = "VDEF:result=total,$percentile,PERCENT";
        $options[] = "PRINT:result:%lf";

        $result = rrd_graph("/dev/null", $options);

        if(!$result) {
            throw new RrdException(rrd_error());
        }

        return $result['calcpr'][0];
    }

    public function getAverage($start, $end, $function = "average")
    {
        $options = array(
            "--start", $start,
            "--end", $end,
        );

        foreach ($this->files as $file) {
            foreach ($this->datasources as $datasource) {
                $options[] = sprintf(
                    "DEF:%s=%s:%s:%s",
                    "total_" . $datasource['name'] . "_" . md5($file),
                    $file,
                    $datasource['name'],
                    strtoupper($function)
                );
            }
        }

        $sources = array();
        $first = true;
        foreach($this->files as $key => $file) {
            foreach ($this->datasources as $datasource) {
                if ($first) {
                    $sources[] = "total_" . $datasource['name'] . "_" . md5($file);
                    $first = false;
                } else {
                    $sources[] = "total_" . $datasource['name'] . "_" . md5($file) . ",+";
                }
            }
        }
        if (count($sources) > 0) {
            $options[] = sprintf(
                "CDEF:total=%s,%s,*",
                implode(",", $sources),
                1
            );
        }

        $options[] = "VDEF:result=total,AVERAGE";
        $options[] = "PRINT:result:%lf";

        $result = rrd_graph("/dev/null", $options);

        if(!$result) {
            throw new RrdException(rrd_error());
        }

        return $result['calcpr'][0];
    }
}