# SPARQL Micro-Service Changelog

## [Unversioned]

### Changed
- support SPARQL query with HTTP POST method


## [0.1.0] 2018-07-25

### Added
- full demo with STTL for TDWG conference

### Changed
- major refactoring to comply with usual conventions: add namespace, move code from sparql-ms to src/sparqlms
- split code in several classes: Metrology, Cache, Context
- config.ini now mandatory for all SPARQL micro-serivces, service.php may be used to handle more complex cases e.g. when a intermediate service call must be done, or when authentication is required (see manual_config_example).


## [0.0.1] 2018-06-19

First decent version of the project.

