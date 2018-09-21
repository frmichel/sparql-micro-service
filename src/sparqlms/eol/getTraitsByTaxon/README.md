# Encyclopedia of Life / getTraitsByTaxon

This service searches the [Encyclopedia of Life traits bank](http://eol.org/traitbank) for data related to a given taxon name.

The EoL trait bank v2 provides a JSON-LD representation using various vocabularies including Darwin Core terms.
Unfortunately, the JSON-LD is invalid (see https://github.com/EOL/eol/issues/139) and thus cannot be parsed directly. Hence, we trick the process to still be able to work with it: (i) an additional profile (profile.jsonld) ignores (invalid) field ```vernacularNames```, and sets the @base tag to give resources a fixed URI; (ii) we modififed the JSON-LD php library to accept ```@id``` fields with a non-string value: the value is converted to a string.

As a result, a query to the trait bank with a URL such as http://eol.org/api/traits/314276 returns the JSON-LD content is in file
```example.jsonld```, and gets translated into the RDF depicted in file ```example.turtle```. This RDF remains questionable as it makes a bad use of Darwin Core terms: it uses reglar dwc: properties with URI objects although these are meant for literals only. The properties in namespace dwciri: have been introduced to address this isse (see the [Darwin Core RDF Guide](http://rs.tdwg.org/dwc/terms/guides/rdf/index.htm)). The example query below fixes this by constructing a graph using proper dwciri predicates.


**Path**: eol/getTraitsByTaxon

**Query mode**: SPARQL

**Parameters**:
- name: a taxon name

## Example query

    CONSTRUCT {
        ?measure
            a                      dwc:MeasurementOrFact;
            dwciri:measurementType ?measurementType;
            dwciri:measurementUnit ?measurementUnit;
            dwc:measurementValue   ?measurementValue;
            schema:name            ?measurePredicate.
    } WHERE {
        SERVICE SILENT <https://example.org/sparql-ms/eol/getTraitsByTaxon?name=Delphinus+delphis>
        { ?measure                 a dwc:MeasurementOrFact;
            dwciri:measurementType ?measurementType;
            dwciri:measurementUnit ?measurementUnit;
            dwc:measurementValue   ?measurementValue;
            schema:predicate       ?measurePredicate.
          OPTIONAL { ?measure      dwc:measurementUnit ?measurementUnit }
          FILTER (?measurePredicate = "total life span" || ?measurePredicate = "body length (VT)" || ?measurePredicate = "conservation status")
        }
    }

## Triples produced by the example query

    @prefix dwc:    <http://rs.tdwg.org/dwc/terms/> .
    @prefix dwciri: <http://rs.tdwg.org/dwc/iri/> .

    <http://eol.org/pages/314276/data#data_point_716247>
        rdf:type                dwc:MeasurementOrFact ;
        schema:name             "total life span"^^xsd:string ;
        dwc:measurementValue    "240"^^xsd:string .
        dwciri:measurementType  "http://purl.obolibrary.org/obo/VT_0001661"^^xsd:string ;
        dwciri:measurementUnit  "http://purl.obolibrary.org/obo/UO_0000035"^^xsd:string .
        
    <http://eol.org/pages/314276/data#data_point_716249>
        rdf:type                dwc:MeasurementOrFact ;
        schema:name             "body length (VT)"^^xsd:string ;
        dwc:measurementValue    "2439.99"^^xsd:string ;
        dwciri:measurementType  "http://purl.obolibrary.org/obo/VT_0001256"^^xsd:string ;
        dwciri:measurementUnit  "http://purl.obolibrary.org/obo/UO_0000016"^^xsd:string .

    <http://eol.org/pages/314276/data#data_point_45845581>
        rdf:type                dwc:MeasurementOrFact ;
        schema:name             "conservation status"^^xsd:string ;
        dwciri:measurementValue "http://eol.org/schema/terms/leastConcern"^^xsd:string ;
        dwciri:measurementType  "http://rs.tdwg.org/ontology/voc/SPMInfoItems#ConservationStatus"^^xsd:string .
