<?php

namespace JimmyCleuren\Bundle\RrdBundle\DependencyInjection;

use JimmyCleuren\Bundle\RrdBundle\Exception\RrdDatabaseNotFoundException;

class RrdDatabase
{
    private $filename = null;

    public function __construct($filename, $create)
    {
        $this->filename = $filename;
        if (!$create && !file_exists($this->filename)) {
            throw new RrdDatabaseNotFoundException($this->filename);
        }
        if (file_exists($this->filename)) {
            $info = rrd_info($this->filename);
        } else {
            $this->create();
        }
    }

    private function create()
    {
        $start = date("U");
        rrd_create($this->filename, array("--start", $start));
    }

    public function update($timestamp, $data)
    {

    }

    public function close()
    {

    }
}