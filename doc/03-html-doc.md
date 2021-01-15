# HTML documentation of SPARQL micro-services

To document SPARQL micro-services and spur their discovery, their [service description graph](02-config.md#configuration-with-a-sparql-service-description-file) can be dynamically translated into an HTML documention page, accessed using content negotiation. The Web page embeds markup data (formatted as JSON-LD) representing the service as a https://schema.org/Dataset. such that search engines like Google Dataset Search can crawl and index it properly.
The HTML page generation is carried out using an [STTL](http://ns.inria.fr/sparql-template/) transformation (see [/src/sparqlms/resources/sms-html-description](../src/sparqlms/resources/sms-html-description)).

Additionally, the root URL of services hosted on a server can be dereferenced to a Web page providing an index of these services. 
Similarly, the Web page embeds markup data (formatted as JSON-LD) representing a https://schema.org/DatasetCatalog also meant for dataset search engines.
The HTML page generation is carried out using an [STTL](http://ns.inria.fr/sparql-template/) transformation (see [/src/sparqlms/resources/sms-html-index](../src/sparqlms/resources/sms-html-index)).

The mechanism is further described in article [[4]](../README.md#Publications).


### Try it out

In a web browser, look up URL their URLs can be looked https://sparql-micro-services.org/. This provides an index of the services hosted on this server.

Using content negotiation, the URLs of these service can be looked up in a web browser, generating their HTML documentation and JSON-LD markup on-the-fly.

For instances, services [flickr/getPhotosByTaxon_sd](../services/flickr/getPhotosByTaxon_sd) and [macaulaylibrary/getAudioByTaxon_sd](../services/macaulaylibrary/getAudioByTaxon_sd) are deployed on this server. Click on these URLs to get the Web page:
https://sparql-micro-services.org/service/flickr/getPhotosByTaxon_sd/ and 
https://sparql-micro-services.org/service/macaulaylibrary/getAudioByTaxon_sd/.


Furthermore, the same URLs can dereference to the SPARQL Service Description graphs:
```
curl --header "accept: text/turtle" https://sparql-micro-services.org/service/flickr/getPhotosByTaxon_sd/
curl --header "accept: text/turtle" https://sparql-micro-services.org/service/macaulaylibrary/getAudioByTaxon_sd/
```
