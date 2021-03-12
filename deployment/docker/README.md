# Prototype and deploy your SPARQL micro-services with Docker

You can prototype and deploy SPARQL micro-services using the Docker images we have built and published on Docker hub.

  - [frmichel/sparql-micro-service](https://hub.docker.com/r/frmichel/sparql-micro-service/) contains the core code to run SPARQL-micro-services, running on an Apache Web server with PHP, configured to serve the SPARQL micro-services. Apache listens on port 80.

  - [frmichel/corese](https://hub.docker.com/r/frmichel/corese/) contains the [Corese-KGRAM](http://wimmics.inria.fr/corese) RDF store and SPARQL endpoint. It is used to execute SPARQL queries and generate the HTML documentation of the service described with a [service descriptions](../../doc/02-config.md#configuration-with-a-sparql-service-description-file) file (does not apply to services configured [with a config.ini file](../../doc/02-config.md#configuration-with-file-configini)).

Both images requires micro-services to be installed in the `services` directory created in the folder where you run docker-compose.


## Run the Docker containers

To start running SPARQL micro-services, simply download the file [docker-compose.yml](docker-compose.yml) on a Docker server and run:

```
docker-compose up -d
```

Wait a few seconds for Corese to initialize properly. 

### Test your own SPARQL micro-services

All micro-services must be installed in the `services` directory created in the folder where you have run docker-compose.

As an example, copy services [deezer/findAlbums](../../services/deezer/findAlbums) and [musicbrainz/getSongByName](../../services/musicbrainz/getSongByName) into directory `services`, and give them full read access rights so that the Docker container can read them (**this is important**).

Assuming variable $SMS_INSTAL gives the directory where you checked out the Github repository:

```bash
cp -r $SMS_INSTAL/services/deezer/findAlbums services
cp -r $SMS_INSTAL/services/musicbrainz/getSongByName services
chmod -R 755 services/*
```

Then, you can test the services using the commands below in a bash.

```bash
# URL-encoded query: select * where {?s ?p ?o}
SELECT='select%20*%20where%20%7B%3Fs%20%3Fp%20%3Fo%7D'

# URL-encoded query: construct where {?s ?p ?o}
CONSTRUCT=construct%20WHERE%20%7B%20%3Fs%20%3Fp%20%3Fo%20%7D%20

curl --header "Accept: application/sparql-results+json" \
  "http://localhost/service/deezer/findAlbums?query=${SELECT}&keyword=eminem"

curl --header "Accept: text/turtle" \
  "http://localhost/service/deezer/findAlbums?query=${SELECT}&keyword=eminem"

curl --header "Accept: application/sparql-results+json" \
  "http://localhost/service/musicbrainz/getSongByName?query=${SELECT}&name=Love"
```

### Possible conflict on port 80

This deployment uses ports 80 and 8081 of the Docker host. If they are in conflict with other aplications, change the port mapping in `docker-compose.yml`.

### Check application logs

This `docker-compose.yml` will mount the SPARQL micro-service and Corese-KGRAM log directories to the Docker host in directory `./logs`.
You may have to set rights 777 on this directory for the container to be able to write log files (`chmod 777 logs`).


## Test URI dereferencing

The Docker image is also configured to support URI dereferencing for some of the SPARQL micro-services that we provide. You can test this with Flickr:

```bash
cp -r $SMS_INSTAL/services/flickr services
chmod -R 755 services/*
```

Configure your own Flckr API key in the `flickr/getPhotoById/config.ini` (replace the string `<api_key>`).


The, enter this URL in your browser: http://localhost/ld/flickr/photo/31173091246 or the following command in a bash:

```bash
curl --header "Accept: text/turtle" http://localhost/ld/flickr/photo/31173091246
```

This should return an RDF description of the photographic resource similar to:

```turtle
@prefix schema: <http://schema.org/> .
@prefix cos: <http://www.inria.fr/acacia/corese#> .
@prefix dce: <http://purl.org/dc/elements/1.1/> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix sd: <http://www.w3.org/ns/sparql-service-description#> .
@prefix ma: <http://www.w3.org/ns/ma-ont#> .
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .

<http://localhost/ld/flickr/photo/31173091516>
    rdf:type schema:Photograph ;
    dce:title           "Delphinus delphis 1 (13-7-16 San Diego)" ;
    schema:author       <https://flickr.com/photos/10770266@N04> ;
    schema:subjectOf    <https://www.flickr.com/photos/10770266@N04/31173091516/> ;
    schema:thumbnailUrl <https://farm6.staticflickr.com/5567/31173091516_f1c09fa5d5_q.jpg> ;
    schema:image        <https://farm6.staticflickr.com/5567/31173091516_f1c09fa5d5_z.jpg> .
```

## Services configured with ServiceDescription graph

The Docker images also support micro-services configured with a [service description](../../doc/02-config.md#configuration-with-a-sparql-service-description-file).

Simply copy the services in the `services` directory, and restart the Corese Docker container as it loads the servide descriptions at start-up.

You can then test the HTML documentation generation by entering the following URL in a web browser:
```
http://localhost/service/<your_api>/<you_service>
```

You can also look up the URIs of the service description and shapes graphs directly, e.g.:
```
curl --header "Accept: text/turtle" http://localhost/service/<your_api>/<you_service>/ServiceDescription
curl --header "Accept: text/turtle" http://localhost/service/<your_api>/<you_service>/ShapesGraph
```
