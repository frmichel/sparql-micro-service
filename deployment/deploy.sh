#!/bin/bash
#
# This script customizes the configuration files (config.ini), SPARQL files (construct.sparql)
# and service description files (ServiceDescription.ttl and ShapesGraph.ttl) of each SPARQL micro-serive
# by replacing the "example.org" hostname and "<api_key>" values commited on the public repo.
#
# Usage: 
# Copy this script in the public_html folder where the SPARQL micro-service code is installed
# (there must be an src subfolder here). CD to public_html and run the script.

# The URL of the server where the services are accessible. Will replace the 'http://example.org'
SERVER='https://sparql-micro-services.org'

# Path to append to the server URL
SERVERPATH=service

# Relative directory where to search for SPARQL microservices,
# starting from the directory where this script is launched
SMSDIR=services

function substitute() {
    # Optional: first reset commited version
    #git checkout HEAD -- $3
    sed "s|$1|$2|g" $3 > $3.tmp
    mv $3.tmp $3
}

# ================================== Set API keys ==========================

# --- BHL API key ---
API_KEY='<paste your api key here>'
for FILE in $(ls $SMSDIR/bhl/*/config.ini 2> /dev/null); do
    replace='<api_key>'
    echo "Changing $replace into $API_KEY in $FILE"
    substitute "$replace" "$API_KEY" "$FILE"
done

# --- Flickr API key ---
API_KEY='<paste your api key here>'
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
API_KEY='<paste your api token here>'
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


# ================================== Set hostname ==========================

# --- Replace server hostname in URIs http://example.org/ld/... of sparql files
for FILE in `ls $SMSDIR/*/*/*.sparql`; do
    replace='http://example.org/ld'
    echo "Changing $replace into $SERVER/ld in $FILE"
    substitute "$replace" "$SERVER/ld" "$FILE"
done

# --- Replace server hostname in example URIs http://example.org/ld/... of service description files
for FILE in `ls $SMSDIR/*/*/ServiceDescription.ttl`; do
    replace='http://example.org/ld'
    echo "Changing $replace into $SERVER/ld in $FILE"
    substitute "$replace" "$SERVER/ld" "$FILE"
done

# --- Replace http://example.org with deployment URL in service description and shape graph files
for FILE in `ls $SMSDIR/*/*/*.ttl`; do
    replace='http://example.org/sparql-ms'
    echo "Changing $replace into $SERVER/$SERVERPATH in $FILE"
    substitute "$replace" "$SERVER/$SERVERPATH" "$FILE"
done

# --- Replace http://example.org with deployment URL in root_url in config.ini file
for FILE in `ls src/sparqlms/config.ini`; do
    replace='http://example.org/sparql-ms'
    echo "Changing $replace into $SERVER/$SERVERPATH in $FILE"
    substitute "$replace" "$SERVER/$SERVERPATH" "$FILE"
done
