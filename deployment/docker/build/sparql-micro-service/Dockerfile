FROM php:7.4-apache
ARG SMS_VERSION
ENV SMS_VERSION=$SMS_VERSION

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
COPY apache.conf /etc/apache2/sites-enabled/apache-sparql-micro-services.conf


# ============== SPARQL micro-services install and config ============

ENV INSTALL="/sparql-micro-service"
RUN mkdir $INSTALL
WORKDIR "$INSTALL"

# --- Download SPARQL micro-service code
RUN git clone https://github.com/frmichel/sparql-micro-service.git $INSTALL
RUN git checkout --quiet tags/$SMS_VERSION

# --- Install the php dependencies
RUN composer install -n -vv

# --- Set logs directory 
ENV LOGS="/var/www/html/sparql-ms/logs"
RUN mkdir -p $LOGS && chmod 777 $LOGS

# --- Deploy the code
RUN mkdir -p /var/www/html/sparql-ms
RUN cp -r $INSTALL/src /var/www/html/sparql-ms/src && cp -r $INSTALL/vendor /var/www/html/sparql-ms/vendor
RUN mkdir -p /services && chmod 777 /services

# Fix a pb with some MacOS platforms
RUN echo 'Mutex posixsem' >> /etc/apache2/apache2.conf
