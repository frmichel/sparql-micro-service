# Configure a SPARQL micro-service

Each SPARQL micro-service resides in a dedicated folder named after the convention: \<Web API\>/\<micro-service\>, e.g. [flickr/getPhotosByTags_sd](/src/sparqlms/flickr/getPhotosByGroupByTag).

A SPARQL micro-service can be configured following two different flavours that each corespond to a method for passing arguments to the micro-service:

Configuration method | Argument-passing method
------------ | -------------
```config.ini``` property file | HTTP query string parameters
```ServiceDescription.ttl``` RDF document | Terms of the SPARQL query graph pattern

Whatever the configuration method, a SPARQL micro-serivce describes how to map responses from the Web API to RDF triples using (1) a JSON-LD profile and optionally (2) a SPARQL INSERT query. This is explained in section [Mapping a Web API response to RDF triples](#mapping-a-web-api-response-to-rdf-triples).
 

## Configuration with file config.ini

In this configuration method, the micro-service folder is organized as follows:

```<Web API>/<service>
    config.ini        # micro-service configuration
    profile.jsonld    # JSON-LD profile to translate the JSON response into JSON-LD
    insert.sparql     # optional SPARQL INSERT query to create triples that JSON-LD cannot create
    construct.sparql  # optional SPARQL CONSTRUCT query used to process URI dereferencing queries
    service.php       # optional script to perform specific actions (see 'src/sparqlms/manual_config_example')
```

The config.ini file provides the following parameters:

Parameter | Mandatory/Optional | Description
------------ | ------------- | -------------
custom_parameter | Mandatory | Array of arguments of the service to be passed as HTTP query string parameters.
api_query | Mandatory | The template of the Web API query string. It contains placeholders for the arguments defined in custom_parameter.
cache_expires_after | Optional | Maximum time (in seconds) to cache responses from the Web API. Default: 2592000 = 30 days
http_header | Optional | Array of HTTP headers sent along with the Web API query


Example:
```bash
custom_parameter[] = param1
custom_parameter[] = param2
api_query =  "https://example.org/api/service/?param1={param1}&param2={param2}"
http_header[Authorization] = "token"
```


## Configuration with a SPARQL Service Description file

In this configuration method, the micro-service folder is organized as follows:

```<Web API>/<service>/
    ServiceDescription.ttl  # SPARQL Service Description describing this micro-service
    ShapesGraph.ttl         # optional SHACL description of the graphs produced by the service
    profile.jsonld          # JSON-LD profile to translate the JSON response into JSON-LD
    insert.sparql           # optional SPARQL INSERT query to create triples that JSON-LD cannot create
    construct.sparql        # optional SPARQL CONSTRUCT query used to process URI dereferencing queries
    service.php             # optional script to perform specific actions (see 'src/sparqlms/manual_config_example')
```
### Service Description Graph

The micro-service description is provided by an RDF graph following the [SPARQL Service Description](https://www.w3.org/TR/2013/REC-sparql11-service-description-20130321/) recommendation (SD).
See provided examples for more details (conventionally named with extension '_sd'), e.g. [flickr/getPhotosByTaxon_sd](/src/sparqlms/flickr/getPhotosByTaxon_sd/ServiceDescription.ttl) or [macaulaylibrary/getAudioByTaxonCode_sd](/src/sparqlms/macaulaylibrary/getAudioByTaxonCode_sd/ServiceDescription.ttl).

The SD graph in file ```ServiceDescription.ttl``` is described in article [4](../README.md#Publications). Very succinctly, it describes an ```sd:Service``` and ```sms:Service``` instance whose data source (```dct:source```) is a Web API (```schema:WebAPI```) that has a search action (```schema:potentialAction```).

The service parameters are translated into elements of the SD graph as follows:
 
Parameter | Mandatory/Optional | Description
------------ | ------------- | -------------
```dct:source``` | Mandatory | An instance of ```schema:WebAPI``` that describes the Web API; its associated search action (```schema:potentialAction```) describes the Web API query string template, the service arguments and optional HTTP headers. See below.
Web API query string template | Mandatory | A ```hydra:IriTemplate``` (```hydra:template```) providing the Web API query string template. It contains placeholders for the input arguments.
Input arguments | Mandatory | Set of ```hydra:IriTemplateMapping``` resources (```hydra:mapping```) associated with the Web API's potential action. Each argument comes with a name (```hydra:variale```) mentioned in the template, and a mapping to a term of the input SPARQL query's graph pattern, along two methods: ```hydra:property``` simply gives the predicate to look for in the SPARQL graph pattern, while ```shacl:sourceShape``` points to the property shape that can help find the term in the graph pattern.
HTTP headers | Optional | Property of the the Web API's potential action. An ```http:headers``` list whose elements are HTTP headers to be sent to the Web API. Each header consists of a ```http:fieldName```, ```http:fieldValue``` and an optional ```http:hdrName```. See the [HTTP Vocabulary in RDF 1.0](https://www.w3.org/WAI/ER/HTTP/WD-HTTP-in-RDF10-20110502). An example is provided in [eol/getTraitsByTaxon_sd](/src/sparqlms/eol/getTraitsByTaxon_sd/ServiceDescriptionPrivate.ttl).
```sms:cacheExpiresAfter``` | Optional | Property of the ```sd:Service``` instance. Maximum time (in seconds) to cache responses from the Web API. Default: 2592000 = 30 days
```sms:exampleQuery``` | Optional | Property of the ```sd:Service``` instance. A typical query used to generate the test interface on the Web page.


**Private information**. 
Since the service description graph is public (it can be queried and dereferenced), it is not suitable to keep sensitive information such as an API private key or security token.
Therefore, a companion file ```ServiceDescriptionPrivate.ttl``` may be defined, loaded into as separate named graph that is not made public.
An example is provided in service [flickr/getPhotosByTaxon_sd](/src/sparqlms/flickr/getPhotosByTaxon_sd).

### Shapes graph
The service description graph can optionally be accompanied by a [SHACL](https://www.w3.org/TR/2017/REC-shacl-20170720/) shapes graph that specifies the type of graph that the SPARQL micro-service is designed to produce.

## Re-injecting arguments in the graph produced by the micro-service

A client's SPARQL query is used to pass arguments to the SPARQL micro-service.
The micro-service then builds the response graph and evaluates the query against this graph.

Therefore, for the graph produced to match the query graph pattern, the micro-serivce must re-inject the arguments into this graph. 

**Example**. In the query below, "sunset" is the service argument.  
```sparql
?photo
  a schema:Photograph;
  schema:keywords "sunset".
  schema:url ?url.
```

To match this query, the service must not only generate triples with the photos URLs, but also the triples with the keyword "sunset".
This is achieved using the ```insert.sparql``` or ```construct.sparql``` files.

In the example below, the ```tag``` argument (whose value is "sunset" in the query) is re-injected in the graph using placeholder ```{tag}```:
 
```sparql
INSERT {
    <http://example.org/photo/{urlencode(tag)}>

        a schema:Photograph;
        schema:keywords {tag};
        ...

```

The ```{tag}``` placeholder is replaced with "sunset" (including the double-quotes). If more than one value were provided, it will be replaced by the list of comma-separated, double-quoted values, e.g.: "sunset", "sea", ...

In case the argument is used to build a URI, the placeholder can contain the ```urlencode``` keyword to escape special characters, as illustrated in ```<http://example.org/photo/{urlencode(tag)}>```.


## Passing multiple values for the same argument

A service argument may be given multiple values, using either HTTP query string parameters or terms of the SPARQL query graph pattern.

Using HTTP query string parameters, simply add the same parameter several times, example:

```  https://example.org/micro/service/?keyword=keyword1&keyword=keyword2```

Using terms of the SPARQL query graph pattern, the values are simply passed as multiple values of a predicate, example:

```sparql
?photo
  a schema:Photograph;
  schema:keywords "keyword1", "keyword2".
  schema:url ?url.
```

In both cases, the argument passed to the Web API will be a comma-separated list of the multiple values, example:

```https://webapi.org/service/?tags=keyword1,keyword2```


## Mapping a Web API response to RDF triples

Translating the Web API JSON response into an RDF graph is carried out in two steps: 
1. Apply a [JSON-LD 1.0](https://www.w3.org/TR/2014/REC-json-ld-20140116/) profile to the response;
2. Optionnally, when mappings are needed that JSON-LD cannot express, a SPARQL Update query enriches the graph: an INSERT query (file ```insert.sparql```) when the SPARQL micro-service is invoked regularly with SPARQL, or a CONSTRUCT query (file ```construct.sparql```) when the SPARQL micro-service is invoked to dereference URIs (see the [installation details](04-install.md#rewriting-rules-for-uri-dereferencing)).

The most simple JSON-LD profile is depicted below. It creates ad-hoc terms in the ```http://ns.inria.fr/sparql-micro-service/api#``` namespace for each property of the JSON response.
```json
{ "@context": {
    "@base": "http://ns.inria.fr/sparql-micro-service/item/",
    "@vocab": "http://ns.inria.fr/sparql-micro-service/api#",
}}
```

This is a handy way of turning the Web API JSON response into RDF, and this allows manipulating the Web API response in a SPARQL query, using either  the ```insert.sparql``` or ```construct.sparql``` file).

Note that **namespaces must NOT be declared in the ```insert.sparql``` and ```construct.sparql``` files**. Instead they must be defined in the [global config.ini file](/src/sparqlms/config.ini).

Many well-known namespaces are already [declared in the EasyRDF library](https://github.com/njh/easyrdf/blob/master/lib/RdfNamespace.php), in addition to the following ones in the config.ini file:
```
namespace[dce]      = http://purl.org/dc/elements/1.1/
namespace[dct]      = http://purl.org/dc/terms/
namespace[dwc]      = http://rs.tdwg.org/dwc/terms/
namespace[dwciri]   = http://rs.tdwg.org/dwc/iri/
namespace[api]      = http://ns.inria.fr/sparql-micro-service/api#
namespace[sms]      = http://ns.inria.fr/sparql-micro-service#
```
