#!/bin/bash
#
# This script customizes the configuration files (config.ini), SPARQL files (insert.sparql, construct.sparql)
# and service description files (ServiceDescription.ttl) of each SPARQL micro-serive by replacing 
# the "example.org" hostname and "<api_key>" values commited on the public repo.
#
# Usage: 
# Copy this script in the public_html folder where the SPARQL micro-service code is installed
# (there must be an src subfolder here). CD to public_html and run the script.

# The machine where the services are deployed. Will replace the 'http://example.org'
SERVER='http:\/\/sms.i3s.unice.fr'

# Directory where to search for SPARQL microservices, from the directory where this script is launched
SMSDIR=src/sparqlms

function substitute() {
    # Optional: first reset commited version
    #git checkout HEAD -- $3
    sed "s/$1/$2/g" $3 > $3.tmp
    mv $3.tmp $3
}

# --- BHL API key ---
API_KEY=<paste your api key here>
for FILE in $(ls $SMSDIR/bhl/*/config.ini 2> /dev/null); do
    replace='<api_key>'
    echo "Changing $replace into $API_KEY in $FILE"
    substitute "$replace" "$API_KEY" "$FILE"
done

# --- Flickr API key ---
API_KEY=<paste your api key here>
for FILE in $(ls $SMSDIR/flickr/*/config.ini 2> /dev/null); do
    replace='<api_key>'
    echo "Changing $replace into $API_KEY in $FILE"
    substitute "$replace" "$API_KEY" "$FILE"
done
for FILE in $(ls $SMSDIR/flickr/*/ServiceDescription*.ttl 2> /dev/null); do
    replace='<api_key>'
    echo "Changing $replace into $API_KEY in $FILE"
    substitute "$replace" "$API_KEY" "$FILE"
done

# --- EoL API token ---
API_KEY= <paste your api token here>
for FILE in $(ls $SMSDIR/eol/*/config.ini 2> /dev/null); do
    replace='<api_personal_token>'
    echo "Changing $replace into $API_KEY in $FILE"
    substitute "$replace" "$API_KEY" "$FILE"
done
for FILE in $(ls $SMSDIR/eol/*/ServiceDescription*.ttl 2> /dev/null); do
    replace='<api_personal_token>'
    echo "Changing $replace into $API_KEY in $FILE"
    substitute "$replace" "$API_KEY" "$FILE"
done

# --- Replace example.org with local server URL in sparql files ---
for FILE in `ls $SMSDIR/*/*/*.sparql`; do
    replace='http:\/\/example.org'
    echo "Changing $replace into $SERVER in $FILE"
    substitute "$replace" "$SERVER" "$FILE"
done

# --- Replace example.org with local server URL in service description and shape graph files ---
for FILE in `ls $SMSDIR/*/*/*.ttl`; do
    replace='http:\/\/example.org'
    echo "Changing $replace into $SERVER/sparql-ms in $FILE"
    substitute "$replace" "$SERVER\/sparql-ms" "$FILE"
done

# --- Replace example.org with local server URL in config.ini file ---
for FILE in `ls $SMSDIR/config.ini`; do
    replace='http:\/\/example.org'
    echo "Changing $replace into $SERVER/sparql-ms in $FILE"
    substitute "$replace" "$SERVER\/sparql-ms" "$FILE"
done
