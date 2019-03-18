# HTML documentation from a SPARQL micro-service Service Description

To document SPARQL micro-services and spur their discovery, they can be described by an RDF graph following the [SPARQL Service Description](https://www.w3.org/TR/2013/REC-sparql11-service-description-20130321/) recommendation. See the [configuration guide](02-config.md#configuration-with-a-sparql-service-description-file) for more details.

Such descriptions can be dynamically translated into an HTML page documenting the service using content negotiation. The Web page embeds markup data (formatted as JSON-LD) representing the service as a https://schema.org/Dataset. In turn, services like Google Dataset Search can crawl and index it properly.

The mechanism is described in the article [4](../README.md#Publications).


### Try it out

Services [flickr/getPhotosByTaxon_sd](/src/sparqlms/flickr/getPhotosByTaxon_sd) and [macaulaylibrary/getAudioByTaxon_sd](/src/sparqlms/macaulaylibrary/getAudioByTaxon_sd) are deployed live. 

Using content negotiation, their URLs can be looked up in a web browser, generating their HTML documentation and JSON-LD markup on-the-fly. Simply click on these URLs:
http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxon_sd/ and 
http://sms.i3s.unice.fr/sparql-ms/macaulaylibrary/getAudioByTaxon_sd/.

Furthermore, the same URLs can dereference to the SPARQL Service Description graphs:
```
curl --header "accept: text/turtle" http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxon_sd/
curl --header "accept: text/turtle" http://sms.i3s.unice.fr/sparql-ms/macaulaylibrary/getAudioByTaxon_sd/
```
