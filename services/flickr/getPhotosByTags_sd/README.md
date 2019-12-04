# flickr/getPhotosByTags_sd

This SPARQL micro-service searches photos on [Flickr](https://flickr.com), that match all of the given tags.

**Query mode**: SPARQL

**Input**:
- object of property schema:keywords (multiple values allowed)


## Produced graph example

```turtle
<http://example.org/ld/flickr/photo/41926925170>
    a                       schema:Photograph;
    schema:name             "T-Top Corvette";
    schema:dateCreated      "2018-07-30 08:01:00";
    schema:keywords         "brooklyn", "bicycle", "car", "vintage", "corvette";
    schema:contentUrl       <https://farm1_staticflickr.com/854/41926925170_459e3a473a_z.jpg>;
    schema:thumbnailUrl     <https://farm1.staticflickr.com/854/41926925170_459e3a473a_s.jpg>;
    schema:mainEntityOfPage <https://flickr.com/photos/15782558@N07/41926925170>;
    schema:fileFormat       "image/jpeg";
    schema:author [ 
        a                   schema:Person;
        schema:name         "Shu-Sin"; 
        schema:identifier   "15782558@N07";
        schema:url          <https://flickr.com/photos/15782558@N07> ].
```

        
## Usage example

```sparql
prefix schema: <http://schema.org/>

SELECT ?photo ?title ?img WHERE {

    ?photo a                schema:Photograph;
        schema:keywords     "brooklyn", "bicycle";
        schema:name         ?title;
        schema:contentUrl   ?img.
}
```
