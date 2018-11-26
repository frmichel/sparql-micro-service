# Biodiversity Heritage Library / getArticlesByTaxon

This service searches the [Biodiversity Heritage Library](https://www.biodiversitylibrary.org/) for scientific articles related to a given taxon name.

Each article is represented by an instance of the `schema:ScholarlyArticle` class. It comes with a title (`schema:name`), a publication date (`schema:datePublished`), authors (`schema:author`), the article's Web page (`schema:mainEntityOfPage`), the first page's URL (`schema:mainEntityOfPage`) and thumbnail (`schema:thumbnailUrl`), and the details about the journal in which it was published (`schema:isPartOf`).


**Path**: bhl/getArticlesByTaxon

**Query mode**: SPARQL

**Parameters**:
- name: a taxon name


## Example of triples produced

```turtle
<http://example.org/ld/bhl/part/73414>
    a schema:ScholarlyArticle;
    schema:name             "5. On the Common Dolphin, Delphinus delphis. Linn";
    schema:mainEntityOfPage <https://www.biodiversitylibrary.org/part/73414>;
    schema:author           "Flower, William Henry,";
    schema:datePublished    "1879";

    schema:thumbnailUrl     <https://www.biodiversitylibrary.org/pagethumb/28521490>;
    schema:hasPart          "https://www.biodiversitylibrary.org/page/28521490";
    schema:pageStart        "382";
    schema:pageEnd           "384";
    schema:isPartOf [
        a schema:PublicationEvent;
        schema:datePublished "1879";
        schema:issueNumber  "";
        schema:name         "Proceedings of the Zoological Society of London.";
        schema:publisher    "London :Academic Press, [etc.],1833-1965.";
        schema:volumeNumber "1879"
    ].
```

## Usage example

```sparql
prefix schema: <http://schema.org/>

SELECT * WHERE {
    SERVICE <https://example.org/sparql-ms/bhl/getArticlesByTaxon?name=Delphinus+delphis>
    { ?article          a schema:ScholarlyArticle;
        schema:name     ?articleTitle;
        schema:author   ?authorName;
        schema:isPartOf [ schema:name ?articleContainerTitle ].
    }
}
```
    