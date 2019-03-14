# Configure a SPARQL micro-service

Each SPARQL micro-service resides in a dedicated folder named after the convention: \<Web API\>/\<micro-service\>, e.g. [flickr/getPhotosByGroupByTag](/src/sparqlms/flickr/getPhotosByGroupByTag).

A SPARQL micro-service can be configured following two different flavours that each corespond to a method for passing arguments to the micro-service:

Configuration method | Argument-passing method
------------ | -------------
Simple: config.ini property file | HTTP query string parameters
Advanced: ServiceDescription.ttl RDF document | Terms of the SPARQL query graph pattern

Additionally, whatever the configuration method, a SPARQL micro-serivce describes how to map responses from the Web API to RDF triples. This is explained in section [Mapping a Web API response to RDF triples](#mapping-a-web-api-response-to-rdf-triples).
 

## Service configuration methods

### Configuration with file config.ini

In this configuration method, the micro-service folder is organized as follows:

```bash
<Web API>/<service>
    config.ini        # micro-service configuration
    profile.jsonld    # JSON-LD profile to translate the JSON response into JSON-LD
    insert.sparql     # optional SPARQL INSERT query to create triples that JSON-LD cannot create
    construct.sparql  # optional SPARQL CONSTRUCT query used to process URI dereferencing queries
    service.php       # optional script to perform specific actions (see 'src/sparqlms/manual_config_example')
```

The config.ini file provides the following parameters:

Parameter | Mandatory/Optional | Description
------------ | ------------- | -------------
custom_parameter | Mandatory | Array of arguments of the service to be passed as HTTP query string parameters
api_query | Mandatory | The template of the Web API query string. It can contain placeholders for the arguments defined in custom_parameter.
cache_expires_after | Optional | Maximum time (in seconds) to cache responses from the Web API. Default: 2592000 = 30 days
http_header | Optional | Array of HTTP headers sent along with the Web API query


Example:
```bash
custom_parameter[] = param1
custom_parameter[] = param2
api_query =  "https://example.org/api/service/?param1={param1}&param2={param2}"
http_header[Authorization] = "token"
```


### Configuration with a SPARQL Service Description file

In this configuration method, the micro-service folder is organized as follows:

```bash
<Web API>/<service>/
    ServiceDescription.ttl  # SPARQL Service Description describing this micro-service
    ShapesGraph.ttl         # optional SHACL description of the graphs produced by the service
    profile.jsonld          # JSON-LD profile to translate the JSON response into JSON-LD
    insert.sparql           # optional SPARQL INSERT query to create triples that JSON-LD cannot create
    construct.sparql        # optional SPARQL CONSTRUCT query used to process URI dereferencing queries
    service.php             # optional script to perform specific actions (see 'src/sparqlms/manual_config_example')
```

The micro-service description is provided by an RDF graph following the [SPARQL Service Description](https://www.w3.org/TR/2013/REC-sparql11-service-description-20130321/) recommendation. 
See the provided examples for more details: [flickr/getPhotosByTaxon_sd](/src/sparqlms/flickr/getPhotosByTaxon_sd/ServiceDescription.ttl) and [macaulaylibrary/getAudioByTaxonCode_sd](/src/sparqlms/macaulaylibrary/getAudioByTaxonCode_sd/ServiceDescription.ttl).

File ServiceDescription.ttl describes an sd:Service instance whose data source (dct:source) is a Web API (schema:WebAPI) that has an action (schema:potentialAction) that is a search action (schema:SearchAction).

Parameter | Mandatory/Optional | Description
------------ | ------------- | -------------
Input arguments | Mandatory | Set of IriTemplateMapping resources (hydra:mapping) associated with the Web API potential action.
Web API query string | Mandatory | A Hydra IriTemplate (hydra:template) providing the Web API query string template. It can contain placeholders for the input arguments.
sms:cacheExpiresAfter | Optional | Property of the sd:Service instance. Maximum time (in seconds) to cache responses from the Web API. Default: 2592000 = 30 days


**Private information**.
Since the service description graph will be made public (it can be queried and dereferenced), it is not suitable to keep sensitive information such as a private API key or security token.
Therefore, a companion file ServiceDescriptionPrivate.ttl may be defined, that will be loaded into as separate named graph that is not made public.
An example is provided in service [flickr/getPhotosByTaxon_sd](/src/sparqlms/flickr/getPhotosByTaxon_sd).

**Shapes graph**.
The service description graph can also be accompanied with a [SHACL](https://www.w3.org/TR/2017/REC-shacl-20170720/) shapes graph that specifies the type of graph that the SPARQL micro-service is designed to produce.

#### Dealing with multiple values for a single argument

If the graph pattern provides multiple values for a single argument, the values will passed in the API query string as a comma-separated list. 

**Example**: a Web API has an argument ```tags``` that takes a comma-separated list of tags, and the query string template is as follows:

```  https://example.org/api/service/?param1={param}```
 
The SPARQL query passes values with the ```schema:keyword``` property:

```
  ?photo
    a schema:Photograph;
    schema:keywords "keyword1", "keyword2".
```

The will result in invoking the Web API with this query string:

```  https://example.org/api/service/?param1=keyword1,keyword2```


## Mapping a Web API response to RDF triples

Translating the Web API JSON response into an RDF graph is carried out in two steps: 
1. Apply a [JSON-LD 1.0](https://www.w3.org/TR/2014/REC-json-ld-20140116/) profile to the response;
2. Optionnally, when mappings are needed that JSON-LD cannot express, a SPARQL Update query enriches the triples: an INSERT query (file insert.sparql) when the SPARQL micro-service is invoked regularly with SPARQL, or a CONSTRUCT query (file construct.sparql) when the SPARQL micro-service is invoked to dereference URIs (see the [installation details](04-install.md#rewriting-rules-for-uri-dereferencing)).

The most simple JSON-LD profile is depicted below. It creates ad-hoc terms in the ```http://ns.inria.fr/sparql-micro-service/api#``` namespace for each property of the JSON response.
```json
{ "@context": {
    "@base": "http://ns.inria.fr/sparql-micro-service/item/",
    "@vocab": "http://ns.inria.fr/sparql-micro-service/api#",
}}
```

This is a handy way of turning the Web API JSON response into RDF; this allows manipulating the Web API response in a SPARQL query (either insert.sparql or construct.sparql).

Note that default namespaces are defined in the [global config.ini file](/src/sparqlms/config.ini):
```
namespace[dce]      = http://purl.org/dc/elements/1.1/
namespace[dct]      = http://purl.org/dc/terms/
namespace[dwc]      = http://rs.tdwg.org/dwc/terms/
namespace[dwciri]   = http://rs.tdwg.org/dwc/iri/
namespace[api]      = http://ns.inria.fr/sparql-micro-service/api#
namespace[sms]      = http://ns.inria.fr/sparql-micro-service#
```
