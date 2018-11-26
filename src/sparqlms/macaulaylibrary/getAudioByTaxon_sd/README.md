# Macauley Library / getAudioByTaxon_sd

This service retrieves audio recordings for a given taxon name from the [Macaulay Library](https://www.macaulaylibrary.org/), a scientific media archive related to birds, amphibians, fishes and mammals.

Each recording is depicted as an instance of the `schema:AudioObject` class that provides a link to the audio file (`schema:contentUrl`), the author (`schema:author`), a thumbnail (`schema:thumbnailUrl`), a description (`schema:description`) and the URL of the related Web page (`schema:mainEntityOfPage`).

**Path**: `macaulaylibrary/getAudioByTaxon_sd`

**Query mode**: SPARQL

**Input**:
- object of property dwc:scientificName: taxonomic name


## Example of triples produced

    [] a dwc:Taxon;
        dwc:scientificName "Delphinus delphis";
        schema:audio <http://example.org/ld/macaulaylibrary/audio/id/111690>.

    <http://example.org/ld/macaulaylibrary/audio/id/111690>
        a schema:AudioObject;
        schema:contentUrl <https://download.ams.birds.cornell.edu/api/v1/asset/111690/audio>;
        schema:thumbnailUrl <https://macaulaylibrary.org/media/Spectrograms/audio/poster/220/0/111/111690.jpg>;
        schema:mainEntityOfPage <https://macaulaylibrary.org/asset/111690>;
        schema:author "Paul J. Perkins";
        schema:description "NOTES ...".

## Usage example

    prefix schema: <http://schema.org/>
    prefix dwc: <http://rs.tdwg.org/dwc/terms/>

    SELECT ?audioUri ?audioFile ?description WHERE {

        ?taxon a dwc:Taxon;
            dwc:scientificName "Delphinus delphis";
            schema:audio ?audioUri.

        ?audio a schema:AudioObject;
            schema:contentUrl ?audioFile;
            schema:description ?description.
    }
    