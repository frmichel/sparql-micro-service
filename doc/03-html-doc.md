# HTML documentation from a SPARQL micro-service Service Description

To document SPARQL micro-services and spur their discovery, they can be described by an RDF graph following the [SPARQL Service Description](https://www.w3.org/TR/2013/REC-sparql11-service-description-20130321/) recommendation. See the [configuration guide](02-config.md#configuration-with-a-sparql-service-description-file) for more details.

Such descriptions can be dynamically translated into an HTML page documenting the service using content negotiation. The Web page embeds markup data (formatted as JSON-LD) representing the service as a https://schema.org/Dataset. In turn, services like Google Dataset Search can crawl and index it properly.

The HTML page generation is carried out using an [STTL](http://ns.inria.fr/sparql-template/) transformation (see [/src/sparqlms/resources/sms-html-description](../src/sparqlms/resources/sms-html-description)).

The mechanism is further described in article [[4]](../README.md#Publications).

### Try it out

Services [flickr/getPhotosByTaxon_sd](../services/flickr/getPhotosByTaxon_sd) and [macaulaylibrary/getAudioByTaxon_sd](../services/macaulaylibrary/getAudioByTaxon_sd) are deployed live. 

Using content negotiation, their URLs can be looked up in a web browser, generating their HTML documentation and JSON-LD markup on-the-fly. Simply click on these URLs:
http://sparql-micros-services.org/services/flickr/getPhotosByTaxon_sd/ and 
http://sparql-micros-services.org/services/macaulaylibrary/getAudioByTaxon_sd/.

Furthermore, the same URLs can dereference to the SPARQL Service Description graphs:
```
curl --header "accept: text/turtle" http://sparql-micros-services.org/services/flickr/getPhotosByTaxon_sd/
curl --header "accept: text/turtle" http://sparql-micros-services.org/services/macaulaylibrary/getAudioByTaxon_sd/
```
