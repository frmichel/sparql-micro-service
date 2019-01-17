# SPARQL Micro-Services

The SPARQL Micro-Service architecture [1, 3] is meant to allow the combination of Linked Data with data from Web APIs. It enables querying non-RDF Web APIs with SPARQL, and allows on-the-fly assigning dereferenceable URIs to Web API resources that do not have a URI in the first place.

This project is a prototype PHP implementation for JSON-based Web APIs. It comes with several example SPARQL micro-services, designed in the context of a biodiversity-related use case, such as:
- search Flickr for photos with a given tag. We use it to search the [*Encyclopedia of Life* Flickr group](https://www.flickr.com/groups/806927@N20) for photos of a given taxon: photos of this group are tagged with the scientific name of the taxon they represent, formatted as ```taxonomy:binomial=<scientific name>```;
- retrieve audio recordings for a given taxon name from the [Macaulay Library](https://www.macaulaylibrary.org/), a scientific media archive related to birds, amphibians, fishes and mammals;
- searche the [MusicBrainz music information encyclopedia](https://musicbrainz.org/) for music tunes whose titles matching a given name;
- search the [Biodiversity Heritage Library](https://www.biodiversitylibrary.org/) for scientific articles related to a given taxon name.
- search the [Encyclopedia of Life traits bank](http://eol.org/traitbank) for data related to a given taxon name.

**Each micro-service is further detailed in its dedicated folder**.

## The SPARQL Micro-Service Architecture

A SPARQL micro-service is a lightweight, task-specific SPARQL endpoint that provides access to a small, resource-centric virtual graph, while dynamically assigning dereferenceable URIs to Web API resources that do not have URIs beforehand. The graph is delineated by the Web API service being wrapped, the arguments passed to this service, and the restricted types of RDF triples that this SPARQL micro-service is designed to spawn.


## How to use SPARQL micro-services?

As any regular SPARQL endpoint, a SPARQL micro-service expects a SPARQL query. Additionally, it usually expects arguments that it will use to call the Web API. __Two different flavours__ exist with respect to how arguments are passed to a SPARQL micro-service: (i) as parameters on the HTTP query string of the endpoint URL, or (ii) as values within the SPARQL query graph pattern

### Passing arguments on the HTTP query string
The query below exemplifies the first flavour. It retrieves information related to the common dolphin species (*Delphinus delphis*) from the Macaulay Library. The taxon name is passed as a parameter on the HTTP query string of the SPARQL micro-service URL, ```?name=Delphinus+delphis```:

```sparql
SERVICE <https://example.org/sparqlms/macaulaylibrary/getAudioByTaxon?name=Delphinus+delphis>
{
  SELECT ?audioUrl WHERE {
    [] schema:contentUrl ?audioUrl.
  }
}
```

The arguments expected by the micro-service (```name``` in this case) are listed in file macaulaylibrary/getAudioByTaxon/config.ini.

### Passing arguments within the SPARQL query graph pattern
The query below exemplifies the second flavour. It is equivalent to the one above but the taxon name is provided as part of the graph pattern with predicate ```dwc:scientificName```:

```sparql
SERVICE <https://example.org/sparqlms/macaulaylibrary/getAudioByTaxon_sd>
{
  SELECT ?audioUrl WHERE {
    [] a dwc:Taxon;
       dwc:scientificName "Delphinus delphis";
       schema:audio [ schema:contentUrl ?audioUrl ].
  }
}
```

The arguments expected by the micro-service (```name``` in this case) are described in the service description (file macaulaylibrary/getAudioByTaxon/ServiceDescription.ttl) either using the [Hydra](https://www.hydra-cg.com/spec/latest/core/) vocabulary or by pointing to a property shape within the [SHACL](https://www.w3.org/TR/shacl/) graph that describes the type of graph that this micro-service can spawn.   

### Typical use case

The query below illustates a common usage of SPARQL micro-serivces that builds a mashup of Linked Data and data from Web APIs.
It first retrieves the URI of the common dolphin species (Delphinus delphis) from TAXREF-LD, a Linked Data representation of the taxonomy maintained by the french National Museum of Natural History [2]. Then, it enriches this description with  information from three different Web APIs: photos from Flickr, audio recordings from the Macaulay Library, and music tunes from MusicBrainz (the tune Web page URL).

Each SPARQL micro-service is invoked within a dedicated SERVICE clause. If any of the 3 Web APIs is not available (due for instance to a network error or internal failure etc.), the micro-service returns an empty result. In case this happens, the OPTIONAL clauses make it possible to still get (possibly partial) results.

```sparql
prefix rdfs:   <http://www.w3.org/2000/01/rdf-schema#>
prefix owl:    <http://www.w3.org/2002/07/owl#>
prefix foaf:   <http://xmlns.com/foaf/0.1/>
prefix schema: <http://schema.org/>

CONSTRUCT {
    ?species
      schema:subjectOf ?photo; schema:image ?img; schema:thumbnailUrl ?thumbnail;
      schema:contentUrl ?audioUrl;
      schema:subjectOf ?musicPage.
} WHERE {
    SERVICE <http://taxref.mnhn.fr/sparql>
    { ?species a owl:Class; rdfs:label "Delphinus delphis". }
    
    OPTIONAL {
      SERVICE <https://example.org/sparqlms/flickr/getPhotosByGroupByTag?group_id=806927@N20&tags=taxonomy:binomial=Delphinus+delphis>
      { ?photo schema:image ?img; schema:thumbnailUrl ?thumbnail. }
    }

    OPTIONAL {
      SERVICE <https://example.org/sparqlms/macaulaylibrary/getAudioByTaxon?name=Delphinus+delphis>
      { [] schema:contentUrl ?audioUrl. }
    }

    OPTIONAL {
      SERVICE <https://example.org/sparqlms/musicbrainz/getSongByName?name=Delphinus+delphis>
      { [] schema:sameAs ?page. }
    }
}
```

## Generating HTML documentation from a SPARQL Service Description 

To enable discovery of SPARQL micro-services, it is possible to describe them with an RDF graph following the [SPARQL Service Description](https://www.w3.org/TR/2013/REC-sparql11-service-description-20130321/) specification. See examples for services [flickr/getPhotosByTaxon_sd](src/sparqlms/flickr/getPhotosByTaxon_sd/ServiceDescription.ttl) and [macaulaylibrary/getAudioByTaxonCode_sd](src/sparqlms/macaulaylibrary/getAudioByTaxonCode_sd/ServiceDescription.ttl).

Then, such descriptions are translated into an HTML page documenting the service, along with markup data (formatted as JSON-LD) representing the service as a https://schema.org/Dataset. We follow the conventions adopted by [Datahub](https://datahub.ckan.io/) to represent a SPARQL endpoint as a schema:Dataset.

Try it out: the two services mentioned above are deployed live. You can dereference their URLs to obtain the SPARQL Service Description graphs in an RDF serialization format:

```
curl --header "accept: text/turtle" http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxon_sd/
curl --header "accept: text/turtle" http://sms.i3s.unice.fr/sparql-ms/macaulaylibrary/getAudioByTaxon_sd/
```

Furthermore, using content negotiation, the same URLs can be looked up in a web browser, generating their HTML documentation and JSON-LD markup on-the-fly. Simply click on these URLs:
http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxon_sd/ and 
http://sms.i3s.unice.fr/sparql-ms/macaulaylibrary/getAudioByTaxon_sd/.


##  [Installation, configuration and deployment](deployment/README.md)


## Publications

[1] Franck Michel, Catherine Faron-Zucker and Fabien Gandon. *SPARQL Micro-Services: Lightweight Integration of Web APIs and Linked Data*. In Proceedings of the Linked Data on the Web Workshop (LDOW2018). https://hal.archives-ouvertes.fr/hal-01722792

[2] Franck Michel, Olivier Gargominy, Sandrine Tercerie & Catherine Faron-Zucker (2017). *A Model to Represent Nomenclatural and Taxonomic Information as Linked Data. Application to the French Taxonomic Register, TAXREF*. In Proceedings of the 2nd International Workshop on Semantics for Biodiversity (S4BioDiv) co-located with ISWC 2017 vol. 1933. Vienna, Austria. CEUR. https://hal.archives-ouvertes.fr/hal-01617708

[3] Michel F., Zucker C., Gargominy O. & Gandon F. (2018). *Integration of Web APIs and Linked Data Using SPARQL Micro-Services—Application to Biodiversity Use Cases*. Information 9(12):310. DOI: https://dx.doi.org/10.3390/info9120310

#### Poster

Michel F., Faron-Zucker C. & Gandon F. (2018). *Bridging Web APIs and Linked Data with SPARQL Micro-Services*. In The Semantic Web: ESWC 2018 Satellite Events, LNCS vol. 11155, pp. 187–191. Heraklion, Greece. Springer, Cham.

#### Demo

Michel F., Faron-Zucker C. & Gandon F. (2018). *Integration of Biodiversity Linked Data and Web APIs using SPARQL Micro-Services*. In Biodiversity Information Science and Standards, TDWG 2018 Proceedings, vol. 2, p. e25481. Dunedin, New Zealand. Pensoft.
