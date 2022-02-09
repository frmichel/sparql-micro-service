FROM openjdk:11.0.14-jdk-bullseye
ARG CORESEJAR
ENV CORESEJAR=$CORESEJAR
ARG SMS_VERSION
ENV SMS_VERSION=$SMS_VERSION

RUN apt-get update -y 
RUN apt-get install -y git wget apache2

# ============== Get the SPARQL micro-services project ============
# This is needed to get sttl files (generation of service web pages) served by Apache

ENV INSTALL="/sparql-micro-service"
RUN mkdir $INSTALL
WORKDIR "$INSTALL"
RUN git clone https://github.com/frmichel/sparql-micro-service.git $INSTALL
RUN git checkout --quiet tags/$SMS_VERSION


# ============== Configure Apache to serve STTL files on localhost ============

COPY apache-sttl.conf /etc/apache2/conf-enabled/apache-sttl.conf


# ============== Configure Corese ============

ENV CORESE="/usr/local/corese" 
RUN mkdir -p $CORESE
WORKDIR "$CORESE"

COPY corese-profile-sms.ttl $CORESE/corese-profile-sms.ttl
COPY log4j2.xml $CORESE/log4j2.xml

ENV CORESESH=$CORESE/corese-server.sh
COPY corese-server.sh $CORESESH
RUN chmod 755 $CORESESH

RUN wget http://files.inria.fr/corese/distrib/$CORESEJAR

RUN mkdir -p /services

# ============== Run Apache and Corese ============

RUN echo "/usr/sbin/apachectl start" > run.sh && echo "$CORESESH" >> run.sh && chmod 755 run.sh
CMD $CORESE/run.sh
