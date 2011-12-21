<?php
/*  This file is part of altiphp, Copyright (c) 2011 Arnaud Renevier, and is
    published under the modifier BSD license. */

class AltiTest extends PHPUnit_Framework_TestCase {
    private $options = null; // srtmtiles by default
//    private $options = array('source' => 'srtmtiles', 'cache' => 'data/');

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidArgLon() {
        $alti = new alti\Alti($this->options);

        $alti->altitude(-181, 0);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidArgLat() {
        $alti = new alti\Alti($this->options);

        $alti->altitude(0, 91);
    }

    public function testArray() {
        $alti = new alti\Alti($this->options);

        $this->assertEquals($alti->altitude(array(2.46064, 48.70408)), $alti->altitude(2.46064, 48.70408));

        $this->assertEquals($alti->altitude(array(array(2.46064, 48.70408), array(2.46338, 48.70815))),
                            array($alti->altitude(2.46064, 48.70408), $alti->altitude(2.46338, 48.70815)));
    }

    public function testGeometries() {
        if (!@include_once("gisconverter.php")) {
            return;
        }
        $decoder = new gisconverter\WKT();
        $alti = new alti\Alti($this->options);

        $point = $decoder->geomFromText('POINT(2.21333 48.87333)');
        $this->assertEquals($alti->altitude($point), $alti->altitude(2.21333, 48.87333));

        $multipoint = $decoder->geomFromText('MULTIPOINT(2.46064 48.70408, 2.46338 48.70815, 2.46467 48.70982, 2.46497 48.71263, 2.46746 48.71277, 2.46999 48.71730)');
        $this->assertEquals($alti->altitude($multipoint), 
                            $alti->altitude(array(array(2.46064, 48.70408), array(2.46338, 48.70815), array(2.46467, 48.70982), 
                                                  array(2.46497, 48.71263), array(2.46746, 48.71277), array(2.46999, 48.71730))));

        $linestring = $decoder->geomFromText('LINESTRING(2.46064 48.70408, 2.46338 48.70815, 2.46467 48.70982, 2.46497 48.71263, 2.46746 48.71277, 2.46999 48.71730)');
        $this->assertEquals($alti->altitude($linestring),
                            $alti->altitude(array(array(2.46064, 48.70408), array(2.46338, 48.70815), array(2.46467, 48.70982), 
                                                  array(2.46497, 48.71263), array(2.46746, 48.71277), array(2.46999, 48.71730))));

        $multilinestring = $decoder->geomFromText('MULTILINESTRING((2.46064 48.70408, 2.46338 48.70815, 2.46467 48.70982, 2.46497 48.71263, 2.46746 48.71277, 2.46999 48.71730))');
        $this->assertEquals($alti->altitude($multilinestring), 
                            $alti->altitude(array(array(array(2.46064, 48.70408), array(2.46338, 48.70815), array(2.46467, 48.70982), 
                                                        array(2.46497, 48.71263), array(2.46746, 48.71277), array(2.46999, 48.71730)))));

        $linearring = $decoder->geomFromText('LINEARRING(2.29374 48.80689, 2.28898 48.80395, 2.29383 48.80098, 2.29683 48.80217, 2.29769 48.80404, 2.29374 48.80689)');
        $this->assertEquals($alti->altitude($linearring), 
                            $alti->altitude(array(array(2.29374, 48.80689), array(2.28898, 48.80395), array(2.29383, 48.80098),
                                                  array(2.29683, 48.80217), array(2.29769, 48.80404), array(2.29374, 48.80689))));

        $polygon = $decoder->geomFromText('POLYGON((2.29374 48.80689, 2.28898 48.80395, 2.29383 48.80098, 2.29683 48.80217, 2.29769 48.80404, 2.29374 48.80689))');
        $this->assertEquals($alti->altitude($polygon), 
                            $alti->altitude(array(array(array(2.29374, 48.80689), array(2.28898, 48.80395), array(2.29383, 48.80098),
                                                        array(2.29683, 48.80217), array(2.29769, 48.80404), array(2.29374, 48.80689)))));

        $multipolygon = $decoder->geomFromText('MULTIPOLYGON(((2.29374 48.80689, 2.28898 48.80395, 2.29383 48.80098, 2.29683 48.80217, 2.29769 48.80404, 2.29374 48.80689)))');
        $this->assertEquals($alti->altitude($multipolygon),
                            $alti->altitude(array(array(array(array(2.29374, 48.80689), array(2.28898, 48.80395), array(2.29383, 48.80098),
                                                              array(2.29683, 48.80217), array(2.29769, 48.80404), array(2.29374, 48.80689))))));

        $geometrycollection = $decoder->geomFromText('GEOMETRYCOLLECTION(POINT(2.21333 48.87333), LINESTRING(2.46064 48.70408, 2.46338 48.70815, 2.46467 48.70982, 2.46497 48.71263, 2.46746 48.71277, 2.46999 48.71730))');
        $this->assertEquals($alti->altitude($geometrycollection), 
                            $alti->altitude(array(array(2.21333, 48.87333),
                                                  array(array(2.46064, 48.70408), array(2.46338, 48.70815), array(2.46467, 48.70982),
                                                        array(2.46497, 48.71263), array(2.46746, 48.71277), array(2.46999, 48.71730)))));
    }

    public function testVincenty() {
        $alti = new alti\Alti($this->options);

        $this->assertEquals($alti->vincentyDistance(2.2001, 48.80906, 2.19121, 48.80773), 669.53);
        $this->assertEquals($alti->vincentyDistance(array(2.2001, 48.80906), array(2.19121, 48.80773)), 669.53);
    }

    public function testInterpolate() {
        $alti = new alti\Alti($this->options);

        $comps = array(array(2.2001,48.80906), array(2.19121,48.80773), array(2.18819,48.80749));
        $interpolate = $alti->interpolate($comps);

        $this->assertGreaterThanOrEqual(count($comps), count($interpolate));

        $dist = 0;
        $previous = $comps[0];
        foreach (array_slice($comps, 1) as $point) {
            $dist += $alti->vincentyDistance($previous[0], $previous[1], $point[0], $point[1]);
            $previous = $point;
        }

        $int_dist = 0;
        $previous = $comps[0];
        foreach (array_slice($interpolate, 1) as $point) {
            $int_dist += $alti->vincentyDistance($previous[0], $previous[1], $point[0], $point[1]);
            $previous = $point;
        }
        $this->assertEquals($dist, $int_dist);
    }

    public function testCoverage() {
        $alti = new alti\Alti($this->options);

        $this->assertEquals($alti->isCovered(array(2.21333, 48.87333)), $alti->isCovered(2.21333, 48.87333));
        $this->assertEquals($alti->isCovered(array(array(2.46064, 48.70408), array(2.46338, 48.70815))),
                            $alti->isCovered(2.46064, 48.70408) and $alti->isCovered(2.46338, 48.70815));

        if (@include_once("gisconverter.php")) {
            $decoder = new gisconverter\WKT();

            $point = $decoder->geomFromText('POINT(2.21333 48.87333)');
            $this->assertEquals($alti->isCovered($point), $alti->isCovered(2.21333, 48.87333));

            $linestring = $decoder->geomFromText('LINESTRING(2.46064 48.70408, 2.46338 48.70815)');
            $this->assertEquals($alti->isCovered($linestring),
                            $alti->isCovered(2.46064, 48.70408) and $alti->isCovered(2.46338, 48.70815));
        }
    }
}
