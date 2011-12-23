<?php
/*  This file is part of altiphp, Copyright (c) 2011 Arnaud Renevier, and is
    published under the modifier BSD license. */

namespace alti;

include_once realpath(__DIR__ . '/datasource.php');

class Alti {
    /**
     * @var string datasource backend
     */
    protected $source = null;

    /**
     * Constructor
     *
     * @param string|array $options
     */
    public function __construct($options = array()) {
        // default values
        $sourcename = 'srtmtiles';
        $sourceoptions = array();

        if (!$options) {
            // use default values
        } else if (is_string($options)) {
            $sourcename = $options;
        } else if (is_array($options)) {
            if (isset($options['source'])) {
                $sourcename = $options['source'];
            }
            unset($options['source']);
            $sourceoptions = $options;
        } else {
            throw new \InvalidArgumentException();
        }

        $this->load($sourcename, $sourceoptions);
    }

    /**
     * Load datasource backend
     *
     * @param string $sourcename backend name
     * @param array $sourceoptions backend options
     * @return void
     */
    protected function load($sourcename, array $sourceoptions = array()) {
        include_once realpath(__DIR__ . '/' . $sourcename . '/main.php');
        $className = __NAMESPACE__ . '\\' . ucfirst($sourcename) . 'DataSource';
        $this->source = new $className($sourceoptions);
    }

