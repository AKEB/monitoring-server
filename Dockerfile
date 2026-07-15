FROM akeb/nginx-php-fpm-8.4:latest

ARG SERVER_VERSION="v0.0.0"
ENV SERVER_VERSION=${SERVER_VERSION}

COPY ./src/ /app/
COPY default.conf /etc/nginx/conf.d/default.conf
COPY run_on_start.sh /run_on_start.sh

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates wget unzip \
    && wget -q https://gu-st.ru/content/lending/linux_russian_trusted_root_ca_pem.zip -O /tmp/russian_root_ca.zip \
    && wget -q https://gu-st.ru/content/lending/russian_trusted_sub_ca_pem.zip -O /tmp/russian_sub_ca.zip \
    && unzip -o /tmp/russian_root_ca.zip -d /usr/local/share/ca-certificates/ \
    && unzip -o /tmp/russian_sub_ca.zip -d /usr/local/share/ca-certificates/ \
    && update-ca-certificates \
    && rm -rf /tmp/russian_root_ca.zip /tmp/russian_sub_ca.zip \
    && apt-get purge -y wget unzip \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* \
    && composer config --unset repositories.framework \
    && rm -rf vendor/ composer.lock \
    && composer install --prefer-dist --no-interaction --no-dev --no-scripts --no-autoloader --no-progress \
    && composer dump-autoload --optimize \
    && mkdir /app/logs/ \
    && echo "<?php\ndefine('SERVER_VERSION', '${SERVER_VERSION}');" > /app/version.php
