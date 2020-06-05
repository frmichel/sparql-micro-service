This directory provides the necessary script and configuration file to start Corese with a server profile that will load
all existing ServiceDescription.ttl and ShapesGraph.ttl into named graphs.

### How to use

Edit file `corese-profile-sms.ttl` and replace '{INSTALL}' with the actual path where the SPARQL micro-services code is installed.

Edit file `corese-server.sh` and customize variable 'CORESE' if needed.

Then, run:
```bash
./ corese-server.sh &
```

Wait a few seconds for Corese to be initialized (no more trace should appear anymore).
To test if Corese is working properly, run the command below to display all loaded named graphs.
the variable `Q` is the URL-encoded version of SPARQL query: `select distinct ?g where { graph ?g { ?s ?p ?o } }`

```bash
Q='elect%20distinct%20%3Fg%0Awhere%20%7B%20graph%20%3Fg%20%7B%20%3Fs%20%3Fp%20%3Fo%20%7D%20%7D'
curl --header "Accept: application/sparql-results+json" "http://localhost:8081/sparql?query=$Q"
```
