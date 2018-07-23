# SPARQL Micro-services Demo - TDWG 2018

This folder contains a demonstration set up for the [TDWG 2018](http://spnhc-tdwg2018.nz/?utm_source=TDWG+Announcements&utm_campaign=904cb6fb04-EMAIL_CAMPAIGN_2018_03_01&utm_medium=email&utm_term=0_b8159bd5d8-904cb6fb04-514691113) conference.

It uses SPARQL micro-services to integrate, within a single SPARQL query, data from the [TAXREF-LD](https://hal.archives-ouvertes.fr/hal-01617708) Linked Data taxonomic register with photos from the [*Encyclopedia of Life* Flickr group](https://www.flickr.com/groups/806927@N20), articles from the [Biodiversity Heritage Library](https://www.biodiversitylibrary.org/), traits from the [Encyclopedia of Life traits bank](http://eol.org/traitbank) trait bank, audio recordings from the [Macaulay Library](https://www.macaulaylibrary.org/), and music tunes from [MusicBrainz](https://musicbrainz.org/).

The demo runs on the [Corese-KGRAM](http://wimmics.inria.fr/corese) engine (configured in file ```profile.ttl```).
The SPARQL query is provided in ```query/query.rq```. It is a CONSTRUCT query that calls the SPARQL micro-services deployed on another server.
File ```data/constr.ttl``` is an example of the data returned by this query.

The SPARQL results are transformed into HTML by the Corese-KGRAM engine using STTL, the SPARQL [Template Transformation Language](https://hal.inria.fr/hal-01150623/). The result page is accessible at ```http://<your-corese-kgram-server>/tutorial/demo?param=<Species name>```.
For instance: ```http://localhost:8080/tutorial/demo?param=Delphinus+delphis```.

