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

# Namespace for the description and shapes graphs.
# Should be the same as the root URL of SPARQL micro-serivces
NAMESPACE=http://sms.i3s.unice.fr/sparql-ms

SPARQLMS=$HOME/public_html/sparql-ms-live/src/sparqlms/

CORESE=$HOME/Corese
LOG4J=file://$CORESE/log4j2.xml
JAR=$CORESE/corese-server-4.0.2-SNAPSHOT-20181213.jar

function genLoad() {
    echo "  [ a sw:Load; " >> $PROFILE
    echo "      sw:path <$1>;" >> $PROFILE
    echo "      sw:name <$2> ]" >> $PROFILE
}

#--- Prepare the Corese profile for loading all ServiceDescription and ShapesGraph files
cd $SPARQLMS
PROFILE=/tmp/corese-profile-sms.ttl
rm -f $PROFILE
echo "st:smsdesc a sw:Workflow; sw:body (" >> $PROFILE
for file in $(ls */*/ServiceDescription.ttl 2> /dev/null); do
    GRAPH=${NAMESPACE}/$(dirname "${file}")/ServiceDescription
    genLoad "$SPARQLMS/$file" "$GRAPH"
done
for file in $(ls */*/ServiceDescriptionPrivate.ttl 2> /dev/null); do
    GRAPH=${NAMESPACE}/$(dirname "${file}")/ServiceDescriptionPrivate
    genLoad "$SPARQLMS/$file" "$GRAPH"
done
for file in $(ls */*/ShapesGraph.ttl 2> /dev/null); do
    GRAPH=${NAMESPACE}/$(dirname "${file}")/ShapesGraph
    genLoad "$SPARQLMS/$file" "$GRAPH"
done
echo ').' >> $PROFILE
echo '' >> $PROFILE
cat $CORESE/corese-profile-sms.ttl >> $PROFILE

echo "Corese profile:"
cat $PROFILE

#--- Start Corese with the profile
cd $CORESE
java -Dfile.encoding="UTF-8" -Dlog4j.configurationFile=$LOG4J -jar $JAR -lp -pp file://$PROFILE -p 8081
