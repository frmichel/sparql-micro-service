# HTML documentation from a SPARQL micro-service Service Description

To enable discovery of SPARQL micro-services, it is possible to describe them with an RDF graph following the [SPARQL Service Description](https://www.w3.org/TR/2013/REC-sparql11-service-description-20130321/) specification. See examples for services [flickr/getPhotosByTaxon_sd](/src/sparqlms/flickr/getPhotosByTaxon_sd/ServiceDescription.ttl) and [macaulaylibrary/getAudioByTaxonCode_sd](/src/sparqlms/macaulaylibrary/getAudioByTaxonCode_sd/ServiceDescription.ttl).

Such descriptions can be dynamically translated into an HTML page documenting the service using content negotiation, along with markup data (formatted as JSON-LD) representing the service as a https://schema.org/Dataset.

Try it out: the two services mentioned above are deployed live. You can dereference their URLs to obtain the SPARQL Service Description graphs in an RDF serialization format:

```
curl --header "accept: text/turtle" http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxon_sd/
curl --header "accept: text/turtle" http://sms.i3s.unice.fr/sparql-ms/macaulaylibrary/getAudioByTaxon_sd/
```

Furthermore, using content negotiation, the same URLs can be looked up in a web browser, generating their HTML documentation and JSON-LD markup on-the-fly. Simply click on these URLs:
http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxon_sd/ and 
http://sms.i3s.unice.fr/sparql-ms/macaulaylibrary/getAudioByTaxon_sd/.
