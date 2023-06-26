# SPARQL Micro-Services

The SPARQL Micro-Service architecture [1, 3] is meant to allow the combination of Linked Data with data from Web APIs. It enables **querying non-RDF Web APIs with SPARQL**, and allows **on-the-fly assigning dereferenceable URIs to Web API resources** that do not have a URI in the first place.

Each SPARQL micro-service is a **lightweight, dedicated SPARQL endpoint** that typically provides access to a small, resource-centric graph. The graph produced can **use any vocabulary or ontology of your choice** and be tuned to meet your requirements. It is delineated by the Web API service being wrapped, the arguments passed to this service, and the types of RDF triples that the SPARQL micro-service is designed to spawn.

This project is a PHP implementation for JSON-based Web APIs. It comes with multiple configuration options to fit most specific APIs (e.g. add specific HTTP headers, configure a cache database) and can generate **provenance information** added to the graph being produced at the time a SPARQL micro-service is invoked.


## Quick start guide

These [slides](doc/quick-start-guide.pdf) describe the main concepts behind SPARQL micro-services, and then guide you through a guide to **quickly write and setup your first SPARQL micro-service**.



## Examples and Demo

You can check out some services we published at [https://sparql-micro-services.org/](https://sparql-micro-services.org/). 
An **HTML documentation and test interface** is generated dynamically from the micro-service description, that embeds http://schema.org/Dataset markup data to **make the service discoverable** using for instance [Google Dataset Search](https://datasetsearch.research.google.com/search?query=flickr%20sparql&docid=88YllZoR%2BmJMuXMgAAAAAA%3D%3D).

A **[demo](http://sparql-micro-services.org/demo-sms?param=Delphinapterus+leucas)** showcases the use of SPARQL micro-services to integrate, within a single SPARQL query, biodiversity data from a regular Linked Data source with non-RDF data resources: photos, scientific articles, life traits, audio recordings, all obtained through dedicated Web APIs wrapped in SPARQL micro-services.

This project comes with several example SPARQL micro-services, allowing for instance to search photos matching some tags on [Flickr](https://www.flickr.com/), or tunes whose titles match a given name in [MusicBrainz](https://musicbrainz.org/).
Other services are designed to query major biodiversity data sources such as the [GBIF](https://www.biodiversitylibrary.org/), [BHL](https://www.biodiversitylibrary.org/) or [EoL](http://eol.org/traitbank).
See the services available in this in [this repository](services/) as well as the [TaxrefWeb](https://github.com/frmichel/taxrefweb/tree/master/sparql-micro-services) application repository.



## Documentation

- [Usage of SPARQL micro-services](doc/01-usage.md)
- [Configuration of a SPARQL micro-service](doc/02-config.md)
- [Installation, configuration and deployment](doc/04-install.md)
- [Docker deployment](deployment/docker/)
- [Dynamic HTML documentation](doc/03-html-doc.md)
- [Provenance information](doc/05-prov.md)



## Typical use case

The query below illustates a common usage of SPARQL micro-serivces that builds a mashup of Linked Data and data from Web APIs.
It first retrieves the URI of the common dolphin species (Delphinus delphis) from TAXREF-LD, a biodiversity RDF dataset [2]. Then, it enriches this description with information from two Web APIs: photos from Flickr and audio recordings from the Macaulay Library.

The SPARQL endpoint as well as the two SPARQL micro-services are invoked within dedicated SERVICE clauses.

The example also illustrates the **two methods for passing arguments to a SPARQL micro-service**: either as RDF terms of regular  triple patterns (predicate ```dwc:scientificName``` for service ```macaulaylibrary/getAudioByTaxon_sd```), or on the endpoint URL (arguments ```group_id``` and ```tags``` for service ```flickr/getPhotosByGroupByTag```):

```sparql
prefix dwc:    <http://rs.tdwg.org/dwc/terms/>
prefix owl:    <http://www.w3.org/2002/07/owl#>
prefix rdfs:   <http://www.w3.org/2000/01/rdf-schema#>
prefix schema: <http://schema.org/>

CONSTRUCT {

    ?species
      schema:subjectOf ?photo; schema:image ?img; schema:thumbnailUrl ?thumbnail;
      schema:contentUrl ?audioUrl.
      
} WHERE {

    # Query a regular SPARQL endpoint of the LOD cloud
    SERVICE <http://taxref.mnhn.fr/sparql> {
      ?species 
        a                       owl:Class;
        rdfs:label              "Delphinus delphis". 
    }

    # SPARQL micro-serivce retrieving audio recordings
    # (Arguments passed as query graph pattern)
    SERVICE <https://example.org/sparqlms/macaulaylibrary/getAudioByTaxon_sd> {
        ?taxon
          dwc:scientificName    "Delphinus delphis";    # input argument
          schema:audio [ 
            schema:contentUrl   ?audioUrl               # expected output
          ]. 
    }
    
    # SPARQL micro-serivce retrieving photos from a Flickr group
    # (Arguments passed as in the endpoint URL)
    SERVICE <https://example.org/sparqlms/flickr/getPhotosByGroupByTag?group_id=806927@N20&tags=taxonomy:binomial=Delphinus+delphis> {
        ?photo 
          schema:image          ?img;
          schema:thumbnailUrl   ?thumbnail.
    }
}
```


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

[3] Michel F., Zucker C., Gargominy O. & Gandon F. (2018). Integration of Web APIs and Linked Data Using SPARQL Micro-Services—Application to Biodiversity Use Cases. *Information 9(12):310*. [DOI](https://dx.doi.org/10.3390/info9120310), [HAL](https://hal.archives-ouvertes.fr/hal-01947589).

### Conference

[1] Michel F., Faron-Zucker C. and Gandon F. SPARQL Micro-Services: Lightweight Integration of Web APIs and Linked Data. In *Proceedings of the Linked Data on the Web Workshop (LDOW2018)*. [HAL](https://hal.archives-ouvertes.fr/hal-01722792).

[2] Michel F., Gargominy O., Tercerie S. & Faron-Zucker C. (2017). A Model to Represent Nomenclatural and Taxonomic Information as Linked Data. Application to the French Taxonomic Register, TAXREF. In *Proceedings of the 2nd International Workshop on Semantics for Biodiversity (S4BioDiv) co-located with ISWC 2017*. Vienna, Austria. CEUR vol. 1933. [HAL](https://hal.archives-ouvertes.fr/hal-01617708).

[4] Michel F., Faron-Zucker C., Corby O. and Gandon F. Enabling Automatic Discovery and Querying of Web APIs at Web Scale using Linked Data Standards. In *LDOW/LDDL'19, companion proceedings of the 2019 World Wide Web Conference (WWW'19 Companion)*. [HAL](https://hal.archives-ouvertes.fr/hal-02060966).


### Poster

Michel F., Faron-Zucker C. & Gandon F. (2018). Bridging Web APIs and Linked Data with SPARQL Micro-Services. In *The Semantic Web: ESWC 2018 Satellite Events, LNCS vol. 11155, pp. 187–191*. Heraklion, Greece. Springer, Cham. [HAL](https://hal.archives-ouvertes.fr/hal-01783936v1).

### Demo

Michel F., Faron-Zucker C. & Gandon F. (2018). Integration of Biodiversity Linked Data and Web APIs using SPARQL Micro-Services. In *Biodiversity Information Science and Standards 2: e25481 (TDWG 2018)*. Dunedin, New Zealand. Pensoft. [DOI](https://dx.doi.org/10.3897/biss.2.25481), [HAL](https://hal.archives-ouvertes.fr/hal-01856365). 
Available online: https://sparql-micro-services.org/demo-sms?param=Delphinapterus%20leucas
