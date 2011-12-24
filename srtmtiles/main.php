<?php
/*  This file is part of altiphp, Copyright (c) 2011 Arnaud Renevier, and is
    published under the modifier BSD license. */

namespace alti;

@include_once("Zend/Loader/Autoloader.php");

class SrtmtilesDataSource implements DataSource {
    /**
     * download tiles url
     */
    const BASEURL = 'http://dds.cr.usgs.gov/srtm/version2_1/SRTM3/';

    /**
     * @var array loaded from srtmdata.php: informations about tiles to download.
     */
    protected $srtmData = array();

    /**
     * @var string directory where tiles are downloaded or stored
     */
    protected $datadir = "";

    /**
     * @var string temporary directory. Will be deleted in destructor
     */
    protected $tmpdir = null;

    /**
     * @var string http backend. Either "curl" or "zend"
     */
    protected $httpbackend = null;

    /**
     * @var array holding tiles data in memory
     */
    protected $tiles = array();

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct(array $options = array()) {
        if (!extension_loaded('zip')) {
            throw new \Exception("zip extension not availabled");
        }

        /* poor man's mktemp -d: call tempnam to get a non existent path,
           remove the file, and create a directory with this file */
        if (!($tmpdir = @tempnam("", ""))) {
            throw new \Exception("could not create temporary file");
        }
        @unlink($tmpdir);
        if (!@mkdir($tmpdir)) {
            throw new \Exception("could not create temporary directory");
        }
        $this->tmpdir = $tmpdir;

        $this->setOptions($options);
    }

    /**
     * Destructor
     *
     * deletes temporary directory
     */
    public function __destruct() {
        @$this->rmdir($this->tmpdir);
    }

    /**
     * Recursively removes directory
     *
     * @param string $dir directory to remove
     * @return void
     */
    protected function rmdir($dir) {
        if (is_dir($dir)) {
            foreach (scandir($dir) as $file) {
                if ($file == "." or $file == "..") {
                    continue;
                }
                $target = $dir . "/" . $file;
                if (is_dir($target)) {
                    $this->rmdir($target);
                } else {
                    unlink($target);
                }
            }
            rmdir ($dir);
        }
    }

    /**
     * set data directory and http backend options
     *
     * @param array $options
     * @return void
     */
    protected function setOptions(array $options = array()) {
        $this->setDataDir(isset($options['cache'])? $options['cache']: null);
        $this->setHttpBackend(isset($options['httpbackend'])? $options['httpbackend']: null);
    }

    /**
     * set data directory. If datadir is null, a temporary directory will be
     * created.
     *
     * @param string|null $datadir
     * @return void
     */
    protected function setDataDir($datadir = null) {
        if ($datadir) {
            $this->datadir = $datadir;
            if (!is_dir($this->datadir)) {
                throw new \Exception((string)$this->datadir . " is no a directory");
            }
        } else {
            $this->datadir = $this->tmpdir;
        }

    }

    /**
     * set http backend. If backend is null, will try to pick one available.
     *
     * @param string|null $backend
     * @return void
     */
    protected function setHttpBackend($backend = null) {
        $backend = strtolower((string)$backend);
        if ($backend) {
            if ($this->hasHttpBackend($backend)) {
                $this->httpbackend = $backend;
            } else {
                throw new \Exception($backend . " backend not available");
            }
        } else {
            if ($this->hasHttpBackend('curl')) {
                $this->httpbackend = 'curl';
            } else if ($this->hasHttpBackend('zend')) {
                $this->httpbackend = 'zend';
            } else {
                throw new \Exception("no http backend available");
            }
        }
    }

    /**
     * check if a http backend is available.
     *
     * @param string $backend
     * @return bool
     */
    protected function hasHttpBackend($backend) {
        if ($backend === 'curl') {
            return extension_loaded('curl');
        }
        if ($backend === 'zend') {
            return class_exists('Zend_Loader_Autoloader');
        }
        return FALSE;
    }

    /**
     * get download data for a given area
     *
     * @param string $area
     * @return array
     */
    protected function dataFor($area) {
        if (!$this->srtmData) {
            include realpath(__DIR__ . "/srtmdata.php");
            $this->srtmData = $srtmData;
        }
        if (!(isset($this->srtmData[$area]))) {
            return null;
        }
        return $this->srtmData[$area];
    }

