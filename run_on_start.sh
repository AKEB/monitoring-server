#!/bin/bash

cd /app/

# ONLY FOR DEVELOPMENT FRAMEWORK
if [ "$DEVELOPMENT" = "true" ] && [ -d "/app_framework" ]; then
  echo "Development mode: using local framework repository."
  composer config repositories.framework path /app_framework
  cd /app && composer install
else
  echo "Production mode: local /app_framework directory not found. Relying on composer.json for remote repository."
  composer config --unset repositories.framework
  cd /app && composer install --prefer-dist --no-interaction --no-dev --no-scripts
fi

# cd /app && composer install --prefer-dist --no-interaction --no-dev --no-scripts

mkdir -p js/framework/
mkdir -p css/framework/
mkdir -p lang/framework/

rm -rf js/framework/
rm -rf css/framework/
rm -rf lang/framework/

cp -R vendor/akeb/framework/src/js js/framework/
cp -R vendor/akeb/framework/src/css css/framework/
cp -R vendor/akeb/framework/src/lang lang/framework/

cd /app/vendor/akeb/framework/src/ && SERVER_ROOT=/app php migrate.php

cd /app/vendor/akeb/framework/src/crons/ && SERVER_ROOT=/app ./run_all.sh

cd /app/crons/ && ./run_all.sh

echo "Server started successfully.";
echo "For run server in browser type: http://127.0.0.1:${NGINX_PORT}"

if [ "$DEVELOPMENT" = "true" ] && [ -d "/app_framework" ]; then
	echo "For run phpmyadmin in browser type: http://127.0.0.1:${PHPMYADMIN_PORT}"
fi
