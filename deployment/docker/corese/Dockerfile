FROM openjdk:11.0.7-jdk-buster

RUN apt-get update -y 
RUN apt-get install -y git wget apache2

# Corese needs to load the service description and shapes graphs at startup.
# These are given in directory /services that has to be mounted on the Docker server side.

# ============== Get the SPARQL micro-services project ============
# This is to get Corese log4l config, prfile and start script.

# --- Download SPARQL micro-service code
ENV INSTALL="/sparql-micro-service"
RUN mkdir $INSTALL
WORKDIR "$INSTALL"
RUN git clone https://github.com/frmichel/sparql-micro-service.git $INSTALL
RUN git checkout tags/0.5.2

# ============== Configure Apache to servce STTL files on localhost ============
# This is necessary with Corese v4.1.6+ as accessing html/sttl files from local file system is no longer permitted
COPY sparql-micro-services.conf /etc/apache2/conf-enabled/sparql-micro-services.conf


# ============== Configure and run Corese ============

# --- Download and install Corese

ENV CORESE="/usr/local/corese"
RUN mkdir -p $CORESE
WORKDIR "$CORESE"

RUN wget http://files.inria.fr/corese/distrib/corese-server-4.1.6d.jar
RUN cp  $INSTALL/deployment/corese/log4j2.xml $CORESE
RUN cat $INSTALL/deployment/corese/corese-profile-sms.ttl | sed "s|{INSTALL}|$INSTALL|g" > $CORESE/corese-profile-sms.ttl

# --- Configure the script to start Corese

ENV CORESESH=$CORESE/corese-server.sh
RUN cp $INSTALL/deployment/corese/corese-server.sh $CORESESH

RUN cat $CORESESH | sed "s|^CORESE.*|CORESE=$CORESE|g" > /tmp/corese && mv /tmp/corese $CORESESH
RUN cat $CORESESH | sed "s|^SMSPATH.*|SMSPATH=$INSTALL|g" > /tmp/corese && mv /tmp/corese $CORESESH
RUN cat $CORESESH | sed "s|^java.*|$JAVA_HOME/bin/java \\\|g" > /tmp/corese && mv /tmp/corese $CORESESH

# Remove final '&' to have the script not exit
RUN cat $CORESESH | sed "s|^[[:blank:]]*&||g" > /tmp/corese && mv /tmp/corese $CORESESH

# Specify the path where to look for service descriptions (/services has to be mounted on the Docker server side)
RUN cat $CORESESH | sed "s|^genMultipleLoad.*|genMultipleLoad \"http://localhost/service\" \"/services\"|g" > /tmp/corese && mv /tmp/corese $CORESESH


RUN chmod 755 $CORESESH
RUN cat $CORESESH

# --- Run Apache and Corese
WORKDIR $CORESE
RUN echo "/usr/sbin/apachectl start" > run.sh && echo "$CORESESH" >> run.sh && chmod 755 run.sh
CMD $CORESE/run.sh
