# How to use SPARQL micro-services

As any regular SPARQL endpoint, a SPARQL micro-service expects at least a SPARQL query. Additionally, it usually expects arguments that it will use to invoke the Web API.

__Two different flavours__ exist with respect to how arguments are passed to a SPARQL micro-service: 
- as parameters on the HTTP query string of the endpoint URL, or
- as values within the SPARQL query graph pattern.

The method to use depends on how the SPARQL micro-service is described (see the [service configuration](02-config.md) page).

### Passing arguments on the HTTP query string

The query below exemplifies the first flavour. It retrieves information about Eminem's albums from Deezer. The artist's name is passed as a parameter on the HTTP query string of the SPARQL micro-service URL, ```?name=eminem```:

```sparql
prefix schema: <http://schema.org>.
SERVICE <https://example.org/deezer/findAlbums?name=eminem>
{
  SELECT * WHERE {
    [] schema:MusicAlbum;
       schema:name ?name.
  }
}
```

The argument expected by the micro-service (```name```) is given in the service's config.ini file, in this example [deezer/findAlbums/config.ini](../services/deezer/findAlbums/config.ini).


### Passing arguments within the SPARQL query graph pattern

The query below exemplifies the second flavour. 
It retrieves articles from PubMed using by their PubMed identifier (PMID) provided using property bibo:pmid.

```sparql
prefix bibo:   <http://purl.org/ontology/bibo/>.
prefix dct:    <http://purl.org/dc/terms/>.
SERVICE <https://sparql-micro-services.org/service/pubmed/getArticleByPMId_sd/>
{
  SELECT * WHERE {
      ?a1 bibo:pmid "27607596".
      ?s dct:creator ?author.
  }
}
```

The argument expected by the micro-service is described in the service description (file [pubmed/getArticleByPMId_sd/ServiceDescription.ttl](../services/pubmed/getArticleByPMId_sd/ServiceDescription.ttl)).


# Forcing the order of evaluating multiple SPARQL micro-services

In the second example above, the values of the input paramter are provided by RDF terms of the SPARQL query graph pattern.
In some use cases, these values should come from variables evaluated elsewhere in the SPARQL query.

Let us take an example. The query below invokes two SPARQL micro-services:
  * the first one, gbif/getTaxonByID_sd, returns the name corresponding to an identifier given by property `dwc:scientificNameID`;
  * the second one, flickr/getPhotosByTaxon_sd, returns photos matching the name given by property `dwc:scientificName` and that should be provided by the first micro-service.

Here, gbif/getTaxonByID_sd needs to be invoked first, so that the values it returns be used to invoke flickr/getPhotosByTaxon_sd.

Such that the query below might fail:

```sparql
# First example. This query may fail.
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

The second micro-service cannot be invoked straight away. It needs values retrieved from the first micro-service. But we cannot know in advance the strategy that the SPARQL engine that evaluates this query will use. It may well invoke the second service first, which would fail since the input parameter is not provided.

As a workaround to this issue, we must instruct the SPARQL engine to evaluate the first service, and then "inject" the values of variable ?name into the second service. This can be achieved simply as demonstrated below:

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
This *should* result in the second invocation to be done along with the values, typically provided as an extra VALUES clause.

**Note however that the way the second service is invoked totally depends on the strategy of the SPARQL engine being used. Hence, the behavior may vary from one engine to the other.**
