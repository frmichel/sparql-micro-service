# Configure a SPARQL micro-service


A SPARQL micro-service resides in a dedicated folder named after the convention: `<Web API>/<micro-service>`, e.g. [flickr/getPhotosByTags_sd](../services/flickr/getPhotosByTags_sd).

It can be configured following two different flavours that each correspond to a method for passing arguments to the micro-service:

Configuration method | Argument-passing method
------------ | -------------
`config.ini` property file | HTTP query string parameters
`ServiceDescription.ttl` RDF document | Terms of the SPARQL query graph pattern

These two methods are described in the first two sections below. Whatever the configuration method, a SPARQL micro-serivce describes how to map responses from the Web API to RDF triples using (1) a JSON-LD profile and optionally (2) a SPARQL CONSTRUCT query. This is explained in section [Mapping a Web API response to RDF triples](#mapping-a-web-api-response-to-rdf-triples).
 
Table of contents:
- [Configuration with file config.ini](#configuration-with-file-configini)
- [Configuration with a SPARQL Service Description file](#configuration-with-a-sparql-service-description-file)
    - [Service Description Graph](#service-description-graph)
    - [Private information of the Service Description Graph](#private-information-of-the-service-description-graph)
    - [Shapes graph](#shapes-graph)
- [Re-injecting arguments in the graph produced by the micro-service](#re-injecting-arguments-in-the-graph-produced-by-the-micro-service)
- [Passing multiple values for the same argument](#passing-multiple-values-for-the-same-argument)
- [Mapping a Web API response to RDF triples](#mapping-a-web-api-response-to-rdf-triples)


## Configuration with file config.ini

In this configuration method, the micro-service folder is organized as follows:

```<Web API>/<service>
    config.ini        # micro-service configuration
    profile.jsonld    # JSON-LD profile to translate the JSON response into JSON-LD
    construct.sparql  # optional SPARQL CONSTRUCT query to create triples that JSON-LD cannot create
    service.php       # optional script to perform specific actions (see 'services/manual_config_example')
```

The config.ini file provides the following parameters:

Parameter | Mandatory/Optional | Description
------------ | ------------- | -------------
custom_parameter | Mandatory | Array of arguments of the service to be passed as HTTP query string parameters. Has to be set but may be left empty when the service takes no parameter. 
api_query | Mandatory | The template of the Web API query string. It contains placeholders for the arguments defined in custom_parameter.
cache_expires_after | Optional | Maximum time (in seconds) to cache responses from the Web API. Default: 2592000 = 30 days
http_header | Optional | Array of HTTP headers sent along with the Web API query. Default: none
add_provenance | Optional | Whether to add provenance information as part of the graph that is being produced. Values are true or false. Default: false. More details about produced provenance information can be found [here](../doc/05-prov.md).
custom_parameter.pass_multiple_values_as_csv | Optional | Define how multiple values of an service argument are passed to the Web API: true = as a comma-separated value, false = value is split and Web API is invoked once for each value. Default: true

Example:
```bash
custom_parameter[] = param1
custom_parameter[] = param2
custom_parameter.pass_multiple_values_as_csv[param2] = false
api_query = "https://example.org/api/service/?param1={param1}&param2={param2}"
http_header[Authorization] = "token"
add_provenance = true
```


## Configuration with a SPARQL Service Description file

In this configuration method, the micro-service folder is organized as follows:

```<Web API>/<service>/
    ServiceDescription.ttl  # SPARQL Service Description describing this micro-service
    ShapesGraph.ttl         # optional SHACL description of the graphs produced by the service
    profile.jsonld          # JSON-LD profile to translate the JSON response into JSON-LD
    construct.sparql        # optional SPARQL CONSTRUCT query to create triples that JSON-LD cannot create
    service.php             # optional script to perform specific actions (see 'services/manual_config_example')
```
### Service Description Graph

The micro-service description is provided by an RDF graph following the [SPARQL Service Description](https://www.w3.org/TR/2013/REC-sparql11-service-description-20130321/) recommendation (SD).
See provided examples for more details (conventionally named with extension '_sd'), e.g. [flickr/getPhotosByTags_sd](../services/flickr/getPhotosByTags_sd/ServiceDescription.ttl) or [macaulaylibrary/getAudioByTaxonCode_sd](../services/macaulaylibrary/getAudioByTaxonCode_sd/ServiceDescription.ttl).

The SD graph in file `ServiceDescription.ttl` is described in article [4](../README.md#Publications).
In a nutshell, it describes a ressource that is an instance of `sd:Service` and `sms:Service` (namespace `sms` stands for `http://ns.inria.fr/sparql-micro-service#`) whose data source (`dct:source`) is a Web API (`schema:WebAPI`) that has a search action (`schema:potentialAction`).
In turn, the search action defines the arguments expected by the service and how they are passed on the Web API query string.

**Example**. The example below is a subset of the [macaulaylibrary/getAudioByTaxonCode_sd](../services/macaulaylibrary/getAudioByTaxonCode_sd/ServiceDescription.ttl) description graph.
```sparql
@prefix xsd:     <http://www.w3.org/2001/XMLSchema#>.
@prefix sd:      <http://www.w3.org/ns/sparql-service-description#>.
@prefix frmt:    <http://www.w3.org/ns/formats/>.
@prefix dct:     <http://purl.org/dc/terms/>.
@prefix httpvoc: <http://www.w3.org/2011/http#>.
@prefix shacl:   <http://www.w3.org/ns/shacl#>.
@prefix void:    <http://rdfs.org/ns/void#>.
@prefix hydra:   <http://www.w3.org/ns/hydra/core#>.
@prefix schema:  <http://schema.org/>.
@prefix skos:    <http://www.w3.org/2004/02/skos/core#>.
@prefix dwc:     <http://rs.tdwg.org/dwc/terms/>.
@prefix sms:     <http://ns.inria.fr/sparql-micro-service#>.

@base <http://example.org/macaulaylibrary/getAudioByTaxon_sd/>.
<>
    a sd:Service, sms:Service;
    sd:endpoint <>;
    sd:supportedLanguage sd:SPARQL11Query;
    sd:feature sd:BasicFederatedQuery, sd:EmptyGraphs;
    sd:resultFormat frmt:SPARQL_Results_JSON,  frmt:Turtle, frmt:JSON-LD, frmt:Trig;
    schema:name "Service name...";
    schema:description '''This SPARQL micro-service...''';
    
    sd:defaultDataset [
        a sd:Dataset, void:Dataset;
        sd:defaultGraph [ a sd:Graph; shacl:shapesGraph <ShapesGraph> ];
        sd:namedGraph   [ a sd:Graph; sd:name <ServiceDescription> ];
        sd:namedGraph   [ a sd:Graph; sd:name <ShapesGraph> ];
        
        void:vocabulary
            <http://schema.org/>,
            <http://rs.tdwg.org/dwc/terms/>,
            <http://www.w3.org/ns/shacl#>,
            <http://www.w3.org/ns/hydra/core#>;
        void:sparqlEndpoint <>;
    ];

    sms:exampleQuery '''
        prefix schema: <http://schema.org/>
        prefix dwc: <http://rs.tdwg.org/dwc/terms/>

        SELECT ?audio ?audioFile ?description WHERE {
            ?taxon a dwc:Taxon;
                dwc:scientificName "Delphinus delphis";
                schema:audio [ schema:contentUrl ?audioFile ].
        }''';
    
    sms:cacheExpiresAfter "P2592000S"^^xsd:duration;

    # Add provenance information to the graph generated at each invocation?
    sms:addProvenance "false"^^xsd:boolean;

    dct:source [
        a schema:WebAPI;
        schema:name "Macaulay Library Web API";
        schema:url <https://www.macaulaylibrary.org/>;
        
        # Description of the Web API invocation
        schema:potentialAction [
            a schema:SearchAction, hydra:IriTemplate;
            hydra:template "https://search.macaulaylibrary.org/catalog.json?action=new_search&searchField=animals&sort=upload_date_desc&mediaType=a&taxonCode={identifier}";

            # Description of each argument
            hydra:mapping [
                hydra:variable "identifier";
                schema:description "The Macaulay's taxon code";
                hydra:required "true"^^xsd:boolean;
                hydra:property schema:identifier;
                
                # If multiple values, they should be passed as a CSV value to the Web API
                sms:passMultipleValuesAsCsv "true"^^xsd:boolean;
            ];
            
            # Other HTTP headers to send when incoking the Web API
            a httpvoc:Request;
            httpvoc:headers (
                [ a                  httpvoc:RequestHeader;
                  httpvoc:fieldName  "Authorization";
                  httpvoc:hdrName    <http://www.w3.org/2011/http-headers#authorization>;
                  httpvoc:fieldValue "JWT <api_personal_token>";
                ]
            ).
        ];
    ].
```

The service configuration parameters are expressed in the SD graph as follows:
 
Parameter or property | Mandatory/Optional | Description
------------ | ------------- | -------------
`dct:source` | Mandatory | An instance of `schema:WebAPI` that describes the Web API; its associated search action (`schema:potentialAction`) describes the Web API query string template, the service arguments and optional HTTP headers. See below.
Web API query string template | Mandatory | A `hydra:IriTemplate` (`hydra:template`) providing the Web API query string template. It contains placeholders for the service's input arguments.
Input arguments | Mandatory | Set of `hydra:IriTemplateMapping` resources (`hydra:mapping`) associated with the Web API's potential action. Each argument comes with a name (`hydra:variale`) mentioned in the template, and a mapping to a term of the input SPARQL query's graph pattern, along two methods: `hydra:property` simply gives the predicate to look for in the SPARQL graph pattern, while `shacl:sourceShape` points to the property shape that can help find the term in the graph pattern.
HTTP headers | Optional | Property of the the Web API's potential action. An `http:headers` list whose elements are HTTP headers to be sent to the Web API. Each header consists of a `http:fieldName`, `http:fieldValue` and an optional `http:hdrName`. See the [HTTP Vocabulary in RDF 1.0](https://www.w3.org/WAI/ER/HTTP/WD-HTTP-in-RDF10-20110502). A usage example is provided in [eol/getTraitsByTaxon_sd](../services/eol/getTraitsByTaxon_sd/ServiceDescriptionPrivate.ttl).
`sms:cacheExpiresAfter` | Optional | Property of the `sd:Service` instance. Maximum time (in seconds) to cache responses from the Web API. Default: `"P2592000S"^^xsd:duration` = 30 days
`sms:exampleQuery` | Optional | Property of the `sd:Service` instance. A typical SPARQL query that can be submitted to the service. Used to generate the test interface on the Web page.
`sms:exampleURI` | Optional | Property of the `sd:Service` instance. A URI that can be dereferenced using this service. Used to generate the test interface on the Web page.
`sms:addProvenance` | Optional | Property of the `sd:Service` instance. Whether to add provenance information as part of the graph that is being produced. Values are `"true"^^xsd:boolean` or `"false"^^xsd:boolean`. Default is false
`sms:passMultipleValuesAsCsv` | Optional | Property of a `hydra:IriTemplateMapping` resource (`hydra:mapping`). Defines how multiple values of a service argument are passed to the Web API: `"true"^^xsd:boolean` = as a comma-separated value, `"false"^^xsd:boolean` = value is split and Web API is invoked once for each value. Default: true


### Private information of the Service Description Graph

Since the service description graph is public (it can be queried and dereferenced), it is not suitable to keep sensitive information such as an API private key or security token.
Therefore, a companion file `ServiceDescriptionPrivate.ttl` may be defined, loaded into as separate named graph that is not made public.
Both `ServiceDescription.ttl` and `ServiceDescriptionPrivate.ttl` do state facts about the same service, so that triples can be asserted in one or the other. The service will always work the same, only the public description will vary.

An example is provided in service [flickr/getPhotosByTags_sd](../services/flickr/getPhotosByTags_sd).

### Shapes graph
The service description graph can optionally be accompanied by a [SHACL](https://www.w3.org/TR/2017/REC-shacl-20170720/) shapes graph that specifies the type of graph that the SPARQL micro-service is designed to produce.

## Re-injecting arguments in the graph produced by the micro-service

In certain situations, it may be necessary to re-inject the service arguments into the graph being produced by the service, so that this graph matches the query graph pattern.
This is even mandatory when passing the arguments in client's SPARQL query.

**Example**. In the SPARQL graph pattern below, assume that "sunset" is the value of the `tag` argument that the service expects.
```sparql
?photo
  a schema:Photograph;
  schema:keywords "sunset".
  schema:url ?url.
```

To match this query, the service must not only generate triples with the `schema:url` predicate, but also the triple with the keyword "sunset". This is achieved using a _placeholder_ in the `construct.sparql` file.

In the example below, the `tag` argument (whose value is "sunset" in the query) is re-injected in the graph using placeholder `{tag}`:
 
```sparql
CONSTRUCT {
    <http://example.org/photo/{urlencode(tag)}>
        a schema:Photograph;
        schema:keywords {tag};
        ...
```

**Do not add the double-quotes**, they will be added automatically.
The `{tag}` placeholder is replaced with `"sunset"`.
If more than one value were provided, the placeholder will be replaced by the list of comma-separated, double-quoted values, e.g.: `"sunset", "sea"`.

In case the argument is used to build a URI, the placeholder can contain the `urlencode` keyword to escape special characters, as illustrated above in `<http://example.org/photo/{urlencode(tag)}>`.


## Passing multiple values for the same argument

A service argument may be given multiple values, using either HTTP query string parameters or terms of the SPARQL query graph pattern.

Using terms of the SPARQL query graph pattern, the values are simply passed as multiple values of a predicate, example:

```sparql
?photo
  a schema:Photograph;
  schema:keywords "keyword1", "keyword2".
  schema:url ?url.
```

Using HTTP query string parameters, provide a CSV value, example:

```  https://example.org/micro/service/?keyword=keyword1,keyword2```


In both cases, the argument passed to the Web API will be a comma-separated list of the multiple values, example:

```https://webapi.org/service/?tags=keyword1,keyword2```


## Mapping a Web API response to RDF triples

Translating the Web API JSON response into an RDF graph is carried out in two steps: 
1. Apply a [JSON-LD 1.0](https://www.w3.org/TR/2014/REC-json-ld-20140116/) profile to the response;
2. Optionnally, when mappings are needed that JSON-LD cannot express, a SPARQL CONSTRUCT query enriches the graph (file `construct.sparql`).

The most simple JSON-LD profile is depicted below. It creates ad-hoc terms in the `http://ns.inria.fr/sparql-micro-service/api#` namespace for each property of the JSON response.
```json
{ "@context": {
    "@base": "http://ns.inria.fr/sparql-micro-service/item/",
    "@vocab": "http://ns.inria.fr/sparql-micro-service/api#",
}}
```

This is a handy way of turning the Web API JSON response into RDF, and this allows manipulating the Web API response in a SPARQL query using the `construct.sparql` file.

Note that many well-known namespaces are already declared when executing your CONSTRUCT query ([those defined in the EasyRDF library](https://github.com/njh/easyrdf/blob/master/lib/RdfNamespace.php)), in addition to the ones in the global [config.ini](../src/sparqlms/config.ini) file.
