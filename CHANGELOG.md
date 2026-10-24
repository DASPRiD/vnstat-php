# Changelog

## 1.0.0 (2026-10-24)


### ⚠ BREAKING CHANGES

* Vnstat\Database::getHours() is keyed by position rather than by vnStat's row id, and no longer pads absent hours with zero entries.
* Vnstat\Database::getDays(), getMonths() and getTop10() are keyed by position rather than by vnStat's row id.
* vnStat 1.x is no longer supported. Install vnStat 2.0 or newer.
* Vnstat\Database::__construct now takes the vnStat JSON as a string rather than an interface name. Use Vnstat\Reader to obtain it.
* point the virtual host document root at the "public" directory instead of the repository root, and move an existing "config.php" to "config/config.php".

### Features

* add a theme control ([b943134](https://github.com/DASPRiD/vnstat-php/commit/b943134623cc87a82e6a24a360076cdf6ec631e5))
* chart the daily and monthly totals ([16ef333](https://github.com/DASPRiD/vnstat-php/commit/16ef333fc21bd6ba27109141fa3ff5da2f8d0d41)), closes [#5](https://github.com/DASPRiD/vnstat-php/issues/5)
* replace Bootstrap with hand-written CSS ([a315e60](https://github.com/DASPRiD/vnstat-php/commit/a315e608cf4d181dd10135337d75f2dd5fa4041e))
* replace xcharts with Chart.js ([220a7f4](https://github.com/DASPRiD/vnstat-php/commit/220a7f4a0ac99469527356e41f0fa071d0e31ced)), closes [#16](https://github.com/DASPRiD/vnstat-php/issues/16)
* require the vnStat 2 JSON schema ([937766d](https://github.com/DASPRiD/vnstat-php/commit/937766df9ed30043e06f2bda4f609970da6a4f8a))
* show byte and bitrate values to three significant figures ([ff251e1](https://github.com/DASPRiD/vnstat-php/commit/ff251e1f5388d34641bcfa1346f2541a1e72a8a0))


### Bug fixes

* build the hourly chart from a real 24 hour window ([f32596f](https://github.com/DASPRiD/vnstat-php/commit/f32596f92a88867070a7ce91cb7e812ff25b1bb3)), closes [#13](https://github.com/DASPRiD/vnstat-php/issues/13)
* compute bitrates with decimal prefixes ([066cb76](https://github.com/DASPRiD/vnstat-php/commit/066cb76ed2101414f24579d8c2c9a9df3651b4dd))
* draw no ratio bar for an entry with no traffic ([bf72c53](https://github.com/DASPRiD/vnstat-php/commit/bf72c537be09b4031ee62a8d5ba2c1e9af105dda))
* escape the interface name passed to vnstat ([33ccd1f](https://github.com/DASPRiD/vnstat-php/commit/33ccd1f6e7d6c019846327970ee394475e1a8c5e))
* mark Entry constructor parameter as explicitly nullable ([dd3a1e0](https://github.com/DASPRiD/vnstat-php/commit/dd3a1e0e0f34eae916a3bbc155215dd7f92dc8a4))
* number the Top 10 by rank instead of by row id ([6b177e2](https://github.com/DASPRiD/vnstat-php/commit/6b177e2698979621bb62be76ed7ef443aff01aa8))
* report vnStat failures instead of rendering an empty page ([bcedecf](https://github.com/DASPRiD/vnstat-php/commit/bcedecfd9426de67d8804a475f00c74af0cb5295)), closes [#15](https://github.com/DASPRiD/vnstat-php/issues/15)
* version asset URLs with their modification time ([94fb2b7](https://github.com/DASPRiD/vnstat-php/commit/94fb2b74bf724c4ed0873e1bc89229ccaab1b861))


### Packaging

* cut releases from the commit log ([a4b5201](https://github.com/DASPRiD/vnstat-php/commit/a4b5201da510f3522a30d51678c9274238788ca4))
* declare PHP and extension requirements ([4f39322](https://github.com/DASPRiD/vnstat-php/commit/4f3932294d43eb9e1f5ea7baeb69f6a383da5036)), closes [#14](https://github.com/DASPRiD/vnstat-php/issues/14)
* move to pds/skeleton layout ([dddadd2](https://github.com/DASPRiD/vnstat-php/commit/dddadd212e02d97a3e5b3a8849bc9df4912e0b67))


### Refactoring

* extract the view formatters into a class ([f411abe](https://github.com/DASPRiD/vnstat-php/commit/f411abe4e4dce083e36c15fadfdf05f40d80e6c0))
* stop mutating a shared entry in the monthly table ([533a3e9](https://github.com/DASPRiD/vnstat-php/commit/533a3e9d9485bfe529793fe34c007a22778ef9f6))


### Documentation

* rewrite the README around the release tarball ([a589e6e](https://github.com/DASPRiD/vnstat-php/commit/a589e6eefadd4d800add1c21aeeba0e91ccd55c7))
