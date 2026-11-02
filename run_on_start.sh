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

touch /var/log/php/fpm-php.www.log 
touch /var/log/php/mngr_aggregate.log 
touch /var/log/php/mngr_aggregate_today.log 
touch /var/log/php/mngr_clear.log 
touch /var/log/php/mngr_sessions.log 
touch /var/log/php/mngr_ssl_notify.log 
touch /var/log/php/mngr_external.log
touch /var/log/php/mngr_alert_actions.log 
touch /var/log/php/php-fpm.log

touch /var/log/nginx/access.log
touch /var/log/nginx/error.log

chmod -R 0777 /var/log/php/
chmod -R 0777 /var/log/nginx/
chown -R www-data:www-data /var/log/php/


echo "Server started successfully.";
echo "For run server in browser type: http://127.0.0.1:${NGINX_PORT}"

if [ "$DEVELOPMENT" = "True" ] ; then
	echo "For run phpmyadmin in browser type: http://127.0.0.1:${PHPMYADMIN_PORT}"
fi
