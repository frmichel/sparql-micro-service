# eol/getTraitsByTaxon_sd

This service searches the [Encyclopedia of Life traits bank](http://eol.org/traitbank) for data related to a given taxon name.
It uses the EoL trait bank v3 API that provides a [Neo4J Cypher](https://neo4j.com/docs/cypher-manual/current/) interface.

Cypher query results are JSON-based, therefore the SPARQL micro-service uses the same process as with any other JSON-based Web APIs to translate the response into RDF: first it applies a JSON-LD profile, then it runs an CONSTRUCT query to augment the produced graph with additional triples using domain ontologies.

The produced graph consists of a ```dwc:Taxon``` instance and ```dwc:MeasurementOrFact``` instances having at least one measurement type and value.

**Query mode**: SPARQL

**Parameters**:
- object of property ```dwc:scientificName```: taxonomic name


## Produced graph example


```turtle
@prefix dwc:    <http://rs.tdwg.org/dwc/terms/> .
@prefix dwciri: <http://rs.tdwg.org/dwc/iri/> .
@prefix dct:    <http://purl.org/dc/terms/>.

[] a dwc:Taxon;
    dwc:scientificName "Delphinus delphis";
    dct:relation _:b8187, _:b8186.

_:b8187 
    a                       dwc:MeasurementOrFact;
    dwc:measurementType     "habitat is";
    dwciri:measurementType  <http://rs.tdwg.org/dwc/terms/habitat>;
    dwc:measurementValue    "marine habitat", <http://purl.obolibrary.org/obo/ENVO_00000569>.
    
_:b8186 
    a                       dwc:MeasurementOrFact;
    dwc:measurementType     "body mass";
    dwciri:measurementType  <http://purl.obolibrary.org/obo/VT_0001259>;
    dwc:measurementUnit     "g";
    dwciri:measurementUnit  <http://purl.obolibrary.org/obo/UO_0000021>;
    dwc:measurementValue    "7069.65".
```

## Usage example

```turtle
prefix dwc:    <http://rs.tdwg.org/dwc/terms/>
prefix dwciri: <http://rs.tdwg.org/dwc/iri/>
prefix dct:    <http://purl.org/dc/terms/>

SELECT * WHERE {
    ?taxon a dwc:Taxon;
        dwc:scientificName "Delphinus delphis";
        dct:relation ?measure.
        
    ?measure 
        a                       dwc:MeasurementOrFact;
        dwc:measurementType     ?measurementType;
        dwc:measurementValue    ?measurementValue.

    OPTIONAL { ?measure dwc:measurementUnit     ?measurementUnit }
    OPTIONAL { ?measure dwciri:measurementUnit  ?measurementUnitUri }

    FILTER (?measurementType = "habitat includes")
}
```
