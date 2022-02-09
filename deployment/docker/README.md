# Prototype and deploy your SPARQL micro-services with Docker

You can prototype and deploy SPARQL micro-services using the Docker images we have built and published on Docker hub.

  - [frmichel/sparql-micro-service](https://hub.docker.com/r/frmichel/sparql-micro-service/) contains the core code to run SPARQL-micro-services, running on an Apache Web server with PHP. Apache listens on port 80.

  - [frmichel/corese](https://hub.docker.com/r/frmichel/corese/) contains the [Corese-KGRAM](http://wimmics.inria.fr/corese) RDF store and SPARQL endpoint. It is used to execute SPARQL queries and generate the HTML documentation of the services described with a [service descriptions](../../doc/02-config.md#configuration-with-a-sparql-service-description-file) file (not necessary for services configured [with a config.ini file](../../doc/02-config.md#configuration-with-file-configini)).

To run SPARQL micro-services, simply place them in the `services` directory in the folder where you run docker-compose.


## Run Docker with our example SPARQL micro-services

To start running SPARQL micro-services, download file [environment.zip](environment.zip). Unzip it to the directory from where you will run docker-compose, and set file access rights as demonstrated below so that the Docker container can read/write the necessary files and folders.

```bash
unzip environment.zip
chmod -R 755 services
chmod -R 755 config
chmod -R 777 logs
```

Then, download file [docker-compose.yml](docker-compose.yml) and run:

```
docker-compose up -d
```

Wait a few seconds for Corese to initialize properly. 
Then, you can test the services using the commands below in a bash.

```bash
# URL-encoded query: construct where {?s ?p ?o}
CONSTRUCT=construct%20WHERE%20%7B%20%3Fs%20%3Fp%20%3Fo%20%7D%20

curl --header "Accept: text/turtle" \
  "http://localhost/service/deezer/findAlbums?query=${CONSTRUCT}&keyword=eminem"

# URL-encoded query: select * where {?s ?p ?o}
SELECT='select%20*%20where%20%7B%3Fs%20%3Fp%20%3Fo%7D'

curl --header "Accept: application/sparql-results+json" \
  "http://localhost/service/musicbrainz/getSongByName?query=${SELECT}&name=Love"
```

The Docker image is also configured to support URI dereferencing using the servide `flickr/getPhotoById`:

Enter this URL in your browser: http://localhost/ld/flickr/photo/31173091246 or the following command in a bash:

```bash
curl --header "Accept: text/turtle" http://localhost/ld/flickr/photo/31173091246
```


### Accessing Logs 

The `docker-compose.yml` mounts the SPARQL micro-service and Corese-KGRAM log directories to the Docker host in directory `logs`.

Corese-KGRAM log is named ```kgram-server.log```, while SPARQL micro-service logs are named ```sms-<date>.log```.

### Changing Configuration

The main SPARQL micro-service configuration file is editable at ```config/sparql-micro-service.ini```. In particular you can change the log level.

For changes to be taken into account, restart the SPARQL-micro-service Docker container.


### Common issues

#### Conflict on port 80

This deployment uses ports 80 of the Docker host. If it is in conflict with another application, change the port mapping in `docker-compose.yml`.

#### Logs directory not writable

The containers need to write in directory ```logs```. This will fail if you do not set rights 777 (`chmod 777 logs`), and the SPARQL-micro-service console shall show an error like this:

```PHP Notice:  Undefined variable: logger in /var/www/html/sparql-ms/src/sparqlms/service.php on line 204```


## Test your own SPARQL micro-services

You can write and deploy you own SPARQL micro-services by simply dropping them in the `services` directory, in the folder where you have run docker-compose.
Just mimmic what already exists in the example services.

Just remember to always give all files full read access rights so that the Docker container can read them:

```bash
chmod -R 755 services/*
```

If your services are configured with a config.ini file, this is all you have to do.

Conversely, if your services are configured with a [service description](../../doc/02-config.md#configuration-with-a-sparql-service-description-file) file, then you need to restart the Corese-KGRAM container as these files are loaded when Corese starts up.

```bash
docker-compose restart corese
```

## Accessing HTML desciption, services description and shapes graph

The Docker images also support micro-services configured with a [service description](../../doc/02-config.md#configuration-with-a-sparql-service-description-file) file.
In the example services, this is the case of `flickr/getPhotosByTag_sd`. You can then test the HTML documentation generation by entering the following URL in your web browser: http://localhost/service/flickr/getPhotosByTag_sd/

Note that the first time it is accessed, the page will take a few seconds to load as Corese performs some lazy initialization.

Also, accessing http://localhost/service/ will show the auto-generated services index page.

You can also look up the URIs of the service description and shapes graphs directly, using your web browser or curl e.g.:
```
curl --header "Accept: text/turtle" http://localhost/service/flickr/getPhotosByTag_sd/ServiceDescription
curl --header "Accept: text/turtle" http://localhost/service/flickr/getPhotosByTag_sd/ShapesGraph
```

When you add or modify such services in the `services` directory, you need to **restart the Corese Docker container** as the service descriptions are loaded when Corese starts up:

```bash
docker-compose restart corese
```
