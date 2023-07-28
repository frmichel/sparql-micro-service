## Examples

This folder provides a few example SPARQL micro-services allowing for instance to search photos matching some tags on [Flickr](https://www.flickr.com/), or tunes whose titles match a given name in [MusicBrainz](https://musicbrainz.org/).


[Advanceds examples](advanced_examples/) cover special cases:
- Support for APIs with **OAuth2 authentication** (see [advanced_examples/oauth2](advanced_examples/oauth2));
- Support for XML-based Web APIs using a scrpt to turn the XML into JSON (see [advanced_examples/xml_api](advanced_examples/xml_api));
- Allow for intermediate action (query, parsing...) before generating the Web API query (see [advanced_examples/manual_config_example](advanced_examples/manual_config_example))

### Additional examples

You can check out the services we published at [https://sparql-micro-services.org/](https://sparql-micro-services.org/) in a separate Github repository: 
[sparql-micro-services.org/](https://github.com/frmichel/sparql-micro-service.org)

Also, in the [TaxrefWeb](https://github.com/frmichel/taxrefweb/tree/master/sparql-micro-services) repository we provide services to query major biodiversity data sources such as the [GBIF](https://www.biodiversitylibrary.org/), [BHL](https://www.biodiversitylibrary.org/) or [EoL](http://eol.org/traitbank).
