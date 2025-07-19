FROM akeb/nginx-php-fpm-8.3:latest

ARG SERVER_VERSION="v0.0.0"
ENV SERVER_VERSION=${SERVER_VERSION}

COPY ./src/ /app/
WORKDIR /app/
RUN mkdir /app/logs/

COPY default.conf /etc/nginx/conf.d/default.conf

COPY run_on_start.sh /run_on_start.sh

RUN composer install
RUN touch /app/version.php
RUN echo '<?php\n' > /app/version.php
RUN echo 'define("SERVER_VERSION", "'${SERVER_VERSION}'");' >> /app/version.php
RUN echo 'define("SERVER_URL", "'${SERVER_URL}'");' >> /app/version.php

# CMD ["php", "main.php"]
