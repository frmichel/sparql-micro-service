FROM openjdk:11.0.7-jdk-buster

RUN apt-get update -y 
RUN apt-get install -y git wget 

# Corese needs to load the service description and shapes graphs at startup.
# Hence, we have to checkout the project and customize it as in the sparql-micro-service build.

# ============== SPARQL micro-services install ============

# --- Download SPARQL micro-service code
ENV INSTALL="/sparql-micro-service"
RUN mkdir $INSTALL
WORKDIR "$INSTALL"
RUN git clone https://github.com/frmichel/sparql-micro-service.git $INSTALL

# --- Customize the deploy file (Web API keys, URL, paths)
ENV DEPLOY="$INSTALL/deployment/deploy.sh"
RUN cat $DEPLOY | sed 's|^SERVER=.*|SERVER='http://localhost'|g' > /tmp/deploy && mv /tmp/deploy $DEPLOY
ENV BHL_API_KEY="e2cd8640-0302-43b8-84ba-af635d572e2c"
ENV FLICKR_API_KEY="84292db278ee7fe90f930e6a87ae532d"
RUN chmod 755 $DEPLOY && $DEPLOY


# ============== Configure and run Corese ============

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
# Specify the path where to look for service descriptions
RUN cat $CORESESH | sed "s|^genMultipleLoad.*|genMultipleLoad \"http://localhost/service\" \"$INSTALL/services\"|g" > /tmp/corese && mv /tmp/corese $CORESESH

RUN chmod 755 $CORESESH
RUN cat $CORESESH

# --- Run Corese
WORKDIR $CORESE
CMD $CORESESH