    /**
     * get tile filename for a given area
     *
     * @param string $area
     * @return string
     */
    protected function fileFor($area) {
        $target = $this->datadir . '/' . $area . '.hgt';
        if (file_exists($target)) {
            return $target;
        }

        // XXX: if there is no data, still create an empty file to avoid reading big
        // data array next time
        $data = $this->dataFor($area);
        if (!$data) {
            @touch($target);
            return $target;
        }
        $download = $this->download(self::BASEURL . $data['path'], $data['md5']);
        if (!$download) {
            return null;
        }
        $this->unzip($download, $target);
        return $target;
    }

    /**
     * download a file. If md5sum is set, check that downloaded file md5sum
     * matches.
     *
     * @param string $url url to download
     * @param string $md5sum expected md5sum of file content
     * @return string full path of downloaded tile
     */
    protected function download($url, $md5sum = "") {
        $target = $this->tmpdir . "/" . basename($url);

        if (($fh = @fopen($target, 'w')) === FALSE) {
            throw new \Exception("could not open " . $target . " in write mode");
        }
        $method = 'download' . ucfirst($this->httpbackend);
        if (!$this->$method($url, $fh)) {
            @fclose($fh);
            return null;
        }
        @fclose($fh);

        if ($md5sum) {
            if (md5_file($target) !== "$md5sum") {
                throw new \Exception ("invalid checksum for " . basename($target));
            }
        }
        return $target;
    }

    /**
     * download a file with curl.
     *
     * @param string $url url to download
     * @param resource $fh file handler where to put download content
     * @return bool TRUE if success, FALSE otherwise
     */
    protected function downloadCurl($url, $fh) {
        if (($ch = @curl_init()) === FALSE) {
            return FALSE;
        }

        if (@curl_setopt($ch, CURLOPT_URL, $url) === FALSE) {
            @curl_close($ch);
            return FALSE;
        }

        if (@curl_setopt($ch, CURLOPT_FILE, $fh) === FALSE) {
            @curl_close($ch);
            return FALSE;
        }

        if (($response = @curl_exec($ch)) === FALSE) {
            @curl_close($ch);
            return FALSE;
        }

        @curl_close($ch);
        return TRUE;
    }

