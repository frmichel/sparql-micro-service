# Macauley Library / getAudioByTaxon

This service retrieves audio recordings for a given taxon name from the [Macaulay Library](https://www.macaulaylibrary.org/), a scientific media archive related to birds, amphibians, fishes and mammals.

Each recording is depicted as an instance of the `schema:AudioObject` class that provides a link to the audio file (`schema:contentUrl`), the author (`schema:author`), a thumbnail (`schema:thumbnailUrl`), a description (`schema:description`) and the URL of the related Web page (`schema:mainEntityOfPage`).

**Path**: `macaulaylibrary/getAudioByTaxon`

**Query mode**: SPARQL

**Parameters**:
- `name`: taxon name


## Example of triples produced

    <http://example.org/ld/macaulaylibrary/audio/id/111690>
        a schema:AudioObject;
        schema:contentUrl <https://download.ams.birds.cornell.edu/api/v1/asset/111690/audio>;
        schema:thumbnailUrl <https://macaulaylibrary.org/media/Spectrograms/audio/poster/220/0/111/111690.jpg>;
        schema:mainEntityOfPage <https://macaulaylibrary.org/asset/111690>;
        schema:author "Paul J. Perkins";
        schema:description "NOTES ...".

## Usage example

    prefix schema: <http://schema.org/>

    SELECT ?page WHERE {
      SERVICE <https://example.org/sparql-ms/macaulaylibrary/getAudioByName?name=Delphinus+delphis>
      { [] schema:sameAs ?page. }
    }
