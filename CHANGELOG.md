# SPARQL Micro-Service Changelog

## [UNVERSIONED] 2018-08-16

### Changed
- add service macauleylibrary/getAudioById for URI dereferencing
- update Docker deployment with last version of code and a MongoDB container
- fix typos in the demo partial


## [0.2.0] 2018-07-27

### Changed
- comply with composer common structure
    - remove directory ```vendor```
    - make composer.json point to my own forks of the JsonLD and EasyRDF libraries


## [0.1.0] 2018-07-26

### Added
- full demo with STTL for TDWG conference
- support SPARQL query with HTTP POST method

### Changed
- major refactoring to comply with usual conventions: add namespace, move code from sparql-ms to src/sparqlms
- split code in several classes: Metrology, Cache, Context
- config.ini now mandatory for all SPARQL micro-serivces, service.php may be used to handle more complex cases e.g. when a intermediate service call must be done, or when authentication is required (see manual_config_example).


## [0.0.1] 2018-06-19

First decent version of the project.