    /**
     * download a file with Zend_Http_Client.
     *
     * @param string $url url to download
     * @param resource $fh file handler where to put download content
     * @return bool TRUE if success, FALSE otherwise
     */
    protected function downloadZend($url, $fh) {
        \Zend_Loader_Autoloader::getInstance();
        $client = new \Zend_Http_Client($url);
        $response = $client->request();
        if (!@fwrite($fh, $response->getBody())) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * unzip a tile
     *
     * @param string $file path of zipped file
     * @path string $target path to extract the file
     */
    protected function unzip($file, $target) {
        $za = new \ZipArchive();
        if (@$za->open($file) === FALSE) {
            throw new \Exception ("invalid zip file " . $file);
        }
        if ($za->numFiles != 1) {
            throw new \Exception ("invalid zip file " . $file);
        }

        if (($archive = $za->getNameIndex(0)) === FALSE) {
            throw new \Exception ("invalid zip file " . $file);
        }
        if (@$za->extractTo($this->tmpdir, $archive) === FALSE) {
            throw new \Exception ("could not extract archive " . $archive);
        }

        # work around php bug #54485
        $stat = $za->statName($archive);
        if ($stat && (filesize($this->tmpdir . '/' . $archive) < $stat['size'])) {
            $za->close();
            throw new \Exception ("could not extract archive " . $archive);
        }
        @$za->close();
        @rename($this->tmpdir . '/' . $archive, $target);
    }

    /**
     * get tile data for a given area
     *
     * @param string $area
     * @return SrtmTile
     */
    protected function getTileFor($area) {
        if (isset($this->tiles[$area])) {
            return $this->tiles[$area];
        }
        $archive = $this->fileFor($area);
        if (!$archive or filesize($archive) === 0) {
            return null;
        }

        $this->tiles[$area] = new SrtmTile($archive);
        return $this->tiles[$area];
    }

    /** 
     * construct area string for $lon and $lat. Area refer to the geometric
     * center of the lower left pixel of srtm tile.
     * 
     * @param float $lon point longitude
     * @param float $lat point latitude
     * @return string
     */
    protected function constructArea($lon, $lat) {
        if ($lat >= 0) {
            $res = sprintf("N%02d", $lat);
        } else {
            $res = sprintf("S%02d", abs($lat) + 1);
        }
        if ($lon >= 0) {
            $res .= sprintf("E%03d", $lon);
        } else {
            $res .= sprintf("W%03d", abs($lon) + 1);
        }
        return $res;
    }

    /**
     * return altitude of a point
     *
     * @param float $lon point longitude
     * @param float $lat point latitude
     * @return float
     */
    public function altitude ($lon, $lat) {
        $area = $this->constructArea($lon, $lat);
        $tile = $this->getTileFor($area);
        if (!$tile) {
            return null;
        }

        $dx = $lon - floor($lon);
        $dy = floor($lat + 1) - $lat;

        return $tile->compute($dx, $dy);
    }

    /**
     * return TRUE if geometry or point is totally covered by data source.

     * @param float $lon point longitude
     * @param float $lat point latitude
     * @return bool
     */
    public function isCovered($lon, $lat) {
        $area = $this->constructArea($lon, $lat);
        $target = $this->datadir . '/' . $area . '.hgt';
        if (file_exists($target)) {
            return filesize($target) != 0;
        }
        $data = $this->dataFor($area);
        if ($data) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * return srtm precision in meters
     * 
     * @return float
     */
    public function getPrecision() {
        return 90.0;
    }
}

class SrtmTile {
    /**
     * size of array data
     */
    const size = 1200;

    /**
     * @var array tile data
     */
    protected $data = null;

    /**
     * Constructor
     *
     * @param string $file uncompressed data file
     */
    public function __construct($file) {
        if (filesize($file) == 0) {
            throw new \Exception ("invalid SRTM data " . $file);
        }

        $size = (int)sqrt(filesize($file)/2) - 1;
        if ($size !== self::size) {
            throw new \Exception ("invalid SRTM data " . $file);
        }

        if (($fh = @fopen($file, 'r+b')) === FALSE) {
            throw new \Exception("could not open " . $file . " file");
        }

        $this->data = $fh;
    }

    /**
     * Destructor
     *
     * @param string $file uncompressed data file
     */
    public function __destruct() {
        if ($this->data) {
            fclose($this->data);
        }
    }

    /**
     * compute altitude of a point at given offest
     *
     * @param float $dx horizontal offset
     * @param float $dy vertical offset
     * @return float
     */
    public function compute($dx, $dy) {
        $left = floor($dx * self::size);
        $right = $left + 1;
        $top = ceil($dy * self::size) - 1;
        $bottom = $top + 1;

        $tl = $this->dataAtOffset($top * (self::size + 1) + $left + 1); // f01
        if($tl >= pow(2, 15)) {
            $tl -= pow(2, 16); 
        }
        if ($tl === 32768 or $tl === -32768) {
            return null;
        }

        $tr = $this->dataAtOffset($top * (self::size + 1) + $right + 1); // f11
        if($tr >= pow(2, 15)) {
            $tr -= pow(2, 16); 
        }
        if ($tr === 32768 or $tr === -32768) {
            return null;
        }

        $bl = $this->dataAtOffset($bottom * (self::size + 1) + $left + 1); // f00
        if ($bl === 32768 or $bl === -32768) {
            return null;
        }
        if($bl >= pow(2, 15)) {
            $bl -= pow(2, 16); 
        }

        $br = $this->dataAtOffset($bottom * (self::size + 1) + $right + 1); // f10
        if ($br === 32768 or $br === -32768) {
            return null;
        }
        if($br >= pow(2, 15)) {
            $br -= pow(2, 16); 
        }

        // bilinear interpolation
        $a = $dy * self::size - $top;
        $b = $dx * self::size - $left;
        return $tl + ($bl - $tl) * $a + ($tr - $tl) * $b + ($tl - $bl - $tr + $br) * $a * $b;
    }

    protected function dataAtOffset($offset) {
        fseek($this->data, $offset * 2 - 2);
        $contents = fread($this->data, 2);
        $data = unpack('n', $contents); 
        return $data[1];
    }
}
