Connecting the Worker
=====================

To connect the worker, you need to run it on your server.

Create a `docker-compose.yml` file
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
          - SERVER_HOST=${SERVER_HOST} # Server host https://monitoring.example.com/
          - WORKER_KEY_HASH=${WORKER_KEY_HASH} # Worker hash. Get it from your server
          - WORKER_THREADS=${WORKER_THREADS:-10} # Worker threads count
          - JOBS_GET_TIMEOUT=${JOBS_GET_TIMEOUT:-10} # How often to request new data from the server (in seconds)
          - PROXY_HOST=${PROXY_HOST:-} # Proxy host
          - PROXY_TYPE=${PROXY_TYPE:-} # Proxy type
          - LOOP_TIMEOUT=${LOOP_TIMEOUT:-200000} # Delay in the processing loop (in milliseconds)
          - RESPONSE_SEND_TIMEOUT=${RESPONSE_SEND_TIMEOUT:-10} # How often to send data to the server (in seconds)
          - LOGS_WRITE_TIMEOUT=${LOGS_WRITE_TIMEOUT:-10} # How often to write logs to the file (in seconds)

Run docker compose: `docker compose up -d`
------------------------------------------
