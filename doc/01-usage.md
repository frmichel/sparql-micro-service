# How to use SPARQL micro-services

As any regular SPARQL endpoint, a SPARQL micro-service expects a SPARQL query. Additionally, it usually expects arguments that it will use to call the Web API.

__Two different flavours__ exist with respect to how arguments are passed to a SPARQL micro-service: 
- as parameters on the HTTP query string of the endpoint URL, or
- as values within the SPARQL query graph pattern.

The method to use depends on how the SPARQL micro-service is described (see the [service configuration](/doc/02-config.md) page).

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

The arguments expected by the micro-service (```name``` in this case) are described in the service description (file [macaulaylibrary/getAudioByTaxon/ServiceDescription.ttl](/src/sparqlms/macaulaylibrary/getAudioByTaxon/ServiceDescription.ttl)) either using the [Hydra](https://www.hydra-cg.com/spec/latest/core/) vocabulary or by pointing to a property shape within the [SHACL](https://www.w3.org/TR/shacl/) graph that describes the type of graph that this micro-service can spawn.   
