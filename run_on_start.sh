#!/bin/bash

cd /app/

if [ "$DEVELOPMENT" = "True" ] ; then
	echo "Development mode"
else
	echo "Production mode"
fi

mkdir -p js/framework css/framework lang/framework
rm -rf js/framework css/framework lang/framework

cp -R vendor/akeb/framework/src/js js/framework/
cp -R vendor/akeb/framework/src/css css/framework/
cp -R vendor/akeb/framework/src/lang lang/framework/

cd /app/vendor/akeb/framework/src/ && SERVER_ROOT=/app php migrate.php

cd /app/vendor/akeb/framework/src/crons/ && chmod +x *.sh && SERVER_ROOT=/app ./run_all.sh

cd /app/crons/ && chmod +x *.sh && ./run_all.sh

echo "Server started successfully.";
echo "For run server in browser type: http://127.0.0.1:${NGINX_PORT}"

if [ "$DEVELOPMENT" = "True" ] ; then
	echo "For run phpmyadmin in browser type: http://127.0.0.1:${PHPMYADMIN_PORT}"
fi
