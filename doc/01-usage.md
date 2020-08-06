# How to use SPARQL micro-services

As any regular SPARQL endpoint, a SPARQL micro-service expects at least a SPARQL query. Additionally, it usually expects arguments that it will use to invoke the Web API.

__Two different flavours__ exist with respect to how arguments are passed to a SPARQL micro-service: 
- as parameters on the HTTP query string of the endpoint URL, or
- as values within the SPARQL query graph pattern.

The method to use depends on how the SPARQL micro-service is described (see the [service configuration](02-config.md) page).

### Passing arguments on the HTTP query string

The query below exemplifies the first flavour. It retrieves information related to the common dolphin species (*Delphinus delphis*) from the Macaulay Library. The taxon name is passed as a parameter on the HTTP query string of the SPARQL micro-service URL, ```?name=Delphinus+delphis```:

```sparql
SERVICE <https://example.org/macaulaylibrary/getAudioByTaxon?name=Delphinus+delphis>
{
  SELECT ?audioUrl WHERE {
    [] schema:contentUrl ?audioUrl.
  }
}
```

The arguments expected by the micro-service (```name``` in this case) are listed in the service's config.ini file, in this example [macaulaylibrary/getAudioByTaxon/config.ini](../services/macaulaylibrary/getAudioByTaxon/config.ini).


### Passing arguments within the SPARQL query graph pattern

The query below exemplifies the second flavour. It is equivalent to the one above but the taxon name is provided as part of the graph pattern with predicate ```dwc:scientificName```:

```sparql
SERVICE <https://example.org/macaulaylibrary/getAudioByTaxon_sd>
{
  SELECT ?audioUrl WHERE {
    [] a dwc:Taxon;
       dwc:scientificName   "Delphinus delphis";
       schema:audio [
         schema:contentUrl  ?audioUrl;
       ].
  }
}
```

The arguments expected by the micro-service (```name``` in this case) are described in the service description (file [macaulaylibrary/getAudioByTaxon_sd/ServiceDescription.ttl](../services/macaulaylibrary/getAudioByTaxon_sd/ServiceDescription.ttl)) either using the [Hydra](https://www.hydra-cg.com/spec/latest/core/) vocabulary or by pointing to a property shape within the [shapes graph](../services/macaulaylibrary/getAudioByTaxon_sd/ShapesGraph.ttl) that describes the type of graph that this micro-service can spawn (See the [SHACL](https://www.w3.org/TR/shacl/) recommendation).


# Forcing the order of evaluating multiple SPARQL micro-services

In the second example above, the values of the input paramter are provided by RDF terms of the SPARQL query graph pattern.
In some use cases, these values should come from variables evaluated elsewhere in the SPARQL query.

Let us take an example. The query below invokes two SPARQL micro-services:
  * the first one, gbif/getTaxonByID_sd, returns the name corresponding to an identifier given by property `dwc:scientificNameID`;
  * the second one, flickr/getPhotosByTaxon_sd, returns photos matching the name given by property `dwc:scientificName` and that should be provided by the first one.

```sparql
# First example. This query fails.
prefix schema: <http://schema.org/>
prefix dwc: <http://rs.tdwg.org/dwc/terms/>

SELECT ?name ?img  WHERE  {
 
  SERVICE <https://example.org/gbif/getTaxonByID_sd> {		
    SELECT ?name WHERE {
      ?taxon
        dwc:scientificNameID        "2360305";
        dwc:scientificName          ?name.
    }
  }

  SERVICE <https://example.org/flickr/getPhotosByTaxon_sd/> {		
    SELECT ?img WHERE {
        ?taxon
            dwc:scientificName  ?name;
            schema:image        [ schema:contentUrl ?img ].
    }
  }
}
```

Hence, the second one cannot be invoked straight away. It needs values retrieved from the first service. But we cannot know in advance the strategy that the SPARQL engine that evaluates this query will use. It may well invoke the second service first, which would fail since the input parameter is not provided.

To workaround this issue, we must instruct the SPARQL engine to evaluate the first service, and then inject the values of variable ?name into the second service. This can be achieved simply as demonstrated below:

```sparql
prefix schema: <http://schema.org/>
prefix dwc: <http://rs.tdwg.org/dwc/terms/>

SELECT ?name ?img  WHERE  {
 
  SERVICE <https://example.org/gbif/getTaxonByID_sd> {		
    SELECT ?name WHERE {
      ?taxon
        dwc:scientificNameID        "2360305";
        dwc:scientificName          ?name.
    }
  }

  # Force evaluating ?name first
  BIND (?name as ?name2)

  SERVICE <https://example.org/flickr/getPhotosByTaxon_sd/> {		
    SELECT ?img WHERE {
        ?taxon
            dwc:scientificName  ?name2;
            schema:image        [ schema:contentUrl ?img ].
    }
  }
}
```

Now, we have split ?name into two variables ?name and ?name2. Line `BIND (?name as ?name2)` forces the SPARQL engine to evaluate the first service to get values of variable ?name. Then, it assigns those values to ?name2. 
This *should* results in the second invocation to be done along with the values, typically provided as an extra VALUES clause.

**Note however that the way the second service is invoked totally depends on the strategy of the SPARQL engine being used. Hence, the behavior may vary from one engine to the other.**
