# SPARQL Micro-services Demo

This folder contains a demonstration set up for the [TDWG 2018](https://dx.doi.org/10.3897/biss.2.25481) conference, and regularly updated later on.

**A live version is available at http://sparql-micro-services.org/demo-sms?param=Delphinapterus+leucas.**

It uses SPARQL micro-services to integrate, within a single SPARQL query, data from the [TAXREF-LD](https://hal.archives-ouvertes.fr/hal-01617708) Linked Data taxonomic register with
occurrences from the [Global Biodiversity Information Framework (GBIF)](https://www.gbif.org/),
photos from [Flickr](https://www.flickr.com/), 
articles from the [Biodiversity Heritage Library](https://www.biodiversitylibrary.org/), 
traits from the [Encyclopedia of Life traits bank](http://eol.org/traitbank),
and audio recordings from the [Macaulay Library](https://www.macaulaylibrary.org/).

The demo runs on the [Corese-KGRAM](https://project.inria.fr/corese/) engine (configured with file ```corese-profile.ttl```).
The main SPARQL query is provided in ```query/query.rq```. It is a CONSTRUCT query that invokes the SPARQL micro-services deployed on another server.

The SPARQL results are transformed into HTML by the Corese-KGRAM engine using STTL, the SPARQL [Template Transformation Language](https://hal.inria.fr/hal-01150623/). The result page is accessible at ```http://<your-corese-kgram-server>/service/demo?param=<Species name>```.
For instance: ```http://localhost:8080/service/demo?param=Delphinus+delphis```.

### Deployment

To run this demo, copy this folder to a directory that is exposed by an HTTP server, typically `$HOME/public_html/demo-sms`.

The image gallery requires the HighSlide javascript library: simply unzip highslide.zip to ./highslide.

Folder `deployment` contains files to help in the deployment of the demo: `deployment/apache` contains an example Apache configuration, and `deployment/corese` contains the bash script and config file needed to run the Corese engine.
