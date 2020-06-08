FROM openjdk:11.0.7-jdk-buster

RUN apt-get update -y
RUN apt-get install -y git wget


# --- Download SPARQL micro-service code (to get log4j config, corese profile and startup script)

ENV INSTALL="/sparql-micro-service"
RUN mkdir $INSTALL
WORKDIR "$INSTALL"
RUN git clone https://github.com/frmichel/sparql-micro-service.git $INSTALL


# --- Download and install Corese

ENV CORESE="/usr/local/corese"
RUN mkdir -p $CORESE
WORKDIR "$CORESE"

RUN wget http://wimmics.inria.fr/doc/tutorial/corese-server-4.1.4a.jar
RUN cp  $INSTALL/deployment/corese/log4j2.xml $CORESE
RUN cat $INSTALL/deployment/corese/corese-profile-sms.ttl | sed "s|{INSTALL}|$INSTALL|g" > $CORESE/corese-profile-sms.ttl

# --- Configure the script to start Corese

ENV CORESESH=$CORESE/corese-server.sh
RUN cp $INSTALL/deployment/corese/corese-server.sh $CORESESH

RUN cat $CORESESH | sed "s|^CORESE.*|CORESE=$CORESE|g" > /tmp/corese && mv /tmp/corese $CORESESH
RUN cat $CORESESH | sed "s|^SMSPATH.*|SMSPATH=$INSTALL|g" > /tmp/corese && mv /tmp/corese $CORESESH
RUN cat $CORESESH | sed "s|^java.*|$JAVA_HOME/bin/java \\\|g" > /tmp/corese && mv /tmp/corese $CORESESH
# Remove loading of any service description
RUN cat $CORESESH | sed "s|^genMultipleLoad.*||g" > /tmp/corese && mv /tmp/corese $CORESESH

RUN chmod 755 $CORESESH
RUN cat $CORESESH

# --- Run Corese
WORKDIR $CORESE
CMD $CORESESH

