# Flickr / getPhotosByGroupByTag

This service searches a Flickr group for photos with a given tag.
In a biodiversity-related use case, we use it to search the [*Encyclopedia of Life* Flickr group](https://www.flickr.com/groups/806927@N20) for photos of a given taxon. In this group; photos are required to be tagged with the scientific name of the taxon they represent, formatted as ```taxonomy:binomial=<scientific name>```.

Each photo is depicted as an instance of the `schema:Photograph`, that provides a title (`schema:name`), a link to the photo file in medium size (`schema:image`) and its format (`schema:fileFormat`), a square thumbnail (`schema:thumbnailUrl`), the author and its web page (`schema:author`), and the URL of the photo Web page (`schema:mainEntityOfPage`).

**Path**: flickr/getPhotosByGroupByTag

**Query mode**: SPARQL

**Parameters**:
- group_id: identifier of the Flickr group (found in the group URL). For instance,  in https://www.flickr.com/groups/806927@N20 the group_id is 806927@N20.
- tags: list of tags


## Example of triples produced

    <http://example.org/ld/flickr/photo/31173091626>
        a schema:Photograph;
        schema:name "Delphinus delphis 5 (13-7-16 San Diego)";
        schema:image <https://farm6.staticflickr.com/5718/31173091626_88c410c3f2_z.jpg>;
        schema:fileFormat "image/jpeg";
        schema:thumbnailUrl <https://farm6.staticflickr.com/5718/31173091626_88c410c3f2_s.jpg>;
        schema:mainEntityOfPage <https://flickr.com/photos/10770266@N04/31173091626>;
        schema:author [ schema:name ""; schema:url <https://flickr.com/photos/10770266@N04> ].

## Usage example

    prefix schema: <http://schema.org/>
    
    SELECT * WHERE {
      SERVICE <https://example.org/sparql-ms/flickr/getPhotosByGroupByTag?group_id=806927@N20&tags=taxonomy:binomial=Delphinus+delphis>
        { ?photo schema:image ?img; schema:thumbnailUrl ?thumbnail.  }
    }
    