#!/bin/bash

HOST_DIR=${1}
APP_DIR=${HOST_DIR}/src
DOCKER_DIR="/app"
SCRIPT_DIR=`dirname ${2}`/
FILE_NAME=${2/"$SCRIPT_DIR"/""}
SCRIPT_DIR=${SCRIPT_DIR/"$APP_DIR"/""}

ARGS=''
for ((argnum = 3; argnum <= $#; argnum++)); do
  ARGS="${ARGS}${!argnum} "
done

# echo /usr/local/bin/docker compose -f ${HOST_DIR}/docker-compose.yml exec -t server bash -c "cd ${DOCKER_DIR}${SCRIPT_DIR} && php ${FILE_NAME} ${ARGS}"

/usr/local/bin/docker compose -f ${HOST_DIR}/docker-compose.yml exec -t server bash -c "cd ${DOCKER_DIR}${SCRIPT_DIR} && php ${FILE_NAME} ${ARGS}"
