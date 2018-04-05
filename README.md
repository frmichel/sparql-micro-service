# SPARQL Micro-Services

The SPARQL Micro-Service architecture [1] is meant to allow the combination of Linked Data with data from Web APIs. It enables querying non-RDF Web APIs with SPARQL, and allows on-the-fly assigning dereferenceable URIs to Web API resources that do not have a URI in the first place.

This project is a prototype PHP implementation for JSON-based Web APIs. It comes with several example SPARQL micro-services, designed in the context of a biodiversity-related use case:
- flickr/getPhotosByGroupByTag: searches a Flickr group for photos with a given tag. We use it to search the [*Encyclopedia of Life* Flickr group](https://www.flickr.com/groups/806927@N20) for photos of a given taxon: photos of this group are tagged with the scientific name of the taxon they represent, formatted as ```taxonomy:binomial=<scientific name>```.
- macaulaylibrary/getAudioByTaxon retrieves audio recordings for a given taxon name from the [Macaulay Library](https://www.macaulaylibrary.org/), a scientific media archive related to birds, amphibians, fishes and mammals.
- musicbrainz/getSongByName searches the [MusicBrainz music information encyclopedia](https://musicbrainz.org/) for music tunes whose titles match a given name with a minimum confidence of 90%.
- bhl/getArticlesByTaxon searches the [Biodiversity Heritage Library](https://www.biodiversitylibrary.org/) for scientific articles related to a given taxon name.

[1] Franck Michel, Catherine Faron-Zucker and Fabien Gandon. *SPARQL Micro-Services: Lightweight Integration of Web APIs and Linked Data*. In Proc. of the Linked Data on the Web Workshop (LDOW2018). https://hal.archives-ouvertes.fr/hal-01722792

## The SPARQL Micro-Service Architecture

A SPARQL micro-service is a lightweight, task-specific SPARQL endpoint that provides access to a small, resource-centric virtual graph, while dynamically assigning dereferenceable URIs to Web API resources that do not have URIs beforehand. The graph is delineated by the Web API service being wrapped, the arguments passed to this service, and the restricted types of RDF triples that this SPARQL micro-service is designed to spawn. 


## How to use SPARQL micro-services?

A SPARQL micro-service is typically called from a SPARQL SERVICE clause.

The query below retrieves the URI of the common dolphin species (*Delphinus delphis*) from the SPARQL endpoint of TAXREF-LD, a Linked Data representation of the taxonomy maintained by the french National Museum of Natural History.
Then, it enriches this description with 15 photos retrieved from Flickr, 28 audio recordings from the Macaulay Library, and 1 music tune from MusicBrainz (the tune Web page URL).

If any of the 3 Web APIs invoked is not available (network error, internal failure etc.), the micro-service returns an empty result. In case this happens, the OPTINAL clauses make it possible to still get (possibly partial) results.

    prefix rdfs:   <http://www.w3.org/2000/01/rdf-schema#>
    prefix owl:    <http://www.w3.org/2002/07/owl#>
    prefix foaf:   <http://xmlns.com/foaf/0.1/>
    prefix schema: <http://schema.org/>
    
    CONSTRUCT {
        ?species 
            schema:subjectOf ?photo; foaf:depiction ?img ; 
            schema:contentUrl ?audioUrl;
            schema:subjectOf ?page.
    } WHERE {
        SERVICE <http://taxref.mnhn.fr/sparql> 
        { ?species a owl:Class; rdfs:label "Delphinus delphis". }

        OPTIONAL { 
          SERVICE <https://example.org/sparql-ms/flickr/getPhotosByGroupByTag?group_id=806927@N20&tags=taxonomy:binomial=Delphinus+delphis> 
          { ?photo foaf:depiction ?img. }
        }
        
        OPTIONAL {
          SERVICE <https://example.org/sparql-ms/macaulaylibrary/getAudioByTaxon?name=Delphinus+delphis> 
          { [] schema:contentUrl ?audioUrl. } 
        }

        OPTIONAL {
          SERVICE <https://example.org/sparql-ms/musicbrainz/getSongByName?name=Delphinus+delphis> 
          { [] schema:sameAs ?page. }
        }
    }


## Folders structure

    sparql-ms/
        config.ini                # generic configuration of the SPARQL micro-service engine
        service.php               # core of the SPARQL micro-services
        utils.php                 # utility functions
        
        <Web API>/                # directory of the services related to one Web API
            <service>/            # one service of this Web API
                config.ini        # micro-service specific configuration
                profile.jsonld    # JSON-LD profile to translate the JSON response into JSON-LD
                insert.sparql     # optional SPARQL INSERT query to create triples that JSON-LD cannot create
                construct.sparql  # optional SPARQL CONSTRUCT query used to process URI dereferencing queries
                service.php       # optional script. Replaces the config.ini in case specific actions are required
            <service>             # one service of this Web API
            ...
        <Web API>/                # directory of the services related to one Web API
            <service>/            # one service of this Web API
            ...
    docker/
        docker-compose.yml        # run SPARQL micro-services with Docker       
    apache_cfg/
        httpd.cfg                 # Apache rewriting rules for HTTP access
        ssl.cfg                   # Apache rewriting rules for HTTPS access


## Deploy with Docker

The easyest way to test SPARQL micro-services is to use the two [Docker](https://www.docker.com/) images we have built: 
- [frmichel/corese](https://hub.docker.com/r/frmichel/corese/): built upon debian:buster, runs the [Corese-KGRAM](http://wimmics.inria.fr/corese) RDF store and SPARQL endpoint. Corese-KGRAM listens on port 8081 but it is not exposed to the Docker server.
- [frmichel/sparql-micro-service](https://hub.docker.com/r/frmichel/sparql-micro-service/): provides the Apache Web server, PHP 5.6, and the SPARQL micro-services described above configured and ready to go. Apache listens on port 80, it is exposed as port 81 of the Docker server.

To run these images, simply download the file ```docker/docker-compose.yml``` on a Docker server and run ```docker-compose up -d```.

#### Test SPARQL querying

You can test the three services by typing the following commands in a bash:

    ```curl --header "Accept: application/sparql-results+json" "http://localhost:81/sparql-ms/flickr/getPhotoById?query=select%20*%20where%20%7B%3Fs%20%3Fp%20%3Fo%7D&photo_id=31173091246"```

    ```curl --header "Accept: application/sparql-results+json" "http://localhost:81/sparql-ms/macaulaylibrary/getAudioByTaxon?query=select%20*%20where%20%7B%3Fs%20%3Fp%20%3Fo%7D&name=Delphinus+delphis"```

    ```curl --header "Accept: application/sparql-results+json" "http://localhost:81/sparql-ms/musicbrainz/getSongByName?query=select%20*%20where%20%7B%3Fs%20%3Fp%20%3Fo%7D&name=Delphinus+delphis"```

That should return a JSON SPARQL result.

#### Test URI dereferencing

Enter this URL in your browser: http://localhost:81/ld/flickr/photo/31173091246 or the following command in a bash:

    curl --header "Accept: text/turtle" http://localhost:81/ld/flickr/photo/31173091246

That should return an RDF description of the resource:

    @prefix schema: <http://schema.org/> .
    @prefix cos: <http://www.inria.fr/acacia/corese#> .
    @prefix dce: <http://purl.org/dc/elements/1.1/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
    @prefix sd: <http://www.w3.org/ns/sparql-service-description#> .
    @prefix ma: <http://www.w3.org/ns/ma-ont#> .
    @prefix api: <http://sms.i3s.unice.fr/schema/> .
    @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .

    <http://localhost:81/ld/flickr/photo/31173091516> dce:creator "" ;
        rdf:type schema:Photograph ;
        dce:title           "Delphinus delphis 1 (13-7-16 San Diego)" ;
        schema:author       <https://flickr.com/photos/10770266@N04> ;
        schema:subjectOf    <https://www.flickr.com/photos/10770266@N04/31173091516/> ;
        schema:thumbnailUrl <https://farm6.staticflickr.com/5567/31173091516_f1c09fa5d5_q.jpg> ;
        schema:url          <https://farm6.staticflickr.com/5567/31173091516_f1c09fa5d5_z.jpg> ;
        .

#### Check application logs

To access the SPARQL micro-servcices log file, add a ```volumes``` parameter in the ```docker/docker-compose.yml``` file, like this:

    sms-apache:
        image: frmichel/sparql-micro-service
        networks:
          - sms-net
        ports:
          - "81:80"
        volumes:
          - "./logs:/var/www/html/sparql-ms/logs"

This will mount the SPARQL micro-service logs directory to the Docker host in directory ```./logs```.

You may have to set access mode 777 on this directory for the container to be able to write log files.


## Installation

To install this project, you will need an Apache Web server with PHP 5.3+ and a write-enabled SPARQL endpoint and RDF triple store. In our case, we used the [Corese-KGRAM](http://wimmics.inria.fr/corese) lightweight in-memory triple-store.

Copy the ```sparql-ms``` directory to a directory exposed by Apache, typically ```/var/www/html``` or the ```public_html``` of your home directory.

Do __NOT__ use PHP ```composer``` to update the libraries in the vendor directory. This would override changes we made in some of them (Json-LD and EasyRDF).

Set the URL of your write-enabled SPARQL endpoint in ```sparql-ms/config.ini```. This endpoint does not need to be exposed publicly on the Web, only the SPARQL micro-services should have access to it. For instance:
```
sparql_endpoint = http://localhost:8080/sparql
```

Customize the dereferenceable URIs generated in the Flickr services: see the comments in construct.sparql and insert.sparql files.

Set Apache [rewriting rules](http://httpd.apache.org/docs/2.4/rewrite/) to invoke micro-services using SPARQL or URI dereferencing: check the Apache rewriting examples in ```apache_cfg/httpd.conf``` and ```apache_cfg/ssl.conf```. See details in the sections below.

#### Rewriting rules for SPARQL querying

Micro-service URL pattern:
    ```http://example.org/sparql-ms/<Web API>/<service>?param=value```

Rule:
    ```RewriteRule "^/sparql-ms/([^/?]+)/([^/?]+).*$" http://example.org/sparql-ms/service.php?querymode=sparql&service=$1/$2 [QSA,P,L]```

Usage Example:
```
    SELECT * WHERE {
        SERVICE <https://example.org/sparql-ms/macaulaylibrary/getAudioByTaxon?name=Delphinus+delphis> 
        { [] <http://schema.org/contentUrl> ?audioUrl. }
    }
```

#### Rewriting rules for URI dereferencing

The apache_cfg directory contains more detailed Apache http/https configuration examples.

URI pattern: 
    ```http://example.org/ld/<Web API>/<service>/<identifier>```

Rule:
    ```RewriteRule "^/ld/flickr/photo/(.*)$" http://example.org/sparql-ms/service.php?querymode=ld&service=flickr/getPhotoById&query=&photo_id=$1 [P,L]```

Usage Example: 
    ```curl --header "accept:text/turtle" http://example.org/ld/flickr/photo/31173091516```
