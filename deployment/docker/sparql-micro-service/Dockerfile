# https://github.com/docker-library/docs/blob/master/php/README.md#supported-tags-and-respective-dockerfile-links
# https://github.com/mlocati/docker-php-extension-installer

FROM php:7.4-apache
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN apt-get update -y
RUN apt-get install -y git wget zip unzip 

# --- Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/bin && php -r "unlink('composer-setup.php');"
RUN mv /bin/composer.phar /bin/composer

# --- Install PHP MongoDB driver
RUN pecl install mongodb
RUN docker-php-ext-enable mongodb


# ============== Apache/PHP configutation ============

# Enable rewrite and proxy modules in Apache
RUN ln -s /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/rewrite.load
RUN ln -s /etc/apache2/mods-available/proxy.load /etc/apache2/mods-enabled/proxy.load
RUN ln -s /etc/apache2/mods-available/proxy.conf /etc/apache2/mods-enabled/proxy.conf
RUN ln -s /etc/apache2/mods-available/proxy_http.load /etc/apache2/mods-enabled/proxy_http.load

# --- Timezone not defined in default php.ini. Replacing it
RUN echo "date.timezone = 'Europe/Paris'" >> "$PHP_INI_DIR/php.ini"

# --- Add rewrite rules for SPARQL micro-services
COPY sparql-micro-services.conf /etc/apache2/sites-enabled/sparql-micro-services.conf


# ============== SPARQL micro-services install and config ============

ENV INSTALL="/sparql-micro-service"
RUN mkdir $INSTALL
WORKDIR "$INSTALL"

# --- Download SPARQL micro-service code
RUN git clone https://github.com/frmichel/sparql-micro-service.git $INSTALL
RUN git checkout tags/0.5.1

# --- Download the dependencies
RUN composer install -n -vv

# --- Customize the deploy file (Web API keys, URL, paths)
ENV DEPLOY="$INSTALL/deployment/deploy.sh"
RUN cat $DEPLOY | sed 's|^SERVER=.*|SERVER='http://localhost'|g' > /tmp/deploy && mv /tmp/deploy $DEPLOY
ENV BHL_API_KEY="<you API key>"
ENV FLICKR_API_KEY="<you API key>"
RUN chmod 755 $DEPLOY && $DEPLOY

# --- Customize the main config.ini (SPARQL endpoint, SPIN-to-SPARQL, cache DB)
ENV CONFIG="$INSTALL/src/sparqlms/config.ini"
RUN cat $CONFIG | sed 's|^sparql_endpoint.*|sparql_endpoint = http://corese:8081/sparql|g' > /tmp/cfg && mv /tmp/cfg $CONFIG
RUN cat $CONFIG | sed 's|^spin_endpoint.*|spin_endpoint = http://corese:8081/service/sparql-to-spin|g' > /tmp/cfg && mv /tmp/cfg $CONFIG
RUN cat $CONFIG | sed 's|^cache_endpoint.*|cache_endpoint = mongodb://mongo:27017|g' > /tmp/cfg && mv /tmp/cfg $CONFIG

# --- Set logs directory 
ENV LOGS="/var/www/html/sparql-ms/logs"
RUN mkdir -p $LOGS && chmod 777 $LOGS

# --- Deploy the code
RUN mkdir -p /var/www/html/sparql-ms
RUN cp -r $INSTALL/src /var/www/html/sparql-ms/src && cp -r $INSTALL/vendor /var/www/html/sparql-ms/vendor
RUN cp -r $INSTALL/services /var/www/html/sparql-ms/services
