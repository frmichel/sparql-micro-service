# Musicbrainz / getSongByName

This service searches the [MusicBrainz music information encyclopedia](https://musicbrainz.org/) for music tunes whose titles match a given name with a minimum confidence of 90%.

Each tune consists of a work (with no URIblank node), having a Web page URL (`schema:sameAs`) and a title (`schema:name`).

**Path**: `musicbrainz/getSongByName

**Query mode**: SPARQL

**Parameters**:
- `name`: taxon name


## Example of triples produced

    []  schema::name "Delphinus delphis";
        schema::sameAs <https://musicbrainz.org/work/3ffe21ec-7d58-44cb-a65a-5f5876e12b50>.


## Usage example

    prefix schema: <http://schema.org/>
    
    SELECT ?musicPage WHERE {
        SERVICE <https://example.org/sparql-ms/musicbrainz/getSongByName?name=Delphinus+delphis>
        { [] schema:sameAs ?musicPage. } }
    }
