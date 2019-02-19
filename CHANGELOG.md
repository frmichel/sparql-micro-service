# SPARQL Micro-Service Changelog


## [Unreleased] 2019-02-18

### Changed
- change namespace of SPARQL micro-service core vocabulary to http://ns.inria.fr/sparql-micro-service#
- change namespace of Web API-specific terms to http://ns.inria.fr/sparql-micro-service/api# in the internal processing of SPARQL micro-services
- update JSON-LD embedded in HTML documentation of a SPARQL micro-service to match requirements of Google's [Structured Dataset Testing Tool](https://search.google.com/structured-data/testing-tool)  
- doc refactoring, add service configuration section

## [0.3.0] 2019-01-24

### Added
- Allow passing arguments of SPARQL micro-services as variables of the graph pattern, along with possily VALUES or FILTER clauses to give the values of these variables
- Generate HTML documentation of a SPARQL micro-service from its SPARQL Service description + JSON-LD embedded markup
- Federated querying (beta): service to split a SPARQL query into a union of invocations (SERVICE clauses) to multiples SPARQL micro-services

### Changed
- refactoring of code into packages common, sparqlms and sparqlcompose
- refactoring of deployment resources into dedicated folder


## [0.2.0] 2018-11-26

### Added
- new configuration method using SPARQL Service Description + Hydra + SHACL instead of config.ini file
- new class Configuration to switch automatically between config.ini and Service Description
- new services supporting SPARQL Service Description 
    - flickr/getPhotosByTaxon_sd
    - macaulaylibrary/getAudioByTaxon_sd
- new service macaulaylibrary/getAudioById for URI dereferencing

### Changed
- change interface of services' custom service.php scripts (see folder ```src/sparqlms/manual_config_example```)
- update Docker deployment with code version 0.1.0 and a MongoDB container
- comply with composer common structure (remove directory ```vendor```, point to own forks of the JsonLD and EasyRDF libraries)
- fix typos in TDWG demo


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
