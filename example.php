<?php
    include "alti.php";

    // initialize service with srtmtiles data source backend
    $service = new alti\Alti('srtmtiles');

    // you can also pass some options to srtmtiles backend; 'cache' will define a
    // directory where tiles will be stored; 'httpbackend' will define library
    // used to download tiles: either Zend or curl
    // $service = new alti\Alti(array('source' => 'srtmtiles', 'cache' => 'data/', 'httpbackend' => 'Zend'));

    // or you can use postgis backend if you have loaded data into postgis with srtm2postgis
    // $service = new alti\Alti(array('source' => 'srtmpostgis', 'dbname' => 'srtm', 'dbuser' => 'arno', 'dbpassword' => ''));

    // get altitude for a given point
    var_dump($service->altitude(2.21333, 48.87333)); // about 167

    // you can define the point as an array of lon lat
    var_dump($service->altitude(array(2.21333, 48.87333)));

    // for some points, altitude is not avaible
    var_dump($service->altitude(142.2, 11.35)); // null; this is different than 0 meter altitude

    // you can define multiple points, and get an array of altitudes
    var_dump($service->altitude(array(array(2.4606, 48.70408), array(2.46338, 48.70815)))); // an array with 2 altitudes

    // you can interpolate a component; extra points will be added to use
    // maximal resolution available, and get a more accurate profile
    var_dump($service->interpolate(array(array(2.4606, 48.70408), array(2.46338, 48.70815)))); // an array with 2 or more altitudes

    // you can check wether an area is covered by data. This allow checking
    // quickly if altitude may be unavailable without computing it for real.
    // Beware, a value of TRUE does not mean data will always be available. With
    // srtm, some areas are globally available, but have some local voids. On
    // the other side, a value of FALSE means there's no way altitude will be
    // available.
    var_dump($service->isCovered(10.38333, 63.41667)); // trondheim

    if (@include("gisconverter.php")) {
        // if gisconverter is available, you can use geometries objects as arguments

        $decoder = new gisconverter\WKT();
        $geometry = $decoder->geomFromText('LINESTRING(2.4606 48.70408, 2.46338 48.70815)');
        var_dump($service->altitude($geometry)); // an array of altitude
    }

    // with srtmtiles backend, you can set a limit on the number of loaded tiles
    $service = new alti\Alti(array('source' => 'srtmtiles', 'maxtilesloaded' => 2));
    try {
        $service->altitude(array(array(2.1, 48.1), array(1.9, 48.1), array(1.9, 47.9)));
    } catch (\OverflowException $e) { // an OverflowException is raised
    }
