#!/bin/bash
# This script starts Corese with a server profile that will load
# all existing files ServiceDescription.ttl and ShapesGraph.ttl into named graphs.
#
# The graph URIs are based on the server hostname and path to access SPARQL microservices.
#
# To test the result, run the command below to display all loaded named graphs:
# SPARQL query: select distinct ?g where { graph ?g { ?s ?p ?o } }
# $ curl --header "Accept: application/sparql-results+json" \
#      "http://localhost:8081/sparql?query=select%20distinct%20%3Fg%0Awhere%20%7B%20graph%20%3Fg%20%7B%20%3Fs%20%3Fp%20%3Fo%20%7D%20%7D"

CORESE=$HOME/Corese
LOG4J=file://$CORESE/log4j2.xml
JAR=$CORESE/corese-server-4.1.1-SNAPSHOT-20190408.jar

function genLoad() {
    echo "  [ a sw:Load; " >> $PROFILE
    echo "      sw:path <$1>;" >> $PROFILE
    echo "      sw:name <$2> ]" >> $PROFILE
}

# Generate the instructions for loading all ServiceDescription and ShapesGraph files in a given location
# Parameters:
#   $1: path where the SPARQL micro-services are deployed
#   $2: URL at which they should be made accessible
function genMultipleLoad() {
    SERVER_URL=$1
    SMS_PATH=$2
    cd $SMS_PATH
    for file in $(ls */*/ServiceDescription.ttl 2> /dev/null); do
        GRAPH=${SERVER_URL}/$(dirname "${file}")/ServiceDescription
        genLoad "$SMS_PATH/$file" "$GRAPH"
    done
    for file in $(ls */*/ServiceDescriptionPrivate.ttl 2> /dev/null); do
        GRAPH=${SERVER_URL}/$(dirname "${file}")/ServiceDescriptionPrivate
        genLoad "$SMS_PATH/$file" "$GRAPH"
    done
    for file in $(ls */*/ShapesGraph.ttl 2> /dev/null); do
        GRAPH=${SERVER_URL}/$(dirname "${file}")/ShapesGraph
        genLoad "$SMS_PATH/$file" "$GRAPH"
    done
}

# Prepare the Corese profile for loading all ServiceDescription and ShapesGraph files
PROFILE=/tmp/corese-profile-sms.ttl
rm -f $PROFILE
echo "st:smsdesc a sw:Workflow; sw:body (" >> $PROFILE

# Generate the loading instructions for the SPARQL micro-services from all locations
genMultipleLoad "http://sms.i3s.unice.fr/sparql-ms"         "$HOME/public_html/sparql-ms-live/services"
genMultipleLoad "https://sparql-micro-services.org/service" "$HOME/public_html/sparql-micro-services.org"

# Complete the profile
echo ').' >> $PROFILE
echo '' >> $PROFILE
cat $CORESE/corese-profile-sms.ttl >> $PROFILE

echo "Corese profile:"
cat $PROFILE

#--- Start Corese with the profile
cd $CORESE
java -Dfile.encoding="UTF-8" -Dlog4j.configurationFile=$LOG4J -jar $JAR -lp -pp file://$PROFILE -p 8081 -re
