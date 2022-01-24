# Prototype and deploy your SPARQL micro-services with Docker

You can prototype and deploy SPARQL micro-services using the Docker images we have built and published on Docker hub.

  - [frmichel/sparql-micro-service](https://hub.docker.com/r/frmichel/sparql-micro-service/) contains the core code to run SPARQL-micro-services, running on an Apache Web server with PHP. Apache listens on port 80.

  - [frmichel/corese](https://hub.docker.com/r/frmichel/corese/) contains the [Corese-KGRAM](http://wimmics.inria.fr/corese) RDF store and SPARQL endpoint. It is used to execute SPARQL queries and generate the HTML documentation of the services described with a [service descriptions](../../doc/02-config.md#configuration-with-a-sparql-service-description-file) file (not necessary for services configured [with a config.ini file](../../doc/02-config.md#configuration-with-file-configini)).

To run SPARQL micro-services, simply place them in the `services` directory in the folder where you run docker-compose.


## Run Docker with our example SPARQL micro-services

To start running SPARQL micro-services, download file [services.zip](services.zip). Unzip it to the directory from where you will run docker-compose, and give them full read access rights so that the Docker container can read them (**this is important**).

```bash
unzip services.zip
chmod -R 755 services
```

Then, download file [docker-compose.yml](docker-compose.yml) and run:

```
docker-compose up -d
```

Wait a few seconds for Corese to initialize properly. 
Then, you can test the services using the commands below in a bash.

```bash
# URL-encoded query: select * where {?s ?p ?o}
SELECT='select%20*%20where%20%7B%3Fs%20%3Fp%20%3Fo%7D'

# URL-encoded query: construct where {?s ?p ?o}
CONSTRUCT=construct%20WHERE%20%7B%20%3Fs%20%3Fp%20%3Fo%20%7D%20

curl --header "Accept: text/turtle" \
  "http://localhost/service/deezer/findAlbums?query=${CONSTRUCT}&keyword=eminem"

curl --header "Accept: application/sparql-results+json" \
  "http://localhost/service/musicbrainz/getSongByName?query=${SELECT}&name=Love"
```


#### Possible conflict on port 80

This deployment uses ports 80 and 8081 of the Docker host. If they are in conflict with other aplications, change the port mapping in `docker-compose.yml`.

#### Check application logs

This `docker-compose.yml` will mount the SPARQL micro-service and Corese-KGRAM log directories to the Docker host in directory `logs`.
You may have to set rights 777 on this directory for the container to be able to write log files (`chmod 777 logs`).


## Test your own SPARQL micro-services

You can write and deploy you own SPARQL micro-services by simply dropping them in the `services` directory, in the folder where you have run docker-compose.
Just mimmic what already exists in the example services.

Just remember to always give all files full read access rights so that the Docker container can read them:

```bash
chmod -R 755 services/*
```


## Accessing HTML desciption, services description and shapes graph

The Docker images also support micro-services configured with a [service description](../../doc/02-config.md#configuration-with-a-sparql-service-description-file).
In the example services, this is the case of `flickr/getPhotosByTag_sd`. You can then test the HTML documentation generation by entering the following URL in your web browser: http://localhost/service/flickr/getPhotosByTag_sd/

Note that the first time it is accessed, the page will take a few seconds to load.

Also, accessing http://localhost/service/ will show the auto-generated services index page.

You can also look up the URIs of the service description and shapes graphs directly, using your web browser or curl e.g.:
```
curl --header "Accept: text/turtle" http://localhost/service/flickr/getPhotosByTag_sd/ServiceDescription
curl --header "Accept: text/turtle" http://localhost/service/flickr/getPhotosByTag_sd/ShapesGraph
```

When you add or modify such services in the `services` directory, you need to **restart the Corese Docker container** as the service descriptions are loaded when Corese starts up:

```
docker-compose restart corese
docker-compose restart sparql-micro-service
```
