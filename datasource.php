<?php
/*  This file is part of altiphp, Copyright (c) 2011 Arnaud Renevier, and is
    published under the modifier BSD license. */

namespace alti;

interface DataSource {

    /**
     * return altitude of a point
     *
     * @param float $lon point longitude
     * @param float $lat point latitude
     * @return float
     */
    public function altitude($lon, $lat);

    /**
     * return data precision in meters
     *
     * @return float
     */
    public function getPrecision();

    /**
     * return TRUE if point is totally covered.
     *
     * @param float $lon point longitude
     * @param float $lat point latitude
     * @return bool
     */
    public function isCovered($lon, $lat);

}
