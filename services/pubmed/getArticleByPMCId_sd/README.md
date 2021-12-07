# Service pubmed/getArticleByPMCId

This service retrieves an aticle from [PubMed](https://pubmed.ncbi.nlm.nih.gov/) using [PMC Entrez APIs](https://www.ncbi.nlm.nih.gov/pmc/tools/developers/), and generates an RDF representation thereof. 
The article is identified by its Pubmed Central ID (PMCID)  starting with `PMC`, provided using property `fabio:hasPubMedCentralId`.

The graph produced relies mainly on the [Bibiographic Ontology](https://github.com/structureddynamics/Bibliographic-Ontology-BIBO) (BIBO) and [FRBR-aligned Bibliographic Ontology](https://sparontologies.github.io/fabio/current/fabio.html) (FaBiO).
An article IRI is preferably based on the article's DOI, if any, prefixed with `http://doi.org/`.
If no DOI is available, the IRI is PubMed's web page URL prefixed with `https://pubmed.ncbi.nlm.nih.gov/`.
Authors are represented in two ways: as separate triples with `dct:creator`, or as an ordered list with `bibo:authorList`.

**Parameters**:
- `fabio:hasPubMedCentralId`: Pubmed Central identifier starting with `PMC`.


## Usage example (SPARQL)
```sparql
prefix bibo:   <http://purl.org/ontology/bibo/> .
SELECT * WHERE {
    ?article 
        fabio:hasPubMedCentralId "PMC5241551";
        ?p ?o.
}
```

## Produced graph example
```turtle
@prefix bibo:    <http://purl.org/ontology//> .
@prefix dce:    <http://purl.org/dc/elements/1.1/> .
@prefix dct:    <http://purl.org/dc/terms/> .
@prefix fabio:  <http://purl.org/spar/fabio/> .

<http://doi.org/10.1097/COH.0000000000000326>
    a                           schema:ScholarlyArticle, bibo:AcademicArticle, fabio:ResearchPaper;
    
    dct:title                   "Lung cancer in persons with HIV.";
    dce:creator                 "Sigel K", "Makinson A", "Thaler J";
    bibo:authorList             ( "Sigel K" "Makinson A" "Thaler J" );
    dct:issued                  "2017 Jan" ;
    
    dct:source                  "Current opinion in HIV and AIDS";
    fabio:journal               "Current opinion in HIV and AIDS";
    bibo:issue                  "1";
    bibo:volume                 "12";
    bibo:numPages               "31-38";
    
    bibo:doi                    "10.1097/COH.0000000000000326";
    bibo:pmid                   "27607596";
    fabio:hasPubMedId           "27607596";
    fabio:hasPubMedCentralId    "PMC5241551";

    schema:url                  <https://pubmed.ncbi.nlm.nih.gov/27607596>;
    .
```
