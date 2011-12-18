<?php
/*  This file is part of altiphp, Copyright (c) 2011 Arnaud Renevier, and is
    published under the modifier BSD license. */

class SrtmtilesTest extends PHPUnit_Framework_TestCase {
    private $options = array('srtmtiles');
//    private $options = array('source' => 'srtmtiles', 'cache' => 'data/');

    // we split tests into multiple functions in order to have only one tile resource loaded in the same time
    public function testAltitude () {
        $alti = new alti\Alti($this->options);

        $this->assertEquals(round($alti->altitude(2, 48)), 125); // round coordinates
        $this->assertEquals(round($alti->altitude(2.21333, 48.87333)), 167); // Mont Valérien
        $this->assertEquals(round($alti->altitude(2.343, 48.8861)), 119); // Montartre
        $this->assertEquals(round($alti->altitude(2.27472, 48.84111)), 32); // parc André Citroën
    }

    public function testAltitude2() {
        $alti = new alti\Alti($this->options);

        $this->assertEquals(round($alti->altitude(1.59333, 50.40889)), 9); // Berck
    }

    public function testAltitude3() {
        $alti = new alti\Alti($this->options);

        $this->assertEquals(round($alti->altitude(6.86972, 45.92306)), 1041); // Chamonix
    }

    public function testAltitude4() {
        $alti = new alti\Alti($this->options);

        $this->assertEquals(round($alti->altitude(-1.51139, 48.63603)), 45); // mont Saint Michel
    }

    public function testAltitude5() {
        $alti = new alti\Alti($this->options);

        $this->assertEquals(round($alti->altitude(37.35333, -3.07583)), 5865); // Kilimandaro
    }

    public function testAltitudeNeg() {
        $alti = new alti\Alti($this->options);

        $this->assertEquals(round($alti->altitude(35.5, 31.5)), -415); // Black Sea
    }

    public function testAltitudeNull() {
        $alti = new alti\Alti($this->options);

        $this->assertNull($alti->altitude(5.33, 60.38944)); // Bergen
        $this->assertNull($alti->altitude(6.865, 45.83361)); // Mont Blanc
    }

    public function testArrayNull() {
        $alti = new alti\Alti($this->options);

        $this->assertNull($alti->altitude(array(array(142.2, 11.35), array(37.35333, -3.07583)), FALSE)); // contains one null altitude
    }

    public function testMultiTileSpan() {
        $alti = new alti\Alti($this->options);

        $this->assertEquals(array_map('round', $alti->altitude(array(array(2.1, 48.1), array(1.9, 48.1)), FALSE)), array(121.0, 126.0));
    }

    public function testInterpolate() {
        $alti = new alti\Alti($this->options);

        $this->assertEquals($alti->interpolate(array(array(2.2001,48.80906), array(2.19121,48.80773), array(2.18819,48.80749))),
            array(array(2.2001, 48.80906), array(2.20121125, 48.80922625), array(2.2023225, 48.8093925),
                 array(2.20343375, 48.80955875), array(2.204545, 48.809725), array(2.20565625, 48.80989125),
                 array(2.2067675, 48.8100575), array(2.20787875, 48.81022375), array(2.19121, 48.80773),
                 array(2.1922166666667, 48.80781), array(2.1932233333333, 48.80789), array(2.18819, 48.80749)));
    }

    public function testCoverage() {
        $alti = new alti\Alti($this->options);
        $this->assertFalse($alti->isCovered(142.2, 11.35)); // Mariana Trench
        $this->assertNull($alti->altitude(142.2, 11.35)); //

        $this->assertFalse($alti->isCovered(10.38333, 63.41667)); // trondheim
        $this->assertNull($alti->altitude(10.38333, 63.41667)); //

        $this->assertFalse($alti->isCovered(array(array(2.343, 48.8861), array(142.2, 11.35)))); // has one invalid point
        $this->assertNull($alti->altitude(array(array(2.343, 48.8861), array(142.2, 11.35))));

    }

}

?>
