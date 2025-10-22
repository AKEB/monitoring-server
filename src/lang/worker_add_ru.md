Подключение обработчика
=======================

Для подключения обработчика, вам необходимо запустить его на своем сервере.

Создайте `docker-compose.yml` файл
----------------------------------

    services:
      worker:
        container_name: "monitoring-worker"
        image: "akeb/monitoring-worker:latest"
        restart: unless-stopped
        environment:
          - TZ=UTC
          - WORKER_KEY_HASH=${WORKER_KEY_HASH}
          - SERVER_HOST=${SERVER_HOST}
          # - WORKER_THREADS=4
          # - JOBS_GET_TIMEOUT=10
          # - PROXY_HOST=
          # - PROXY_TYPE=
          # - LOOP_TIMEOUT=200000
          # - RESPONSE_SEND_TIMEOUT=10
          # - LOGS_WRITE_TIMEOUT=10

Запустите docker compose: `docker compose up -d`
----------------------------------
