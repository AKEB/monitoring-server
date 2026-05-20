Подключение воркера
===================

Для подключения воркера запустите его на своём сервере.

Создайте файл `docker-compose.yml`
-----------------------------------

    services:
      worker:
        container_name: "monitoring-worker"
        image: "akeb/monitoring-worker-go:latest"
        restart: always
        cap_add:
          - NET_RAW
          - NET_ADMIN
        environment:
          - TZ=${TZ:-UTC} # UTC
          - SERVER_HOST=${SERVER_HOST} # Адрес сервера https://monitoring.example.com/
          - WORKER_KEY_HASH=${WORKER_KEY_HASH} # Хеш воркера с сервера
          - PROTOCOL_VERSION=2.0 # Обязательно (monitoring-server v2.1+)
          - DEBUG=${DEBUG:-false} # Отладка
          - CURL_DEBUG=${CURL_DEBUG:-false} # Трассировка HTTP-запросов
          - DOCKER_DEBUG=${DOCKER_DEBUG:-false} # Отладка Docker
          - WORKER_THREADS=${WORKER_THREADS:-10} # Число потоков
          - JOBS_GET_TIMEOUT=${JOBS_GET_TIMEOUT:-10} # Как часто запрашивать задачи (сек)
          - PROXY_HOST=${PROXY_HOST:-} # URL прокси
          - PROXY_TYPE=${PROXY_TYPE:-0} # Тип прокси (константы PHP cURL: 0 HTTP, 5 SOCKS5, …)
          - LOOP_TIMEOUT=${LOOP_TIMEOUT:-200000} # Задержка в цикле (мкс)
          - RESPONSE_SEND_TIMEOUT=${RESPONSE_SEND_TIMEOUT:-10} # Как часто отправлять результаты (сек)
          - LOGS_WRITE_TIMEOUT=${LOGS_WRITE_TIMEOUT:-10} # Как часто писать служебные логи (сек)

Запуск: `docker compose up -d`
------------------------------

Перезапуск из админки (кнопка **Restart worker** на странице редактирования воркера) один раз отдаёт `restart: true` в `get/`; процесс завершается — перезапуск делает Docker/systemd.

Полное описание протокола: `docs/WORKER_API.md` в репозитории monitoring-server.
