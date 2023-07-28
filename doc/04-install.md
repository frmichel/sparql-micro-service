# Installation, configuration and deployment

## Pre-requisites

To install and run this project, you will need the following components to be installed first:

  * a web server of your choice: we use Apache, thus configuration examples are given for Apache. You may use another server although you shall translate the confifugration appropriately.
  * PHP 7.1+
  * Additional PHP packages: `php-mbstring` and `php-xml`, `php-devel`, `php-pear` (PECL)
  * [Composer](https://getcomposer.org/doc/) (PHP dependency management)
  * [Corese-KGRAM](https://project.inria.fr/corese/download/) in-memory triple-store and SPARQL endpoint. It is used to store create temporary graphs and evaluate SPARQL queries. Specific features of Corese ([STTL](http://ns.inria.fr/sparql-template/) and [LDScript](http://ns.inria.fr/sparql-extension/)) are also used for the genration of Web pages ([service index and documentation](03-html-doc.md)) and the translation of SPARQL queries into SPIN.
  * [Java Runtime Environment 10+](https://www.java.com/fr/download/)
  * a [MongoDB database](https://www.mongodb.com/c) (optional), to serve as the cache database (can be deactivated in [/src/sparqlms/config.ini](../src/sparqlms/config.ini)).


#### PHP fine-tuning
  * Make sure the time zone is defined in the php.ini file, for instance:
```ini
  [Date]
  ; Defines the default timezone used by the date functions
  ; http://php.net/date.timezone
  date.timezone = 'Europe/Paris'
```
  * To use MongoDB as a cache (optional), install the [MongoDB PHP driver](https://secure.php.net/manual/en/mongodb.installation.manual.php) and add the following line to php.ini:`extension=mongodb.so`
  * If some SPARQL micro-services require a long time to complete, you may need to increase the default tiemout, for instance:
```ini
  [PHP]
  max_execution_time = 300
  max_input_time = 300
```
  * If some SPARQL micro-services produce large outputs, you may need to increase the default max memory, for instance:
```ini
  [PHP]
  memory_limit = 2048M
```

  
## Folders structure

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
    resources/                # SPARQL queries used while executing a SPARQL micro-service
        sms-html-description/ # STTL transformation generating an HTML page from a service description graph

services/                     # directory where the services are deployed
    <Web API>/                # directory of the services related to one Web API
    
        # Service with arguments passed as parameters of the HTTP query string
        <service>/
            config.ini        # micro-service configuration
            profile.jsonld    # JSON-LD profile to translate the JSON response into JSON-LD
            construct.sparql  # optional SPARQL CONSTRUCT query to create triples that JSON-LD cannot create
            service.php       # optional script to perform specific actions (see folder 'manual_config_example')
                              
        # Service with arguments passed in the SPARQL query graph pattern
        <service>/
            profile.jsonld    # JSON-LD profile to translate the JSON response into JSON-LD
            construct.sparql  # optional SPARQL CONSTRUCT query to create triples that JSON-LD cannot create
            service.php       # optional script to perform specific actions (see folder 'manual_config_example')
            ServiceDescription.ttl # SPARQL Service Description describing this micro-service
            ShapesGraph.ttl   # optional SHACL description of the graphs produced by the service
        ...
        
deployment/
    docker/                   # this folder gives the necessary files to build Corese and your SPARQL micro-services as Docker containers
    apache/                   # Apache rewriting rules for HTTP access
    corese/                   # Corese configuration and running files
    deploy.sh                 # customization of services' configuration files and SPARQL queries
```

## Installation procedure

Clone this Github repository to a directory that is made accessible through HTTP by Apache, typically `/var/www/html/sparqlms` or `~/public_html/sparqlms` in your home directory.

CD to sparqlms directory.

Use composer to [install the dependencies](https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies), this will create a `vendor` directory with the required PHP libraries:
```
composer install
```

Create directory `logs` with execution and modification rights for all (`chmod 777 logs`), so that Apache can write into it.

You should now have the following directory structure:

```
services/
sparqlms/
    deployment/
    logs/
    src/
        common/
        sparqlms/
    vendor/
```

### Customize the properties in file [/src/sparqlms/config.ini](../src/sparqlms/config.ini):

- Set the URL of your write-enabled SPARQL endpoint and optional SPARQL-to-SPIN service. These do not need to be exposed on the internet, only the Apache process should have access to them, e.g.:
```
sparql_endpoint = http://localhost:8081/sparql
spin_endpoint   = http://localhost:8081/service/sparql-to-spin
```
- Set the path to the directories where SPARQL micro-services are deployed, e.g.:
```
services_paths[] = ../../services
services_paths[] = /home/user/services
```
- The MongoDB cache is activated by default. If you don't want to use it, turn it off:
```
use_cache = false
```


## Customize the SPARQL micro-services' configuration

The services provided in folder [/services](../services) are configured as if they were deployed at http://example.org/service, and the dereferenceable URIs they generate are in the form http://example.org/ld. These must be customized before you can use the services, to match the URL at which they are deployed.

Also, services requiring an API KEY need to be updated with your own private API keys.

Script [/deployment/deploy.sh](../deployment/deploy.sh) does that for you: copy the script to the folder where the services are located (for instance /services), update the variables `SERVER`, `SERVERPATH`, `SMSDIR` and `API_KEY`, and run the script.


### Change the log level

The application writes log traces in files named like `logs/sms-<date>.log`. The default log level is NOTICE. To change it, simply update the following line in [/src/sparqlms/config.ini](../src/sparqlms/config.ini) with e.g. INFO or DEBUG:

```
    log_level = INFO
```

Log levels are described in [Monolog documentation](https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels).


### Corese-KGRAM security configuration

Starting version 4.1.6, Corese-KGRAM implements some [security measures](https://files.inria.fr/corese/doc/level.html) that require defining explicitely the HTTP domains where Corese-KGRAM is allowed to look for remote ressources. 
This applies to SPARQL federated queries (clause SERVICE <...>), but also to STTL transformation files (that can no longer be accessed directly from the local file system).

To allow those case:
  * in the [Corese profile](../deployment/corese/corese-profile-sms.ttl), complete the list of URLs of all the domains that SERVICE clauses are allowed to reach:
```
  st:access st:namespace
    <http://localhost/sttl>,
    <https://sparql-micro-services.org>,
    <http://sms.i3s.unice.fr/sparql-ms>.
```
  * In the Apache configuration, create aliases to expose the STTL folders through http://localhost/sttl/. File [/deployment/apache/example.org.conf](../deployment/apache/example.org.conf) provides an example Apache configuration to do that.


Note: You may deactivate those security constraints by using the "-su" option of Corese. But this opens a potential security leak, e.g. a SPARQL query submitted to a SPARQL micro-serivce may execute SERVICE clauses against any endpoint.


### URL rewriting rules

You now need to configure [rewriting rules](http://httpd.apache.org/docs/2.4/rewrite/) so that Apache will route SPARQL micro-service invocations appropriately. Several rules are needed to deal with the regular invocation with a SPARQL query, or the invocation to dereference URIs.
Complete examples are given in [/deployment/apache/example.org.conf](../deployment/apache/example.org.conf), and the sections below provide further explanations.

The main entry point of SPARQL micro-services is the [service.php](../src/sparqlms/service.php) script. This script takes several parameters listed in the table below:

Parameter | Description
--------- | -------------
service | the name of SPARQL micro-service being invoked, formatted as `<Web API>/<service>`
querymode | either `sparql` for regular SPARQL invocation or `ld` when the service is invoked to dereference a URI
root_url | URL at which the SPARQL micro-service is deployed (optional). If provided, this parameter overrides the `root_url` parameter in the [main config.ini](../src/sparqlms/config.ini) file.
query, default-graph-uri, named-graph-uri | the regular SPARQL parameters described in the [SPARQL Protocol](https://www.w3.org/TR/2013/REC-sparql11-protocol-20130321/) (since a SPARQL micro-service is first of all a SPARQL endpoint). When the service is invoked for URI dereferencing (querymode=ld), these parameters are ignored.
*service custom arguments* | any other arguments of the SPARQL micro-service in case they are passed as query string parameters

Apache rewriting rules are used to route invocations to `service.php` while setting the `querymode`, `service` and `root_url` parameters appropriately. Other parameters (`query`, `default-graph-uri`, `named-graph-uri` and the service custom arguments) that are passed by the client invoking the service are transmitted transparantly to `service.php`.


#### Rewriting rules for SPARQL querying

If the service custom arguments are passed on the HTTP query string ([config.ini method](02-config.md#configuration-with-file-configini)), the URL pattern is a follows:
```http://example.org/service/<Web API>/<service>?param=value```.

If they are passed passed within the SPARQL query graph pattern ([Service Description method](02-config.md#configuration-with-a-sparql-service-description-file)), the URL pattern is simply:
```http://example.org/service/<Web API>/<service>```.

The rewriting rule below invokes script  `service.php` with parameter `querymode` set to `sparql` and `service` set to `<Web API>/<service>`.
The other parameters (`query`, `default-graph-uri`, `named-graph-uri` and the service custom arguments) are passed transparently (flag QSA of the rewriting rule):
```
    RewriteRule "^/service/([^/?]+)/([^/?]+).*$" http://example.org/~userdir/sparqlms/src/sparqlms/service.php?querymode=sparql&service=$1/$2 [QSA,P,L]
```

**Example**. The following invocation:
```sparql
SELECT * WHERE {
  SERVICE <https://example.org/service/macaulaylibrary/getAudioByTaxon?name=Delphinus+delphis>
  { [] <http://schema.org/contentUrl> ?audioUrl. }
}
```
will be rewritten into this URL:
```
http://example.org/~userdir/sparqlms/src/sparqlms/service.php?querymode=sparql&service=macaulaylibrary/getAudioByTaxon&name=Delphinus+delphis
```


#### Rewriting rules for URI dereferencing

Here we describe the example of the Flickr Web API.

Service `flickr/getPhotosByTaxon_sd` generates RDF triples with photo URIs formatted as follows:
`http://example.org/ld/flickr/photo/<identifier>`, where `<identifier>` is the Flickr internal identifier.

To produce a graph in response to the lookup of such a URI, service `flickr/getPhotoById` is used. The rewriting rule below invokes script  `service.php` with parameter `querymode` set to `ld` and `service` set to `flickr/getPhotoById`:

    RewriteRule "^/ld/flickr/photo/(.*)$" http://example.org/~userdir/sparqlms/src/sparqlms/service.php?querymode=ld&service=flickr/getPhotoById&photo_id=$1 [P,L]
```
```

This invokes service `flickr/getPhotoById` with the `photo_id` parameter whose value is extract from the URI.

Note that the `querymode=ld` argument instructs `service.php ` to execute the query in file `construct.sparql` and return the response of this query as the response to the URI lookup query. Hence no SPARQL `query` argument needs to be provided.

**Example**. The following invocation:
```
    curl --header "Accept:text/turtle" http://example.org/ld/flickr/photo/31173091516
```
will be rewritten into this URL:
```
http://example.org/~userdir/sparqlms/src/sparqlms/service.php?querymode=ld&service=flickr/getPhotoById&photo_id=31173091516
```


#### Rewriting rules for dereferencing ServiceDescription and SHACL graphs

Additional rewrinting rules must be set to allow dereferencing the ServiceDescription and SHACL graphs, as well as the translation of the ServiceDescription graph into an HTML page.

Complete examples are given in the first part of the [/deployment/apache/example.org.conf](../deployment/apache/example.org.conf).


# Start the services

If some SPARQL micro-services are [configured with a Service Description file](02-config.md#configuration-with-a-sparql-service-description-file), then files ServiceDescription.ttl, ServiceDescriptionPrivate.ttl and ShapesGraph.ttl of each SPARQL micro-service must be loaded as named graphs when Corese-KGRAM starts.
Script [corese-server.sh](../deployment/corese/corese-server.sh) prepares a list of those files as well as their named graphs URIs, then it starts up Corese-KGRAM that immediately loads the files.


Update this script as needed and run it:

`./corese-server.sh`

If you are using MongoDB as a cache database, make sure it is running:

`sudo systemctl status mongod`

FInally, restart the Apache server to take into account any configuration changes:

`sudo systemctl status httpd`



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
```

You can also look up the URIs of the service description and shapes graphs directly, e.g.:
```
curl --header "Accept: text/turtle" http://localhost/service/flickr/getPhotosByTags_sd/ServiceDescription
curl --header "Accept: text/turtle" http://localhost/service/flickr/getPhotosByTags_sd/ShapesGraph
```
