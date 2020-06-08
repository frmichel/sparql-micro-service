#!/bin/sh

git clone https://github.com/mongodb/mongo-php-driver.git
cd mongo-php-driver

pecl install mongodb
echo "extension=mongodb.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
