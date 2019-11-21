# flickr/getPhotoById

This service retrives a Flickr photo by its identifier.
It is mostly meant to dereference photo URIs produced in the flickr/getPhotosByGroupByTag service, but can also be use with SPARQL.

Each photo is represented by an instance of the `schema:Photograph`, that provides a title (`schema:name`), a description (`schema:description`), a link to the photo file in medium size (`schema:contentUrl` and `foaf:depiction`) and its format (`schema:fileFormat`), a square thumbnail (`schema:thumbnailUrl`), the author and its web page (`schema:author`), and the URL of the photo Web page (`schema:mainEntityOfPage`).

**Query mode**: dereferencing to RDF content, SPARQL

**Parameters**:
- photo_id: Flickr's internal photo identifier


## Produced graph example

```turtle
<http://example.org/ld/flickr/photo/31173091626>
    a schema:Photograph;
    schema:name "Delphinus delphis 5 (13-7-16 San Diego)";
    schema:description "" ;
    schema:image <https://farm6.staticflickr.com/5718/31173091626_88c410c3f2_z.jpg>;
    schema:fileFormat "image/jpeg";
    schema:thumbnailUrl <https://farm6.staticflickr.com/5718/31173091626_88c410c3f2_s.jpg>;
    schema:mainEntityOfPage <https://flickr.com/photos/10770266@N04/31173091626>;
    schema:author [ schema:name ""; schema:url <https://flickr.com/photos/10770266@N04> ].
```

## Usage example (SPARQL)

```sparql
prefix schema: <http://schema.org/>

SELECT * WHERE {
  SERVICE <https://example.org/sparql-ms/flickr/getPhotoById?photo_id=31173091626>
    { ?photo schema:image ?img; schema:thumbnailUrl ?thumbnail.  }
}
```

## Usage example (dereferencing)

    curl --header "Accept:text/turtle" http://example.org/ld/flickr/photo/31173091626
