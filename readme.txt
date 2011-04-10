altiphp
==========

altiphp is a php library to get altitudes of points or geometries. You can also
compute complete route profiles.

When given a longitude, and a latitude, altiphp will compute altitude. When
given multiple points in an array, or when given a Multipoint Geometry (or a
derived geometry such as LineString or LinearRing) from [gisconverter][1]
library, altiphp will return an array of altitudes. 

arrays can be interpolated interpolated to add more points to match datasource
resolution. Then, you can get an accurate route profile.

altiphp uses [SRTM][2] data as a source of altitude data. But it's possible (and
hopefully easy) to write backends for other sources of data.

Two srtm backends are available: 

* __srtmpostgis__: data are read from postgis. Data must have been previously
                   loaded into postgis with [srtm2postgis][3].
* __srtmtiles__: data are downloaded online, stored as zipped files, and data
                 is loaded from those files.

With srtmtiles, either curl or zend http client is used to download tiles. If
neither of those are available, altiphp won't be able to fetch SRTM data
online. zip extension is also needed to unzip compressed files. Tiles can also
be optionally stored in a directory. Then, they won't be downloaded next time.
To spare disk space, they are stored compressed.  Each loaded tile takes about
300Mo of memory, so you can optionaly limit the number of tiles loaded at once.

See [example.php](example.php) to see how to use altiphp

altiphp needs php at least 5.3

altiphp is available under modified bsd license. See [copying.txt](copying.txt)
for more informations.

[1]: https://github.com/arenevier/gisconverter.php
[2]: http://wiki.openstreetmap.org/wiki/SRTM
[3]: https://github.com/arenevier/srtm2postgis
