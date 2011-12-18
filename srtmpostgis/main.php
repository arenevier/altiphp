<?php
/*  This file is part of altiphp, Copyright (c) 2011 Arnaud Renevier, and is
    published under the modifier BSD license. */

namespace alti;

@include_once("Zend/Loader/Autoloader.php");

class SrtmpostgisDataSource implements DataSource {
    /**
     * size of a tile
     */
    const tilesize = 1200;

    /**
     * @var resource $dbconn PostgreSQL connection
     */
    protected $dbconn = array();

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct(array $options) {
        if (!extension_loaded('pgsql')) {
            throw new \Exception("pgsql extension not availabled");
        }

        $connection_string = "";
        if (isset($options["dbhost"])) {
            $connection_string .= "host='" . str_replace("'", "\\'", (string)$options["dbhost"]) . "' ";
        } else {
            $connection_string .= "host='localhost' ";
        }
        if (isset($options["dbport"])) {
            $connection_string .= "port='" . str_replace("'", "\\'", (string)$options["dbport"]) . "' ";
        }
        if (isset($options["dbsslmode"])) {
            $connection_string .= "sslmode='" . str_replace("'", "\\'", (string)$options["dbsslmode"]) . "' ";
        } else {
        }

        if (isset($options["dbname"])) {
            $connection_string .= "dbname='" . str_replace("'", "\\'", (string)$options["dbname"]) . "' ";
        } else {
            $connection_string .= "dbname='srtm' ";
        }
        if (isset($options["dbuser"])) {
            $connection_string .= "user='" . str_replace("'", "\\'", (string)$options["dbuser"]) . "' ";
        }
        if (isset($options["dbpassword"])) {
            $connection_string .= "password='" . str_replace("'", "\\'", (string)$options["dbpassword"]) . "' ";
        }

        // XXX: PGSQL_CONNECT_FORCE_NEW because we will create a statement for it
        if (!($this->dbconn = @pg_connect ($connection_string, PGSQL_CONNECT_FORCE_NEW))) {
            throw new \Exception("could not connect to database");
        }

        // Prepare a query for execution
        if (!(@pg_prepare($this->dbconn, "altitude_stmt", 'SELECT alt FROM altitude WHERE pos = $1'))) {
            throw new \Exception("could not create statement");
        }
    }

    /**
     * get altitude from database. This where sql request happens.
     * Returns altitude or null if data is not available.
     *
     * @param int $pos position in table
     * @return int|null
     */
    protected function dbAltitude($pos) {
        print $pos . "\n";
        if (!($result = @pg_execute($this->dbconn, "altitude_stmt", array((int)($pos))))) {
            throw new \Exception("could not execute query");
        }
        $arr = pg_fetch_row($result);
        $tl = $arr[0];
        if (is_null($tl) or $tl === "32768" or $tl === "-32768") {
            return NULL;
        }
        return (int)$tl;
    }

    /**
     * return altitude of a point
     *
     * @param float $lon point longitude
     * @param float $lat point latitude
     * @return float
     */
    public function altitude ($lon, $lat) {
        $dx = $lon - floor($lon);
        $dy = floor($lat + 1) - $lat;

        $offset = (floor($lat) * 360 + floor($lon)) * self::tilesize * self::tilesize;

        $left = floor($dx * self::tilesize);
        $right = $left + 1;
        $top = floor($dy * self::tilesize) -1;
        $bottom = $top + 1;

        if (is_null($tl = $this->dbAltitude($offset + $top  * self::tilesize + $left))) {
            return NULL;
        }

        if (is_null($tr = $this->dbAltitude($offset + $top  * self::tilesize + $right))) {
            return NULL;
        }

        if (is_null($bl = $this->dbAltitude($offset + $bottom  * self::tilesize + $left))) {
            return NULL;
        }

        if (is_null($br = $this->dbAltitude($offset + $bottom  * self::tilesize + $right))) {
            return NULL;
        }

        $a = $dy * self::tilesize - $top - 1;
        $b = $dx * self::tilesize - $left;

        return $tl + ($bl - $tl) * $a + ($tr - $tl) * $b + ($tl - $bl - $tr + $br) * $a * $b;
    }

    /**
     * return data precision in meters
     *
     * @return float
     */
    public function getPrecision() {
        return 90.0;
    }

    /**
     * return TRUE if geometry or point is totally covered.
     *
     * @param float $lon point longitude
     * @param float $lat point latitude
     * @return bool
     */
    public function isCovered($lon, $lat) {
        $offset = (floor($lat) * 360 + floor($lon)) * self::tilesize * self::tilesize;

        if (!($result = pg_execute($this->dbconn, "altitude_stmt", array((int)($offset))))) {
            throw new \Exception("could not execute query");
        }
        $arr = pg_fetch_row($result);
        $tl = $arr[0];
        return !is_null($tl);
    }

}
