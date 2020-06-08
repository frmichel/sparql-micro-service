# Deploy SPARQL micro-services with Docker

## Test our example Docker images

You can test SPARQL micro-services provided in the project using the several Docker images we have built and published:
simply download the file [docker-compose.yml](docker-compose.yml) on a Docker server and run:
```
docker-compose up -d
```

Wait a few seconds for Corese to initialize properly, then you can test the deployment as exemplified hereafter.

### Possible conflict on port 80

This deployment uses ports 80 and 8081 of the Docker host. If there are in conflict with other aplications, change the port mapping in `docker-compose.yml`.

### Check application logs

This `docker-compose.yml` will mount the SPARQL micro-service and Corese-KGRAM log directories to the Docker host in directory `./logs`.
You may have to set rights 777 on this directory for the container to be able to write log files (`chmod 777 logs`).


## Build your own SPARQL micro-services Docker images

It is possible to deploy your own SPARQL micro-services as [Docker](https://www.docker.com/) containers.
As an example, this directory provides the Docker files to build the following images: 
- directory [sparql-micro-service](sparql-micro-service) shows how to build the main image consisting of an Apache Web server with PHP 5.6, configured to serve the SPARQL micro-services. Apache listens on port 80.
- directories [corese-sd](corese-sd) and [corese](corese) provide two ways of building an image with the [Corese-KGRAM](http://wimmics.inria.fr/corese) RDF store and SPARQL endpoint. Corese-KGRAM listens on port 8081.

To build the SPARQL micro-service image, **you will need a Github token** to be able to access forked repositories of some php libraries. Contact me to to get a token, and update the following line in [sparql-micro-service/Dockerfile](sparql-micro-service/Dockerfile):
```
RUN composer config -g github-oauth.github.com <enter your token here>
```

Then, run the following command on a Docker server:
```
docker-compose -f docker-compose-build.yml up -d
```

Note that these images are published on Docker hub: 
[frmichel/sparql-micro-service](https://hub.docker.com/r/frmichel/sparql-micro-service/),
[frmichel/corese](https://hub.docker.com/r/frmichel/corese/),
[frmichel/corese-sd](https://hub.docker.com/r/frmichel/corese-sd/).

Corese comes in two flavors depending on the way you configure your SPARQL micro-services: in case your SPARQL micro-services are configured [with a config.ini file](../../doc/02-config.md#configuration-with-file-configini), then image `frmichel/corese` is just fine.
If your SPARQL micro-services are configured using [service descriptions](../../doc/02-config.md#configuration-with-a-sparql-service-description-file), then it is necessary to pre-load their description graphs into Corese. This is exemplified in the second image: `frmichel/corese-sd`.


# Test the installation

## Test SPARQL querying

You can test the services using the commands below in a bash.

```bash
SERVICEPATH=http://localhost/service

# URL-encoded query: select * where {?s ?p ?o}
SELECT='select%20*%20where%20%7B%3Fs%20%3Fp%20%3Fo%7D'

curl --header "Accept: application/sparql-results+json" \
  "${SERVICEPATH}/flickr/getPhotoById?query=${SELECT}&photo_id=31173091246"

curl --header "Accept: application/sparql-results+json" \
  "${SERVICEPATH}/macaulaylibrary/getAudioByTaxon?query=${SELECT}&name=Delphinus+delphis"

curl --header "Accept: application/sparql-results+json" \
  "${SERVICEPATH}/musicbrainz/getSongByName?query=${SELECT}&name=Delphinus+delphis"
```

That should return a SPARQL JSON result.

## Test URI dereferencing

Enter this URL in your browser: http://localhost/ld/flickr/photo/31173091246 or the following command in a bash:

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

## Test the HTML documentation generation

Two services are provided with a service description graph that can be dynamically translated into an HTML documentation. Enter the following URLs in a web browser:
```
http://localhost/service/flickr/getPhotosByTags_sd/
http://localhost/service/macaulaylibrary/getAudioByTaxon_sd/
```

You can also look up the URIs of the service description and shapes graphs directly, e.g.:
```
curl --header "Accept: text/turtle" http://localhost/service/flickr/getPhotosByTags_sd/ServiceDescription
curl --header "Accept: text/turtle" http://localhost/service/flickr/getPhotosByTags_sd/ShapesGraph
```
