# Installation, configuration and deployment

To install this project, you will need an Apache Web server and a write-enabled SPARQL endpoint (in our case, we used the [Corese-KGRAM](http://wimmics.inria.fr/corese) lightweight in-memory triple-store), and optionally an MongoDB instance to serve as the cache database (can be deactivated in [/src/sparqlms/config.ini](/src/sparqlms/config.ini)).

A Corese-KGRAM service is also required (possibly the same) to execute components that rely on the STTL and LDScript features (see folder [/deployment/corese](/deployment/corese)):
  * An STTL transformation service able to transform a SPARQL query into a [SPIN](http://spinrdf.org/sp.html) representation (see [/src/sparqlms/config.ini](/src/sparqlms/config.ini)) is required when [passing arguments within the SPARQL query graph pattern](01-usage.md#passing-arguments-within-the-sparql-query-graph-pattern)
  * An STTL transformation service transforms micro-services Service Descriptions graphs into HTML pages with embedded JSON-LD (see [/src/sparqlms/resources/sms-html-description](/src/sparqlms/resources/sms-html-description)).
  * In the sparqlcompose component, an STTL transformation service generates a federated query composed of SERVICE clauses invoking SPARQL micro-services (beta).


### Pre-requisites

The following packages must be installed before installing the SPARQL micro-services.
  * PHP 5.3+. Below we assume our current vesrion is 5.6
  * Addition PHP packages: ```php56w-mbstring``` and ```php56w-xml```, ```php56w-devel```, ```php-pear``` (PECL)
  * [Composer](https://getcomposer.org/doc/) (PHP dependency management)
  * Make sure the time zone is defined in the php.ini file, for instance:
```ini
  [Date]
  ; Defines the default timezone used by the date functions
  ; http://php.net/date.timezone
  date.timezone = 'Europe/Paris'
```
  * To use MongoDB as a cache, install the [MongoDB PHP driver](https://secure.php.net/manual/en/mongodb.installation.manual.php) and add the following line to php.ini:```extension=mongodb.so```
  * Corese-KGRAM v4.1.1+
  * Java Runtime Environment 8+

  
### Folders structure

```bash
src/common
    Cache.php
    Configuration.php         # management of the config either by config.ini file of service description graph
    Context.php               # application execution context
    Metrology.php             # execution time measures
    Utils.php                 # utility functions

src/sparqlms/
    config.ini                # generic configuration of the SPARQL micro-service engine
    service.php               # core logics of the SPARQL micro-services
    resources/

    <Web API>/                # directory of the services related to one Web API
    
        # Service with arguments passed as parameters of the HTTP query string
        <service>/
            config.ini        # micro-service configuration
            profile.jsonld    # JSON-LD profile to translate the JSON response into JSON-LD
            insert.sparql     # optional SPARQL INSERT query to create triples that JSON-LD cannot create
            construct.sparql  # optional SPARQL CONSTRUCT query used to process URI dereferencing queries
            service.php       # optional script to perform specific actions (see folder 'manual_config_example')
                              
        # Service with arguments passed in the SPARQL query graph pattern
        <service>/
            profile.jsonld    # JSON-LD profile to translate the JSON response into JSON-LD
            insert.sparql     # optional SPARQL INSERT query to create triples that JSON-LD cannot create
            construct.sparql  # optional SPARQL CONSTRUCT query used to process URI dereferencing queries
            service.php       # optional script to perform specific actions (see folder 'manual_config_example')
            ServiceDescription.ttl # SPARQL Service Description describing this micro-service
            ShapesGraph.ttl   # optional SHACL description of the graphs produced by the service
        ...
        
deployment/
    docker/docker-compose.yml # run SPARQL micro-services with Docker       
    apache/httpd.cfg          # Apache rewriting rules for HTTP access
    corese/*                  # Corese configuration and running files
    deploy.sh                 # customization of configuration files (config.ini) and SPARQL queries
```

### Installation procedure

Clone the project directory to a directory that is made accessible through HTTP by Apache, typically ```/var/www/html/sparqlms``` or ```public_html/sparqlms``` in your home directory.

From the project directory, run command ```composer install```, this will create a ```vendor``` directory with the required PHP libraries.

Create directory ```logs``` with execution and modification rights for all (```chmod 777 logs```), so that Apache can write into it. You should now have the following directory structure:

    sparqlms/
        deployment/
        src/common/
        src/sparqlms/
        vendor/
        logs/

Set the URLs of your write-enabled SPARQL endpoint and optional SPARQL-to-SPIN service in ```src/sparqlms/config.ini```. These do not need to be exposed on the internet, only the Apache process should have access to them. For instance:
```
sparql_endpoint = http://localhost:8081/sparql
spin_endpoint   = http://localhost:8081/service/sparql-to-spin
```

Use the [/deployment/deploy.sh](/deployment/deploy.sh) script to customize the configuration files. This will:
  - replace the ```http://example.org``` URL (variable $SERVER) with the URL of your own server
  - set the API_KEY values to your own API keys.


#### URL rewriting rules

You now need to configure Apache with [rewriting rules](http://httpd.apache.org/docs/2.4/rewrite/) that will route micro-service invocations appropriately. Several rules are needed to deal with the regular invocation with a SPARQL query, or the invocation to dereference URIs.
Complete examples are given in [/deployment/apache/httpd.conf](/deployment/apache/httpd.conf), and the sections below provide further explanations.

The main entry point of SPARQL micro-services is the [service.php](/src/sparqlms/service.php) script. This script takes several parameters listed in the table below:

Parameter | Description
--------- | -------------
querymode | either ```sparql``` for regular SPARQL invocation or ```ld``` when the micro-service is invoked to dereference a URI
service | the micro-service being named: ```<Web API>/<service>```
query, default-graph-uri, named-graph-uri | the regular SPARQL parameters described in the [SPARQL Protocol](https://www.w3.org/TR/2013/REC-sparql11-protocol-20130321/). In case the service is invoked for URI dereferencing, these parameters are ignored.
micro-service arguments | any other argument of the micro-service in case they are passed as query string parameters

Apache rewriting rules are used to route invocations to ```service.php``` while setting the ```querymode``` and ```service``` parameters appropriately. Other parameters are passed transparantly.


##### Rewriting rules for SPARQL querying

If micro-service arguments are passed on the HTTP query string, the URL pattern is a follows:
```http://example.org/sparqlms/<Web API>/<service>?param=value```.

If they are passed passed within the SPARQL query graph pattern, the pattern is a follows:
```http://example.org/sparqlms/<Web API>/<service>```.

The rewriting rule invokes script  ```service.php``` with parameter ```querymode``` set to ```sparql``` and ```service``` set to ```<Web API>/<service>```:
```
    RewriteRule "^/sparqlms/([^/?]+)/([^/?]+).*$" http://example.org/~userdir/sparqlms/src/sparqlms/service.php?querymode=sparql&service=$1/$2 [QSA,P,L]
```

Usage Example:
```sparql
SELECT * WHERE {
  SERVICE <https://example.org/sparqlms/macaulaylibrary/getAudioByTaxon?name=Delphinus+delphis>
  { [] <http://schema.org/contentUrl> ?audioUrl. }
}
```

##### Rewriting rules for URI dereferencing

Here we describe the example of the Flickr Web API.

Services ```flickr/getPhotosByGroupByTag``` and ```flickr/getPhotosByTaxon_sd``` generate RDF triples with photo URIs formatted as follows:
```http://example.org/ld/flickr/photo/<identifier>```, where ```<identifier>``` is the Flickr internal identifier.

A lookup to such a URI is rewritten by the rule below to invoke service ```flickr/getPhotoById``` that is specifically designed to dereference such URIs:
```
    RewriteRule "^/ld/flickr/photo/(.*)$" http://example.org/~userdir/sparqlms/src/sparqlms/service.php?querymode=ld&service=flickr/getPhotoById&query=&photo_id=$1 [P,L]
```

This invokes service ```flickr/getPhotoById``` with the ```photo_id``` parameter.
Note that the ```querymode=ld``` argument instructs  scrpipt ```service.php ``` to execute the query in file construct.sparql and return the response of this query as the response to the URI lookup query.

Usage Example:
```
    curl --header "Accept:text/turtle" http://example.org/ld/flickr/photo/31173091516
```


### Change the log level

The application writes log traces in files named like ```logs/sms-<date>.log```. The default log level is NOTICE. To change it, simply update the following line in script [service.php](/src/sparqlms/service.php) with e.g. INFO or DEBUG:

```php
    $context = Context::getInstance(Logger::NOTICE, "--------- Starting SPARQL micro-service --------");
```

Log levels are described in [Monolog documentation](https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels).



# Deploy with Docker

You can test SPARQL micro-services using the two [Docker](https://www.docker.com/) images we have built:
- [frmichel/corese](https://hub.docker.com/r/frmichel/corese/): built upon debian:buster, runs the [Corese-KGRAM](http://wimmics.inria.fr/corese) RDF store and SPARQL endpoint. Corese-KGRAM listens on port 8081.
- [frmichel/sparql-micro-service](https://hub.docker.com/r/frmichel/sparql-micro-service/): provides the Apache Web server, PHP 5.6, and the SPARQL micro-services described above. Apache listens on port 80, it is exposed as port 80 of the Docker server.

To run these images, simply download the file [docker-compose.yml](/deployment/docker/docker-compose.yml) on a Docker server and run:
```
docker-compose up -d
```

Note that this will also start a standard instance of MongoDB to serve as the cache DB.

#### Possible conflict on port 80

This deployment uses ports 80 and 8081 of the Docker host. If there are in conflict with other aplication, change the port mapping in ```docker-compose.yml```.


#### Check application logs

This ```docker-compose.yml``` will mount the SPARQL micro-service and Corese-KGRAM log directories to the Docker host in directory ```./logs```.
You may have to set rights 777 on this directory for the container to be able to write log files (```chmod 777 logs```).



# Test the installation

### Test SPARQL querying

You can test the services using the commands below in a bash.

```bash
PATH=http://localhost/sparql-ms

# URL-encoded query : select * where {?s ?p ?o}
SELECT='select%20*%20where%20%7B%3Fs%20%3Fp%20%3Fo%7D'

curl --header "Accept: application/sparql-results+json" \
  "${PATH}/flickr/getPhotoById?query=${SELECT}&photo_id=31173091246"

curl --header "Accept: application/sparql-results+json" \
  "${PATH}/macaulaylibrary/getAudioByTaxon?query=${SELECT}&name=Delphinus+delphis"

curl --header "Accept: application/sparql-results+json" \
  "${PATH}/musicbrainz/getSongByName?query=${SELECT}&name=Delphinus+delphis"
```

That should return a SPARQL JSON result.

### Test URI dereferencing

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

### Test the HTML documentation generation

Two services are provided with a service description graph that can be dynamically translated into an HTML documentation. Enter the following URLs in a web browser:
```
http://localhost/sparql-ms/flickr/getPhotosByTaxon_sd/
http://localhost/sparql-ms/macaulaylibrary/getAudioByTaxon_sd/
```

You can also look up the URIs of the service description and shapes graphs directly, e.g.:
```
http://localhost/sparql-ms/flickr/getPhotosByTaxon_sd/ServiceDescription
http://localhost/sparql-ms/flickr/getPhotosByTaxon_sd/ShapesGraph
```
