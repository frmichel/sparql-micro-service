#!/bin/bash
# This file can be used to automated the execution of this service followed by the loading of the result
# into a Virtuoso endpoint.

export COVIDONTHEWEB=/appli/covidontheweb

log() {
    echo "[$(date '+%F %T')] $1"
}

echo "==========================================================================="
log "Starting..."

# Initialize turtle file with basic definitions
output=dump-opencovid19fr-$(date '+%Y%m%d_%H%M%S').ttl
cp dump-init.ttl "$output"

# Query the SPARQL micro-service that translates the json data into RDF
log "Querying OpenCovid19-fr SPARQL micro-service..."
Q=construct%20where%20%7B%20%3Fs%20%3Fp%20%3Fo%20%7D
curl -H "accept: text/turtle" --connect-timeout 120 --max-time 300 --retry 4 \
     --output "$output" \
     "https://sparql-micro-services.org/service/opencovid19fr/dailyCases_sd?query=${Q}"

# Check minimum output size to make sure the query completed correctly
if [  $(wc -c <"$output") -lt  "10000000" ]; then
    log "Unexpected output size < 10MB. Stopping."
    exit 0
fi
log "Produced file $output: $(wc -l <"$output") lines, $(du -k $output | cut -f1) KB."

# Replace existing graph with new one
log "Importing graph in Virtuoso..."; echo
graph="http://ns.inria.fr/covid19/graph/opencovid19fr"
$COVIDONTHEWEB/src/virtuoso/virtuoso-import.sh --cleargraph --graph $graph --path $(pwd) "$output"

log "Zipping result file..."
zip dump-opencovid19fr.zip $output
rm -f $output

echo; log "Done."
