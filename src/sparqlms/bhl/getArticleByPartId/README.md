# bhl/getArticleByPartId

This service retrives a scientific article from the [Biodiversity Heritage Library](https://www.biodiversitylibrary.org/) (BHL).
It is mostly meant to dereference article URIs produced in the bhl/getArticlesByTaxon service, but can also be use with SPARQL.

Each article is represented by an instance of the `schema:ScholarlyArticle` class. It comes with a title (`schema:name`), a publication date (`schema:datePublished`), authors (`schema:author`), the article's Web page (`schema:mainEntityOfPage`), the first page's URL (`schema:mainEntityOfPage`) and thumbnail (`schema:thumbnailUrl`), and the details about the journal in which it was published (`schema:isPartOf`).

**Query mode**: dereferencing to RDF content, SPARQL

**Parameters**:
- partId: BHL's internal article identifier


## Produced graph example

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
    schema:pageEnd          "384";
    schema:isPartOf [
        a schema:PublicationEvent;
        schema:datePublished "1879";
        schema:issueNumber  "";
        schema:name         "Proceedings of the Zoological Society of London.";
        schema:publisher    "London :Academic Press, [etc.],1833-1965.";
        schema:volumeNumber "1879"
    ].
```

## Usage example (SPARQL)

```sparql
prefix schema: <http://schema.org/>

SELECT * WHERE {
    SERVICE <https://example.org/sparql-ms/bhl/getArticleByPartId?partId=73414>
    { ?article          a schema:ScholarlyArticle;
        schema:name     ?articleTitle;
        schema:author   ?authorName;
        schema:isPartOf [ schema:name ?articleContainerTitle ].
    }
}
```

## Usage example (dereferencing)

    curl --header "Accept:text/turtle" http://example.org/ld/bhl/part/73414
