# SPARQL Micro-Services

The SPARQL Micro-Service architecture [1, 3] is meant to allow the combination of Linked Data with data from Web APIs. It enables **querying non-RDF Web APIs with SPARQL**, and allows **on-the-fly assigning dereferenceable URIs to Web API resources** that do not have a URI in the first place.

Each SPARQL micro-service is a **lightweight, task-specific SPARQL endpoint** that typically provides access to a **small, resource-centric graph**. The graph is delineated by the Web API service being wrapped, the arguments passed to this service, and the types of RDF triples that the SPARQL micro-service is designed to spawn.

Optionally, **provenance information** can be generated on the fly and added to the graph being produced at the time a SPARQL micro-service is invoked.

This project is a PHP implementation for JSON-based Web APIs. It comes with several example SPARQL micro-services, allowing for instance to search photos matching some tags on [Flickr](https://www.flickr.com/), or tunes whose titles match a given name in [MusicBrainz](https://musicbrainz.org/).
Other services are designed to query major biodiversity data sources such as the [Global Biodiversity Information Framework (GBIF)](https://www.biodiversitylibrary.org/), the [Biodiversity Heritage Library](https://www.biodiversitylibrary.org/) or the [Encyclopedia of Life traits bank (EoL)](http://eol.org/traitbank).
See the services available in this in [this repository](services/) as well as the [TaxrefWeb repository](https://github.com/frmichel/taxrefweb/tree/master/sparql-micro-services).
Each micro-service is further detailed in its own dedicated folder.


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

The SPARQL endpoint as well as the two SPARQL micro-service are invoked within dedicated SERVICE clauses.

The example also illustrates the two methods for passing arguments to a SPARQL micro-service: either on the endpoint URL (arguments ```group_id``` and ```tags``` for service ```flickr/getPhotosByGroupByTag below```), or as RDF terms of regular  triple patterns (predicate ```dwc:scientificName``` for service ```macaulaylibrary/getAudioByTaxon_sd```).

If any of the Web APIs is not available (due for instance to a network error or internal failure etc.), the micro-service returns an empty result. In thss case, the OPTIONAL clauses make it possible to still get (possibly partial) results.

```sparql
prefix rdfs:   <http://www.w3.org/2000/01/rdf-schema#>
prefix owl:    <http://www.w3.org/2002/07/owl#>
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
    
    # SPARQL micro-serivce retrieving photos from a Flickr group
    OPTIONAL {
      SERVICE <https://example.org/sparqlms/flickr/getPhotosByGroupByTag?group_id=806927@N20&tags=taxonomy:binomial=Delphinus+delphis> {
        ?photo 
          schema:image          ?img;
          schema:thumbnailUrl   ?thumbnail.
      }
    }

    # SPARQL micro-serivce retrieving audio recordings
    OPTIONAL {
      SERVICE <https://example.org/sparqlms/macaulaylibrary/getAudioByTaxon_sd> {
        ?taxon
          dwc:scientificName    "Delphinus delphis";    # input argument
          schema:audio [ 
            schema:contentUrl   ?audioUrl               # expected output
          ]. 
      }
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
Available online: http://sms.i3s.unice.fr/demo-sms?param=Delphinapterus+leucas
