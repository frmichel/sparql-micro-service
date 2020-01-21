# SPARQL Micro-Service Changelog


## [0.4.3] 2010-01-xx

### Changed
- Bug fix. Suppot for multiple values of a custom parameter on the HTTP query string (issue [#12](https://github.com/frmichel/sparql-micro-service/issues/12))
- In [scr/sparqlms/service.php](scr/sparqlms/service.php), when invoking the Web API, the service parameters are encoded with [rawurlencode](https://www.php.net/manual/function.rawurlencode.php) rather then [urlencode](https://www.php.net/manual/function.urlencode.php). The main difference is tu turn space into '%20' rather than '+'.



## [0.4.2] 2019-11-26

### Added
- Enable the deployement of SPARQL micro-services in multiple locations (not only src/sparqlms): see property `services_paths` in`/src/sparql/config.ini`
- Allow to deploy services with multiple hostnames: the `root_url` property in file `/src/sparql/config.ini` can now be overridden using argument `root_url` passed to the `/src/sparqlms/service.php` main script
- Added new property `sms:exampleURI` in ServiceDescription graph and updated dynamic HTML description

### Changed
- Allow HTTP proxy configuration with properties `proxy.*` in  `/src/sparql/config.ini` (issue [#9](https://github.com/frmichel/sparql-micro-service/issues/9))
- Support of URI dereferencing with services configured using a ServiceDescription graph (was not possible before)
- SPARQL micro-services moved from `/src/sparql` to `/service`


## [0.4.1] 2019-07-09

### Added
- Implemented issue [#8](https://github.com/frmichel/sparql-micro-service/issues/8): generate provenance information

### Changed
- Mandatory parameter `version` added to the global config.ini file


## [0.4.0] 2019-04-29

**CONFIGURATION CHANGE requires upgrade of existing micro-services**: in this version, files `insert.sparql` are removed. Instead, only a `construct.sparql` may be defined that replaces the function of both `insert.sparql` and `construct.sparql` in earlier versions.

**Upgrade procedure**: simply remove `insert.sparql` from services that have both an `insert.sparql` and `construct.sparql`, or rename `insert.sparql` into `construct.sparql` and replace the INSERT with CONSTRUCT within the queries themselves.

### Added
- New service `eol/getTraitsByName_sd`
- New service `flickr/getPhotosByTags_sd`
- Configuration parameter `log_level` in main config.ini file

### Changed
- Removal of files `insert.sparql`. Instead, only a `construct.sparql` may be defined that replaces the function of both `insert.sparql` and `construct.sparql` in versions 0.3.*.
- Fix issue [#2](https://github.com/frmichel/sparql-micro-service/issues/2): Implement http_header config param in the Service Description mode
- Fix issue [#3](https://github.com/frmichel/sparql-micro-service/issues/3): document rewriting rules for HTML doc generation
- Fix issue [#4](https://github.com/frmichel/sparql-micro-service/issues/4): support for multiple values of an argument. 
- Fix issue [#5](https://github.com/frmichel/sparql-micro-service/issues/5): pb about cache expiration
- Improve logging with log names


## [0.3.1] 2019-03-04

### Changed
- change namespace of SPARQL micro-service core vocabulary to http://ns.inria.fr/sparql-micro-service#
- change namespace of Web API-specific terms to http://ns.inria.fr/sparql-micro-service/api# in the internal processing of SPARQL micro-services
- update JSON-LD embedded in HTML documentation of a SPARQL micro-service to match requirements of Google's [Structured Dataset Testing Tool](https://search.google.com/structured-data/testing-tool)  
- doc refactoring, add service configuration section
- update Docker images and configuration<


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
- change interface of services' custom service.php scripts (see folder `src/sparqlms/manual_config_example`)
- update Docker deployment with code version 0.1.0 and a MongoDB container
- comply with composer common structure (remove directory `vendor`, point to own forks of the JsonLD and EasyRDF libraries)
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
