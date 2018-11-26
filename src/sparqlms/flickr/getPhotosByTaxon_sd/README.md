# Flickr / getPhotosByTaxon_sd

This service searches Flickr photos related to a certain taxonomic name. It relies on the common conventioned used e.g. in the [*Encyclopedia of Life*](https://www.flickr.com/groups/806927@N20) Flickr group and the [*Biodiversity Heritage Library*](https://www.flickr.com/photos/biodivlibrary) where a taxonomic name is given by tag formatted as ```taxonomy:binomial=<scientific name>```.

**Path**: flickr/getPhotosByTaxon_sd

**Query mode**: SPARQL

**Input**:
- object of property dwc:scientificName: taxonomic name


## Example of triples produced

```turtle
[] a dwc:Taxon;
    dwc:scientificName "Delphinus delphis";
    schema:image <http://example.org/ld/flickr/photo/31173091626>.

<http://example.org/ld/flickr/photo/31173091626>
    a schema:Photograph;
    schema:name "Delphinus delphis 5 (13-7-16 San Diego)";
    schema:contentUrl <https://farm6.staticflickr.com/5718/31173091626_88c410c3f2_z.jpg>;
    foaf:depiction <https://farm6.staticflickr.com/5718/31173091626_88c410c3f2_z.jpg>;
    schema:thumbnailUrl <https://farm6.staticflickr.com/5718/31173091626_88c410c3f2_s.jpg>;
    schema:mainEntityOfPage <https://flickr.com/photos/10770266@N04/31173091626>;
    schema:fileFormat "image/jpeg";
    schema:author [ schema:identifier "10770266@N04"; schema:url <https://flickr.com/photos/10770266@N04> ].
```

        
## Usage example

```sparql
prefix schema: <http://schema.org/>
prefix dwc: <http://rs.tdwg.org/dwc/terms/>

SELECT ?photo ?title ?img WHERE {

    ?taxon a dwc:Taxon;
        dwc:scientificName "Delphinus delphis";
        schema:image ?photo.

    ?photo a schema:Photograph;
        schema:name ?title;
        schema:contentUrl ?img.
}
```
