# SPARQL Micro-Services

The SPARQL Micro-Service architecture [1] is meant to allow the combination of Linked Data with data from Web APIs. It enables querying non-RDF Web APIs with SPARQL, and allows on-the-fly assigning dereferenceable URIs to Web API resources that do not have a URI in the first place.

This project is a prototype PHP implementation for JSON-based Web APIs. It comes with several example SPARQL micro-services, designed in the context of a biodiversity-related use case, such as:
- search Flickr for photos with a given tag. We use it to search the [*Encyclopedia of Life* Flickr group](https://www.flickr.com/groups/806927@N20) for photos of a given taxon: photos of this group are tagged with the scientific name of the taxon they represent, formatted as ```taxonomy:binomial=<scientific name>```;
- retrieve audio recordings for a given taxon name from the [Macaulay Library](https://www.macaulaylibrary.org/), a scientific media archive related to birds, amphibians, fishes and mammals;
- searche the [MusicBrainz music information encyclopedia](https://musicbrainz.org/) for music tunes whose titles matching a given name;
- search the [Biodiversity Heritage Library](https://www.biodiversitylibrary.org/) for scientific articles related to a given taxon name.
- search the [Encyclopedia of Life traits bank](http://eol.org/traitbank) for data related to a given taxon name.

**Each micro-service is further detailed in its dedicated folder**.

## The SPARQL Micro-Service Architecture

A SPARQL micro-service is a lightweight, task-specific SPARQL endpoint that provides access to a small, resource-centric virtual graph, while dynamically assigning dereferenceable URIs to Web API resources that do not have URIs beforehand. The graph is delineated by the Web API service being wrapped, the arguments passed to this service, and the restricted types of RDF triples that this SPARQL micro-service is designed to spawn.


## How to use SPARQL micro-services?

As any regular SPARQL endpoint, a SPARQL micro-service expects a SPARQL query. Additionally, it usually expects arguments that it will use to call the Web API. __Two different flavours__ exist with respect to how arguments are passed to a SPARQL micro-service: (i) as parameters on the HTTP query string of the endpoint URL, or (ii) as values within the SPARQL query graph pattern

### Passing arguments on the HTTP query string
The query below exemplifies the first flavour. It retrieves information related to the common dolphin species (*Delphinus delphis*) from the Macaulay Library. The taxon name is passed as a parameter on the HTTP query string of the SPARQL micro-service URL, ```?name=Delphinus+delphis```:

```sparql
SERVICE <https://example.org/sparqlms/macaulaylibrary/getAudioByTaxon?name=Delphinus+delphis>
{
  SELECT ?audioUrl WHERE {
    [] schema:contentUrl ?audioUrl.
  }
}
```

The arguments expected by the micro-service (```name``` in this case) are listed in file macaulaylibrary/getAudioByTaxon/config.ini.

### Passing arguments within the SPARQL query graph pattern
The query below exemplifies the second flavour. It is equivalent to the one above but the taxon name is provided as part of the graph pattern with predicate ```dwc:scientificName```:

```sparql
SERVICE <https://example.org/sparqlms/macaulaylibrary/getAudioByTaxon_sd>
{
  SELECT ?audioUrl WHERE {
    [] a dwc:Taxon;
       dwc:scientificName "Delphinus delphis";
       schema:audio [ schema:contentUrl ?audioUrl ].
  }
}
```

The arguments expected by the micro-service (```name``` in this case) are described using the [Hydra](https://www.hydra-cg.com/spec/latest/core/) vocabulary in file macaulaylibrary/getAudioByTaxon/ServiceDescription.ttl.

### Typical use case

The query below illustates a common usage of SPARQL micro-serivces that builds a mashup of Linked Data and data from Web APIs.
It first retrieves the URI of the common dolphin species (Delphinus delphis) from TAXREF-LD, a Linked Data representation of the taxonomy maintained by the french National Museum of Natural History [2]. Then, it enriches this description with  information from three different Web APIs: photos from Flickr, audio recordings from the Macaulay Library, and music tunes from MusicBrainz (the tune Web page URL).

Each SPARQL micro-service is invoked within a dedicated SERVICE clause. If any of the 3 Web APIs is not available (due for instance to a network error or internal failure etc.), the micro-service returns an empty result. In case this happens, the OPTIONAL clauses make it possible to still get (possibly partial) results.

```sparql
prefix rdfs:   <http://www.w3.org/2000/01/rdf-schema#>
prefix owl:    <http://www.w3.org/2002/07/owl#>
prefix foaf:   <http://xmlns.com/foaf/0.1/>
prefix schema: <http://schema.org/>

CONSTRUCT {
    ?species
      schema:subjectOf ?photo; schema:image ?img; schema:thumbnailUrl ?thumbnail;
      schema:contentUrl ?audioUrl;
      schema:subjectOf ?musicPage.
} WHERE {
    SERVICE <http://taxref.mnhn.fr/sparql>
    { ?species a owl:Class; rdfs:label "Delphinus delphis". }
    
    OPTIONAL {
      SERVICE <https://example.org/sparqlms/flickr/getPhotosByGroupByTag?group_id=806927@N20&tags=taxonomy:binomial=Delphinus+delphis>
      { ?photo schema:image ?img; schema:thumbnailUrl ?thumbnail. }
    }

    OPTIONAL {
      SERVICE <https://example.org/sparqlms/macaulaylibrary/getAudioByTaxon?name=Delphinus+delphis>
      { [] schema:contentUrl ?audioUrl. }
    }

    OPTIONAL {
      SERVICE <https://example.org/sparqlms/musicbrainz/getSongByName?name=Delphinus+delphis>
      { [] schema:sameAs ?page. }
    }
}
```


## Folders structure

```bash
src/sparqlms/
    config.ini                # generic configuration of the SPARQL micro-service engine
    service.php               # core of the SPARQL micro-services
    utils.php                 # utility functions
    Context.php
    Cache.php
    Metrology.php

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

        <service>/
        ...
    <Web API>/                # directory of the services related to one Web API
        <service>/            # one service of this Web API
        ...
docker/
    docker-compose.yml        # run SPARQL micro-services with Docker       
apache_cfg/
    httpd.cfg                 # Apache rewriting rules for HTTP access
```


## Installation

To install this project, you will need an Apache Web server and a write-enabled SPARQL endpoint and RDF triple store (in our case, we used the [Corese-KGRAM](http://wimmics.inria.fr/corese) lightweight in-memory triple-store), and an optional MongoDB instance to serve as the cache database (can be deactivated in src/sparqlms/config.ini).

Optionally, if you want to [pass arguments within the SPARQL query graph pattern](#passing-arguments-within-the-sparql-query-graph-pattern), you will need a service able to transform a SPARQL query into a [SPIN](http://spinrdf.org/sp.html) representation.


#### Pre-requisites

The following packages must be installed before installing the SPARQL micro-services.
  * PHP 5.3+. Below we assume our current vesrion is 5.6
  * Addition PHP packages: ```php56w-mbstring``` and ```php56w-xml```, ```php56w-devel```, ```php-pear``` (PECL)
  * [Composer](https://getcomposer.org/doc/)
  * Make sure the time zone is defined in the php.ini file, for instance:
```ini
  [Date]
  ; Defines the default timezone used by the date functions
  ; http://php.net/date.timezone
  date.timezone = 'Europe/Paris'
```
  * To use MongoDB as a cache, install the [MongoDB PHP driver](https://secure.php.net/manual/en/mongodb.installation.manual.php) and add the following line to php.ini:```extension=mongodb.so```
  * Corese-KGRAM v4.0.2+

#### Installation procedure

Clone the project directory to a directory exposed by Apache, typically ```/var/www/html/sparqlms``` or ```public_html/sparqlms``` in your home directory.

From the project directory, run command ```composer install```, this will create a vendor directory with the required PHP libraries.

Create directory ```logs``` with exec and write rights for all (```chmod 777 logs```). You should now have the following directory structure:

    sparqlms/
        src/sparqlms/
        vendor/
        logs/

Set the URLs of your write-enabled SPARQL endpoint and optional SPARQL-to-SPIN service in ```src/sparqlms/config.ini```. These do not need to be exposed publicly on the Web, only the Apache process should have access to them. For instance:
```
sparql_endpoint = http://localhost:8080/sparql
spin_endpoint   = http://localhost:8080/service/spin
```

Customize the dereferenceable URIs generated in the different services: replace the ```http://example.org``` URL with the URL of your server. See the comments in construct.sparql and insert.sparql files.

Set Apache [rewriting rules](http://httpd.apache.org/docs/2.4/rewrite/) to invoke micro-services using SPARQL or URI dereferencing: check the Apache rewriting examples in ```apache_cfg/httpd.conf```. See details in the sections below.

#### Rewriting rules for SPARQL querying

Micro-service URL pattern if arguments are passed on the HTTP query string:
    ```http://example.org/sparqlms/<Web API>/<service>?param=value```

Micro-service URL pattern if arguments are passed within the SPARQL query graph pattern:
    ```http://example.org/sparqlms/<Web API>/<service>```

Rule:
    ```RewriteRule "^/sparqlms/([^/?]+)/([^/?]+).*$" http://example.org/~userdir/sparqlms/src/sparqlms/service.php?querymode=sparql&service=$1/$2 [QSA,P,L]```

Usage Example:
```sparql
SELECT * WHERE {
  SERVICE <https://example.org/sparqlms/macaulaylibrary/getAudioByTaxon?name=Delphinus+delphis>
  { [] <http://schema.org/contentUrl> ?audioUrl. }
}
```

#### Rewriting rules for URI dereferencing

The apache_cfg directory contains more detailed Apache configuration examples.

URI pattern:
    ```http://example.org/ld/<Web API>/<service>/<identifier>```

Rule example:
    ```RewriteRule "^/ld/flickr/photo/(.*)$" http://example.org/~userdir/sparqlms/src/sparqlms/service.php?querymode=ld&service=flickr/getPhotoById&query=&photo_id=$1 [P,L]```

Usage Example:
    ```curl --header "Accept:text/turtle" http://example.org/ld/flickr/photo/31173091516```


## Deploy with Docker

You can test SPARQL micro-services using the two [Docker](https://www.docker.com/) images we have built:
- [frmichel/corese](https://hub.docker.com/r/frmichel/corese/): built upon debian:buster, runs the [Corese-KGRAM](http://wimmics.inria.fr/corese) RDF store and SPARQL endpoint. Corese-KGRAM listens on port 8081 but it is not exposed outside of the Docker server.
- [frmichel/sparql-micro-service](https://hub.docker.com/r/frmichel/sparql-micro-service/): provides the Apache Web server, PHP 5.6, and the SPARQL micro-services described above. Apache listens on port 80, it is exposed as port 81 of the Docker server.

To run these images, simply download the file ```docker/docker-compose.yml``` on a Docker server and run ```docker-compose up -d```.

Note that this will also start a standard instance of MongoDB to serve as the cache DB.

#### Check application logs

This docker-compose.yml will mount the SPARQL micro-service logs directory to the Docker host in directory ```./logs``` where you can check the SPARQL micro-services log files.

You may have to set rights 777 on this directory for the container to be able to write log files (chmod 777 logs).


## Change the log level

The application logs traces in files named like ```logs/sms-<date>.log```. The default log level is INFO. To change it, simply update the following line in file service.php:

```php
    $context = Context::getInstance(Logger::INFO);
```

Log levels are described in [Monolog documentation](https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels).

 
## Test the installation

#### Test SPARQL querying

You can test the services using the commands below in a bash.
Simply *replace "example.org" with your server's hostname, or "localhost:81" if you deployed the services with Docker*.

```bash
PATH=http://example.org/sparql-ms
SELECT='select%20*%20where%20%7B%3Fs%20%3Fp%20%3Fo%7D'

curl --header "Accept: application/sparql-results+json" \
  "${PATH}/flickr/getPhotoById?query=${SELECT}&photo_id=31173091246"

curl --header "Accept: application/sparql-results+json" \
  "${PATH}/macaulaylibrary/getAudioByTaxon?query=${SELECT}&name=Delphinus+delphis"

curl --header "Accept: application/sparql-results+json" \
  "${PATH}/musicbrainz/getSongByName?query=${SELECT}&name=Delphinus+delphis"
```

That should return a SPARQL JSON result.


#### Test URI dereferencing

Enter this URL in your browser: http://example.org/ld/flickr/photo/31173091246 or the following command in a bash:

```bash
curl --header "Accept: text/turtle" http://example.org/ld/flickr/photo/31173091246
```

*If you used the Docker deployment, simply replace example.org with localhost:81.*

That should return an RDF description of the photographic resource:

```turtle
@prefix schema: <http://schema.org/> .
@prefix cos: <http://www.inria.fr/acacia/corese#> .
@prefix dce: <http://purl.org/dc/elements/1.1/> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix sd: <http://www.w3.org/ns/sparql-service-description#> .
@prefix ma: <http://www.w3.org/ns/ma-ont#> .
@prefix api: <http://sms.i3s.unice.fr/schema/> .
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .

<http://example.org/ld/flickr/photo/31173091516> dce:creator "" ;
    rdf:type schema:Photograph ;
    dce:title           "Delphinus delphis 1 (13-7-16 San Diego)" ;
    schema:author       <https://flickr.com/photos/10770266@N04> ;
    schema:subjectOf    <https://www.flickr.com/photos/10770266@N04/31173091516/> ;
    schema:thumbnailUrl <https://farm6.staticflickr.com/5567/31173091516_f1c09fa5d5_q.jpg> ;
    schema:image        <https://farm6.staticflickr.com/5567/31173091516_f1c09fa5d5_z.jpg> .
```


## Publications

[1] Franck Michel, Catherine Faron-Zucker and Fabien Gandon. *SPARQL Micro-Services: Lightweight Integration of Web APIs and Linked Data*. In Proceedings of the Linked Data on the Web Workshop (LDOW2018). https://hal.archives-ouvertes.fr/hal-01722792

[2] Franck Michel, Olivier Gargominy, Sandrine Tercerie & Catherine Faron-Zucker (2017). *A Model to Represent Nomenclatural and Taxonomic Information as Linked Data. Application to the French Taxonomic Register, TAXREF*. In Proceedings of the 2nd International Workshop on Semantics for Biodiversity (S4BioDiv) co-located with ISWC 2017 vol. 1933. Vienna, Austria. CEUR. https://hal.archives-ouvertes.fr/hal-01617708

#### Poster

Michel F., Faron-Zucker C. & Gandon F. (2018). *Bridging Web APIs and Linked Data with SPARQL Micro-Services*. In The Semantic Web: ESWC 2018 Satellite Events, LNCS vol. 11155, pp. 187–191. Heraklion, Greece. Springer, Cham.

#### Demo

Michel F., Faron-Zucker C. & Gandon F. (2018). *Integration of Biodiversity Linked Data and Web APIs using SPARQL Micro-Services*. In Biodiversity Information Science and Standards, TDWG 2018 Proceedings, vol. 2, p. e25481. Dunedin, New Zealand. Pensoft.
