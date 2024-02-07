#!/bin/bash
# This script starts Corese with a server profile that will load
# all existing files ServiceDescription.ttl and ShapesGraph.ttl into named graphs.
#
# The graph URIs are based on the server hostname and path to access SPARQL micro-services.
#
# To test the result, run the command below to display all loaded named graphs:
# SPARQL query Q below is the url-encoded for: "select distinct ?g where { graph ?g { ?s ?p ?o } }"
# $ Q='elect%20distinct%20%3Fg%0Awhere%20%7B%20graph%20%3Fg%20%7B%20%3Fs%20%3Fp%20%3Fo%20%7D%20%7D'
# $ curl --header "Accept: application/sparql-results+json" "http://localhost:8080/sparql?query=$Q"

env

# Following env. variables must be set by Dockerfile: CORESE=path, $CORESEJAR=name of the jar without path
LOG4J=file://$CORESE/log4j2.xml
JAR=$CORESE/$CORESEJAR

# Root path of the SPARQL micro-service Github repository
SMSPATH=/sparql-micro-service

function genLoad() {
    echo "  [ a sw:Load; " >> $PROFILE
    echo "      sw:path <$1>;" >> $PROFILE
    echo "      sw:name <$2> ]" >> $PROFILE
}

# Generate the instructions for loading all ServiceDescription and ShapesGraph files from a given location
# Parameters:
#   $1: URL at which the SPARQL micro-services are accessible
#   $2: path where the SPARQL micro-services are deployed
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

# Generate the loading instructions for the SPARQL micro-services
genMultipleLoad "http://localhost/service" "/services"

# Complete the profile
echo ').' >> $PROFILE
echo '' >> $PROFILE
cat $CORESE/corese-profile-sms.ttl | sed "s|{INSTALL}|$SMSPATH|g" >> $PROFILE

echo "=========== Corese profile:"
cat $PROFILE

#--- Start Corese with the profile
# Note: option -re = re-entrant mode to allow for a SPARQL µs to call another one
#       option -su = allows access fo file system as well as any endpoint in a SERVICE clause
cd $CORESE
$JAVA_HOME/bin/java \
    -Dfile.encoding="UTF-8" \
    -Dlog4j.configurationFile=$LOG4J \
    -jar $JAR \
    -lp \
    -pp file://$PROFILE -p 8081 \
    -su -re
