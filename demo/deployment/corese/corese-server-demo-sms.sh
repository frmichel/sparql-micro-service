#!/bin/bash

# Start Corese server for the SµS demo
# Browse to http://localhost:8082/tutorial/demo?param=Delphinus+delphis

CORESE=$HOME/Corese
LOG4J=file://$CORESE/log4j2-demosms.xml
JAR=$CORESE/corese-server-4.1.6d.jar

PROFILE=file://$HOME/public_html/demo-sms/profile.ttl

cd $CORESE
java \
    -Dfile.encoding="UTF-8" \
    -Dlog4j.configurationFile=$LOG4J \
    -jar $JAR \
    -lp \
    -pp file://$PROFILE -p 8082 &
