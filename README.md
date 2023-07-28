# SPARQL Micro-Services

The SPARQL Micro-Service architecture [1, 3] enables **querying Web APIs with SPARQL**, as well as **assigning dereferenceable URIs to Web API resources** that do not have a URI in the first place.

Each SPARQL micro-service is a **lightweight SPARQL endpoint** that typically provides access to a small, resource-centric graph. It takes arguments that depend on the Web API, query the Web API, and build a graph that uses **any vocabulary or ontology of your choice**.

 #### Configuration options to fit most specific APIs:
- Add any **HTTP headers** to the Web API query;
- Support for APIs with **OAuth2 authentication** (see [example](services/advanced_examples/oauth2));
- Native support for **JSON-based** Web APIs. XML-based Web APIs are supported with an additional component (see [example](services/advanced_examples/xml_api));

#### Implementation features:
- Easy **Docker-based deployment**;
- **Simple**: a micro-service consists of a configuration file, a JSON-LD profile and a SPARQL query
- **Dynamic**: simply drop off your files and your service is ready to go.
- **Cache database** with configurable expiration time, to improve performance;
- **Provenance information** about the API invokation (time, parameterr etc.);
- Web API invokation with **HTTP GET method**, POST is not supported.
- Dynamic generation of an HTML documentation and test interface


## Quick start guide

The most straightforward way to run SPARQL micro-services is using the [Docker deployment](deployment/docker/) option.

Then, these [slides](doc/quick-start-guide.pdf) describe the main concepts behind SPARQL micro-services and provide a guide to **quickly write and setup your first SPARQL micro-service**.



## Examples and Demo

You can check out some services we published at [https://sparql-micro-services.org/](https://sparql-micro-services.org/). The source of these services is provided in a separate Github repository: 
[sparql-micro-services.org/](https://github.com/frmichel/sparql-micro-service.org)

The **HTML documentation and test interface** is [generated dynamically](doc/03-html-doc.md) from the micro-service descriptions, that embeds Schema.org markup to **make the services discoverable** using for instance [Google Dataset Search](https://datasetsearch.research.google.com/search?query=flickr%20sparql&docid=88YllZoR%2BmJMuXMgAAAAAA%3D%3D).

A **[demo](http://sparql-micro-services.org/demo-sms?param=Delphinapterus+leucas)** showcases the use of SPARQL micro-services to integrate, within a single SPARQL query, biodiversity data from a regular Linked Data source with non-RDF data resources: photos, scientific articles, life traits, audio recordings, all obtained through dedicated Web APIs wrapped in SPARQL micro-services.

This project comes with [several example](services/advanced_examples/) SPARQL micro-services, allowing for instance to search photos matching some tags on [Flickr](https://www.flickr.com/), or tunes whose titles match a given name in [MusicBrainz](https://musicbrainz.org/).

Also, in the [TaxrefWeb](https://github.com/frmichel/taxrefweb/tree/master/sparql-micro-services) repository we provide services to query major biodiversity data sources such as the [GBIF](https://www.biodiversitylibrary.org/), [BHL](https://www.biodiversitylibrary.org/) or [EoL](http://eol.org/traitbank).


## Documentation

- [How to use SPARQL micro-services](doc/01-usage.md)
- [Configuring a SPARQL micro-service](doc/02-config.md)
- [Docker-based deployment](deployment/docker/)
- [Complete non-Docker installation procedure](doc/04-install.md) (for more advanced deployments)
- [Dynamic HTML documentation](doc/03-html-doc.md)
- [Adding provenance information](doc/05-prov.md)


## Cite this work:

Michel F., Faron C., Gargominy O. & Gandon F. (2018). Integration of Web APIs and Linked Data Using SPARQL Micro-Services—Application to Biodiversity Use Cases. *Information 9(12):310*. [DOI](https://dx.doi.org/10.3390/info9120310), [HAL](https://hal.archives-ouvertes.fr/hal-01947589).


```bibtex
@article{michel_sparqlmicroservices_2018,
  title = {Integration of {{Web APIs}} and {{Linked Data Using SPARQL Micro}}-{{Services}}\textemdash{{Application}} to {{Biodiversity Use Cases}}},
  volume = {9},
  copyright = {Licence Creative Commons Attribution 4.0 International (CC-BY)},
  issn = {2078-2489},
  language = {en},
  number = {12},
  journal = {Information},
  doi = {10.3390/info9120310},
  author = {Michel, Franck and Faron, Catherine and Gargominy, Olivier and Gandon, Fabien},
  month = dec,
  year = {2018},
  pages = {310},
  url = {https://hal.archives-ouvertes.fr/hal-01947589}
}
```


## Publications

### Journal

[3] Michel F., Faron C., Gargominy O. & Gandon F. (2018). Integration of Web APIs and Linked Data Using SPARQL Micro-Services—Application to Biodiversity Use Cases. *Information 9(12):310*. [DOI](https://dx.doi.org/10.3390/info9120310), [HAL](https://hal.archives-ouvertes.fr/hal-01947589).

### Conference

[1] Michel F., Faron-Zucker C. and Gandon F. SPARQL Micro-Services: Lightweight Integration of Web APIs and Linked Data. In *Proceedings of the Linked Data on the Web Workshop (LDOW2018)*. [HAL](https://hal.archives-ouvertes.fr/hal-01722792).

[2] Michel F., Gargominy O., Tercerie S. & Faron-Zucker C. (2017). A Model to Represent Nomenclatural and Taxonomic Information as Linked Data. Application to the French Taxonomic Register, TAXREF. In *Proceedings of the 2nd International Workshop on Semantics for Biodiversity (S4BioDiv) co-located with ISWC 2017*. Vienna, Austria. CEUR vol. 1933. [HAL](https://hal.archives-ouvertes.fr/hal-01617708).

[4] Michel F., Faron-Zucker C., Corby O. and Gandon F. Enabling Automatic Discovery and Querying of Web APIs at Web Scale using Linked Data Standards. In *LDOW/LDDL'19, companion proceedings of the 2019 World Wide Web Conference (WWW'19 Companion)*. [HAL](https://hal.archives-ouvertes.fr/hal-02060966).


### Poster

Michel F., Faron-Zucker C. & Gandon F. (2018). Bridging Web APIs and Linked Data with SPARQL Micro-Services. In *The Semantic Web: ESWC 2018 Satellite Events, LNCS vol. 11155, pp. 187–191*. Heraklion, Greece. Springer, Cham. [HAL](https://hal.archives-ouvertes.fr/hal-01783936v1).

### Demo

Michel F., Faron-Zucker C. & Gandon F. (2018). Integration of Biodiversity Linked Data and Web APIs using SPARQL Micro-Services. In *Biodiversity Information Science and Standards 2: e25481 (TDWG 2018)*. Dunedin, New Zealand. Pensoft. [DOI](https://dx.doi.org/10.3897/biss.2.25481), [HAL](https://hal.archives-ouvertes.fr/hal-01856365). 
Available online: https://sparql-micro-services.org/demo-sms?param=Delphinapterus%20leucas
