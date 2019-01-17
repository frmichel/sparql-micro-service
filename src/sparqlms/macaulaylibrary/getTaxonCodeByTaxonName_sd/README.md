# macaulaylibrary/getTaxonCodeByTaxonName_sd

Retrieve the internal [Macaulay Library](https://www.macaulaylibrary.org/)'s code(s) associated to a scientific taxon name.

**Query mode**: SPARQL

**Input**:
- object of property `dwc:scientificName`: taxonomic name


## Produced graph example

```turtle
[]  a dwc:Taxon;
    dwc:scientificName "Delphinus delphis";
    schema:identifier "t-11034463", "t-12037971", "t-12037972";
    .
```

## Usage example

```sparql
prefix schema: <http://schema.org/>
prefix dwc: <http://rs.tdwg.org/dwc/terms/>

SELECT ?taxonCode WHERE {

    []  a dwc:Taxon;
        dwc:scientificName "Delphinus delphis";
        schema:identifier ?taxonCode.
}```