    /*
     * Calculates the distance between two points on earth surface
     *
     * @param float $p1lon first point longitude
     * @param float $p1lat first point latitude
     * @param float $p2lon second point longitude
     * @param float $p2lat second point latitude
     * @return float distance between points in meters
     */
    public function vincentyDistance($p1lon, $p1lat, $p2lon = null, $p2lat = null) {
        if ($p1lon instanceof \gisconverter\Point and $p1lat instanceof \gisconverter\Point) {
            return $this->vincentyDistance($p1lon->lon, $p1lon->lat, $p1lat->lon, $p1lat->lat);
        } else if (is_array($p1lon) and is_array($p1lat))  {
            return $this->vincentyDistance($p1lon[0], $p1lon[1], $p1lat[0], $p1lat[1]);
        }

        /* this function is a port to php from OpenLayers
           OpenLayers.Util.distVincenty javascript function */
        $a = 6378137;
        $b = 6356752.3142;
        $f = 1/298.257223563;

        $L = deg2rad($p2lon - $p1lon);
        $U1 = atan((1-$f) * tan(deg2rad($p1lat)));
        $U2 = atan((1-$f) * tan(deg2rad($p2lat)));
        $sinU1 = sin($U1);
        $cosU1 = cos($U1);
        $sinU2 = sin($U2);
        $cosU2 = cos($U2);

        $lambda = $L;
        $lambdaP = 2 * pi();
        $iterLimit = 20;

        while (abs ($lambda - $lambdaP) > 1e-12 && --$iterLimit > 0) {
            $sinLambda = sin($lambda);
            $cosLambda = cos($lambda);
            $sinSigma = sqrt(($cosU2 * $sinLambda) * ($cosU2 * $sinLambda) +
                    ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda) * ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda));
            if ($sinSigma==0) {
                return 0;  // co-incident points
            }
            $cosSigma = $sinU1 * $sinU2 + $cosU1 * $cosU2 * $cosLambda;
            $sigma = atan2($sinSigma, $cosSigma);
            $alpha = asin($cosU1 * $cosU2 * $sinLambda / $sinSigma);
            $cosSqAlpha = cos($alpha) * cos($alpha);
            $cos2SigmaM = $cosSigma - 2 * $sinU1 * $sinU2 / $cosSqAlpha;
            $C = $f / 16 * $cosSqAlpha * (4 + $f * (4 - 3 * $cosSqAlpha));
            $lambdaP = $lambda;
            $lambda = $L + (1 - $C) * $f * sin($alpha) *
                ($sigma + $C * $sinSigma * ($cos2SigmaM + $C * $cosSigma * (-1 + 2 * $cos2SigmaM * $cos2SigmaM)));
        }
        if ($iterLimit==0) {
            return null;  // formula failed to converge
        }

        $uSq = $cosSqAlpha * ($a * $a - $b * $b) / ($b * $b);
        $A = 1 + $uSq / 16384 * (4096 + $uSq * (-768 + $uSq * (320 - 175 * $uSq)));
        $B = $uSq / 1024 * (256 + $uSq * (-128 + $uSq * (74 - 47 * $uSq)));
        $deltaSigma = $B * $sinSigma * ($cos2SigmaM + $B / 4 * ($cosSigma * (-1 + 2 * $cos2SigmaM * $cos2SigmaM) -
            $B / 6 * $cos2SigmaM * (-3 + 4 * $sinSigma * $sinSigma) * (-3 + 4 * $cos2SigmaM * $cos2SigmaM)));
        $s = $b * $A * ($sigma - $deltaSigma);
        return round($s, 3); // round to 1mm precision
    }

    /**
     * interpolate an array of points by adding extra points to the route, so
     * that points are not separated by more than source precision.
     *
     * @param gisconverter\Geometry|array  gisconverter\Geometry or
     *                                     array containing multiple components
     * @return array with points added if needed
     */
    public function interpolate($path) {
        if ($path instanceof \gisconverter\Point) {
            return array($path->lon, $path->lat);
        } else if ($path instanceof \gisconverter\Geometry) {
            return $this->interpolate($path->components);
        }

        $precision = $this->source->getPrecision();
        $prev = null;
        $curr = null;
        $dist = 0;
        $res = array();
        foreach ($path as $comp) {
            $prev = $curr;
            if (is_array($comp) and (count($comp) == 2) and is_numeric($comp[0]) and is_numeric($comp[1])) {
                $curr = array($comp[0], $comp[1]);
            } else if ($comp instanceof \gisconverter\Point) {
                $curr = array($comp->lon, $comp->lat);
            } else { // not an array of points
                return $path;
            }
            if ($prev and $curr) {
                $dist = $this->vincentyDistance($prev[0], $prev[1], $curr[0], $curr[1]);
                if ($dist > $precision) {
                    $numpoints = floor($dist / $precision);
                    $dlon = ($curr[0] - $prev[0]) / ($numpoints + 1);
                    $dlat = ($curr[1] - $prev[1]) / ($numpoints + 1);
                    foreach (range (1, $numpoints) as $i) {
                        $res[] = array($prev[0] + $dlon * $i, $prev[1] + $dlat * $i);
                    }
                }
            }
            $res[] = $comp;
        }
        return $res;
    }

    /**
     * return altitude of a point or a geometry. Optionally interpolates routes.
     *
     * @param float|array|gisconverter\Geometry $arg lon point longitude or
     *                                               array containing two coordinates or
     *                                               gisconverter\Geometry or
     *                                               array containing multiple components
     * @param bool $lon optional latitude if $arg is a longitude
     * @return float or array()
     */
    public function altitude($arg, $lat = null) {
        if (is_numeric($arg) and is_numeric($lat)) {
            $lon = $arg;
            if ($lon < -180 or $lon > 180 or $lat < -90 or $lat > 90) {
                throw new \InvalidArgumentException();
            }
            return $this->source->altitude($lon, $lat);
        } else if (is_array($arg) and (count($arg) == 2) and is_numeric($arg[0]) and is_numeric($arg[1])) {
            return $this->source->altitude($arg[0], $arg[1]);
        } else if (is_array($arg)) {
            $res = array();
            foreach ($arg as $comp) {
                $altitude = $this->altitude($comp);
                $res[] = $altitude;
            }
            return $res;
        } else if ($arg instanceof \gisconverter\Point) {
            return $this->source->altitude($arg->lon, $arg->lat);
        } else if ($arg instanceof \gisconverter\Geometry) {
            return $this->altitude($arg->components);
        } else {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * return TRUE if geometry or point is totally covered by data source.
     *
     * @param float|array|gisconverter\Geometry $arg lon point longitude or
     *                                               array containing two coordinates or
     *                                               gisconverter\Geometry or
     *                                               array containing multiple components
     * @param bool $lon optional latitude if $arg is a longitude
     * @return bool
     */
    public function isCovered($arg, $lat = null) {
        if (is_numeric($arg) and is_numeric($lat)) {
            $lon = $arg;
            return $this->source->isCovered($lon, $lat);
        } else if (is_array($arg) and count($arg) == 2 and is_numeric($arg[0]) and is_numeric($arg[1])) {
            return $this->source->isCovered($arg[0], $arg[1]);
        } else if (is_array($arg)) {
            foreach ($arg as $item) {
                if (!$this->isCovered($item)) {
                    return FALSE;
                }
            }
            return TRUE;
        } else if ($arg instanceof \gisconverter\Point) {
            return $this->isCovered($arg->lon, $arg->lat);
        } else if ($arg instanceof \gisconverter\Geometry) {
            return $this->isCovered($arg->components);
        } else { // no an array of points
            throw new \InvalidArgumentException();
        }
    }

}
