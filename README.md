# SPARQL Micro-Services

The SPARQL Micro-Service architecture [1, 3] is meant to allow the combination of Linked Data with data from Web APIs. It enables **querying non-RDF Web APIs with SPARQL**, and allows **on-the-fly assigning dereferenceable URIs to Web API resources** that do not have a URI in the first place.

Each SPARQL micro-service is a lightweight, task-specific SPARQL endpoint that provides access to a small, resource-centric graph. The graph is delineated by the Web API service being wrapped, the arguments passed to this service, and the restricted types of RDF triples that the SPARQL micro-service is designed to spawn.


This project is a prototype PHP implementation for JSON-based Web APIs. It comes with several example SPARQL micro-services, designed in the context of a biodiversity-related use case, such as:
- search Flickr for photos with a given tag. We use it to search the [*Encyclopedia of Life* Flickr group](https://www.flickr.com/groups/806927@N20) for photos of a given taxon: photos of this group are tagged with the scientific name of the taxon they represent, formatted as ```taxonomy:binomial=<scientific name>```;
- retrieve audio recordings for a given taxon name from the [Macaulay Library](https://www.macaulaylibrary.org/), a scientific media archive related to birds, amphibians, fishes and mammals;
- search the [Biodiversity Heritage Library](https://www.biodiversitylibrary.org/) for scientific articles related to a given taxon name.
- search the [Encyclopedia of Life traits bank](http://eol.org/traitbank) for data related to a given taxon name. The API wrapped is a [Neo4J Cypher](https://neo4j.com/docs/cypher-manual/current/) endpoint;
- search the [MusicBrainz encyclopedia](https://musicbrainz.org/) for music tunes whose titles matching a given name.

**Each micro-service is further detailed in its own dedicated folder**.

## Documentation

- [Usage of SPARQL micro-services](/doc/01-usage.md)
- [Configure a SPARQL micro-service](/doc/02-config.md)
- [Automatic generation of HTML documentation](/doc/03-html-doc.md)
- [Installation, configuration and deployment](/doc/04-install.md)

## Typical use case

The query below illustates a common usage of SPARQL micro-serivces that builds a mashup of Linked Data and data from Web APIs.
It first retrieves the URI of the common dolphin species (Delphinus delphis) from TAXREF-LD, a biodiversity RDF dataset published [2]. Then, it enriches this description with information from two Web APIs: photos from Flickr and audio recordings from the Macaulay Library.

Each SPARQL micro-service is invoked within a dedicated SERVICE clause. If any of the Web APIs is not available (due for instance to a network error or internal failure etc.), the micro-service returns an empty result. In case this happens, the OPTIONAL clauses make it possible to still get (possibly partial) results.

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
    SERVICE <http://taxref.mnhn.fr/sparql>
    { ?species a owl:Class; rdfs:label "Delphinus delphis". }
    
    # SPARQL micro-serivce retrieving photos from the Eol Flickr group
    OPTIONAL {
      SERVICE <https://example.org/sparqlms/flickr/getPhotosByGroupByTag?group_id=806927@N20&tags=taxonomy:binomial=Delphinus+delphis>
      { ?photo schema:image ?img; schema:thumbnailUrl ?thumbnail. }
    }

    # SPARQL micro-serivce retrieving audio recordings
    OPTIONAL {
      SERVICE <https://example.org/sparqlms/macaulaylibrary/getAudioByTaxon?name=Delphinus+delphis>
      { [] schema:contentUrl ?audioUrl. }
    }
}
```

## Publications

[1] Franck Michel, Catherine Faron-Zucker and Fabien Gandon. *SPARQL Micro-Services: Lightweight Integration of Web APIs and Linked Data*. In Proceedings of the Linked Data on the Web Workshop (LDOW2018). https://hal.archives-ouvertes.fr/hal-01722792

[2] Franck Michel, Olivier Gargominy, Sandrine Tercerie & Catherine Faron-Zucker (2017). *A Model to Represent Nomenclatural and Taxonomic Information as Linked Data. Application to the French Taxonomic Register, TAXREF*. In Proceedings of the 2nd International Workshop on Semantics for Biodiversity (S4BioDiv) co-located with ISWC 2017 vol. 1933. Vienna, Austria. CEUR. https://hal.archives-ouvertes.fr/hal-01617708

[3] Michel F., Zucker C., Gargominy O. & Gandon F. (2018). *Integration of Web APIs and Linked Data Using SPARQL Micro-Services—Application to Biodiversity Use Cases*. Information 9(12):310. DOI: https://dx.doi.org/10.3390/info9120310

#### Poster

Michel F., Faron-Zucker C. & Gandon F. (2018). *Bridging Web APIs and Linked Data with SPARQL Micro-Services*. In The Semantic Web: ESWC 2018 Satellite Events, LNCS vol. 11155, pp. 187–191. Heraklion, Greece. Springer, Cham.

#### Demo

Michel F., Faron-Zucker C. & Gandon F. (2018). *Integration of Biodiversity Linked Data and Web APIs using SPARQL Micro-Services*. In Biodiversity Information Science and Standards, TDWG 2018 Proceedings, vol. 2, p. e25481. Dunedin, New Zealand. Pensoft.
