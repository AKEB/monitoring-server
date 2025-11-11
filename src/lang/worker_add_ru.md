Подключение обработчика
=======================

Для подключения обработчика, вам необходимо запустить его на своем сервере.

Создайте `docker-compose.yml` файл
----------------------------------

    services:
      worker:
        container_name: "monitoring-worker"
        image: "akeb/monitoring-worker:latest"
        restart: always
        cap_add:
          - NET_RAW
          - NET_ADMIN
        environment:
          - TZ=${TZ:-UTC} # UTC
          - SERVER_HOST=${SERVER_HOST} # Адрес сервера https://monitoring.example.com/
          - WORKER_KEY_HASH=${WORKER_KEY_HASH} # Hash обработчика. Получить его нужно на сайте сервера
          - DEBUG=${DEBUG:-false} # Debug mode
          - WORKER_THREADS=${WORKER_THREADS:-10} # Кол-во потоков обработчика
          - JOBS_GET_TIMEOUT=${JOBS_GET_TIMEOUT:-10} # Как часто запрашивать новые данные в сервера (в секундах)
          - PROXY_HOST=${PROXY_HOST:-} # Proxy host
          - PROXY_TYPE=${PROXY_TYPE:-} # Proxy type
          - LOOP_TIMEOUT=${LOOP_TIMEOUT:-200000} # Задержка в цикле обработки (в миллисекундах)
          - RESPONSE_SEND_TIMEOUT=${RESPONSE_SEND_TIMEOUT:-10} # Как часто отправлять данные на сервер (в секундах)
          - LOGS_WRITE_TIMEOUT=${LOGS_WRITE_TIMEOUT:-10} # Как часто писать сообщения в логах (в секундах)

Запустите docker compose: `docker compose up -d`
----------------------------------
