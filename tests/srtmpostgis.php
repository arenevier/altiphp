<?php
/*  This file is part of altiphp, Copyright (c) 2011 Arnaud Renevier, and is
    published under the modifier BSD license. */

class SrtmpostgisTest extends PHPUnit_Framework_TestCase {
    private $options = array('source' => 'srtmpostgis');
//    private $options = array('source' => 'srtmpostgis', 'dbname' => 'srtm', 'dbuser' => 'arno', 'dbpassword' => '');

    public function testAltitude () {
        $alti = new alti\Alti($this->options);

        $this->assertEquals(round($alti->altitude(2, 48)), 125); // round coordinates
        $this->assertEquals(round($alti->altitude(2.21333, 48.87333)), 167); // Mont Valérien
        $this->assertEquals(round($alti->altitude(2.343, 48.8861)), 119); // Montartre
        $this->assertEquals(round($alti->altitude(2.27472, 48.84111)), 32); // parc André Citroën
        $this->assertEquals(round($alti->altitude(1.59333, 50.40889)), 9); // Berck
        $this->assertEquals(round($alti->altitude(6.86972, 45.92306)), 1041); // Chamonix
        $this->assertEquals(round($alti->altitude(-1.51139, 48.63603)), 45); // mont Saint Michel
        $this->assertEquals(round($alti->altitude(37.35333, -3.07583)), 5865); // Kilimandaro
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
