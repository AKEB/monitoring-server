#!/bin/bash

LOG="/var/log/php/mngr_clear.log"
PHP="php -d memory_limit=32M -d allow_url_fopen=true -d error_log=${LOG}"

while [ 1 ] ;
do
     # Сохраняем весь вывод скрипта
    output=`${PHP} mngr_clear.php ${1}`
    status=$?
    if [ $status -eq 0 ]
    then
        # Получаем последнее слово из последней строки
        sleep_time=$(echo "$output" | tail -n1 | awk '{print $NF}')
        if [[ $sleep_time =~ ^[0-9]+$ ]]
        then
            sleep $sleep_time
        else
            sleep 1
        fi
    else
        sleep 2
    fi
done