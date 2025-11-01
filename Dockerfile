FROM akeb/nginx-php-fpm-8.4:latest

ARG SERVER_VERSION="v0.0.0"
ENV SERVER_VERSION=${SERVER_VERSION}

COPY ./src/ /app/
COPY default.conf /etc/nginx/conf.d/default.conf
COPY run_on_start.sh /run_on_start.sh

RUN composer config --unset repositories.framework \
    && rm -rf vendor/ composer.lock \
    && composer install --prefer-dist --no-interaction --no-dev --no-scripts --no-autoloader --no-progress \
    && composer dump-autoload --optimize \
    && mkdir /app/logs/ \
    && echo "<?php\ndefine('SERVER_VERSION', '${SERVER_VERSION}');" > /app/version.php
