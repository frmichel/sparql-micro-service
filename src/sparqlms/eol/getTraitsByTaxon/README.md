# eol/getTraitsByTaxon

This service searches the [Encyclopedia of Life traits bank](http://eol.org/traitbank) for data related to a given taxon name.
It uses the EoL trait bank v3 API that provides a [Neo4J Cypher](https://neo4j.com/docs/cypher-manual/current/) interface.

Cypher query results are JSON-based, therefore the SPARQL micro-service uses the same process as with any other JSON-based Web APIs to translate the response into RDF: first it applies a JSON-LD profile, then it runs an INSERT query to augment the produced graph with additional triples using domain ontologies.

The produced graph consists of dwc:MeasurementOrFact instances having at least a measurement type and value.

**Query mode**: SPARQL

**Parameters**:
- name: a taxon name

## Produced graph example

The graph below is the result of translating the response exemplified in [example.json](example.json).

```turtle
@prefix dwc:    <http://rs.tdwg.org/dwc/terms/> .
@prefix dwciri: <http://rs.tdwg.org/dwc/iri/> .
@prefix api: <http://ns.inria.fr/sparql-micro-service/api#> .
@prefix dwc: <http://rs.tdwg.org/dwc/terms/> .
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .

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
SELECT * WHERE {
    SERVICE SILENT <https://example.org/sparql-ms/eol/getTraitsByTaxon?name=Delphinus+delphis>
    {   ?measure 
            a                       dwc:MeasurementOrFact;
            dwc:measurementType     ?measurementType;
            dwc:measurementValue    ?measurementValue.

        OPTIONAL { ?measure dwc:measurementUnit     ?measurementUnit }
        OPTIONAL { ?measure dwciri:measurementUnit  ?measurementUnitUri }

        FILTER (?measurementType  != 'habitat includes')
    }
}
```
