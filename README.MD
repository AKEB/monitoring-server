# Monitoring Server

![Logo](https://monitoring.akeb.ru/images/apple-icon-180x180.png)

Default User Email: `admin@admin.com` and Password `Admin@123`

## Issues

- [x] Создание/редактирование обработчиков
- [x] Список обработчиков
- [x] Создание/редактирование мониторов
- [x] Список мониторов
- [x] Права доступа к мониторам, наследуемые из папок
- [x] Отображение списка мониторов на главной странице
- [x] Фильтрация мониторов по названию
- [x] Отображение выбранного монитора на главной странице
- [x] Обновление списка мониторов в фоне
- [x] Обновление выбранного монитора в фоне
- [ ] Фильтрация мониторов по статусу
- [ ] Технические работы
- [ ] События в базе
- [ ] Отображение событий на главной странице
- [ ] Отображение событий в выбранном мониторе
- [ ] Нотификации о событиях
- [ ] Рассмотреть clickhouse в качестве хранилища данных
- [ ] Агрегировать данные в отдельную таблицу
- [ ] Удалять логи старше 30 дней.
- [ ] Проверить еще раз все страницы на мобильном устройстве
- [ ] Проверить еще раз светлую тему
- [ ] Развернуть боевой инстанс
- [ ] Новый тип монитора TCP
- [ ] Новый тип монитора node-exporter
- [ ] Новый тип монитора MySQL
- [ ] Новый тип монитора PostgreSQL
- [ ] Новый тип монитора Docker API

## Environments

| Environment                  |  Default             |   Type   | Description                   |
|------------------------------|:--------------------:|:--------:|-------------------------------|
| ---------------------------- | -------------------- | -------- | ----------------------------- |
| TZ                           | UTC                  | string   | Timezone                      |
| PASSWORD_SALT                |                      | string   | Password Salt                 |
| ---------------------------- | -------------------- | -------- | ----------------------------- |
| MYSQL_DEBUG                  | false                | boolean  | MySQL Debug logs              |
| MYSQL_HOST                   | localhost            | string   | MySQL Host                    |
| MYSQL_PORT                   | 3306                 | integer  | MySQL Port                    |
| MYSQL_USERNAME               | root                 | string   | MySQL User                    |
| MYSQL_PASSWORD               |                      | string   | MySQL Password                |
| MYSQL_DB_NAME                | monitoring           | string   | MySQL DB Name                 |
| MYSQL_DONT_USE_SLAVE         | true                 | boolean  | MySQL Dont Use Slave          |
| MYSQL_SLAVE_HOST             | MYSQL_HOST           | string   | MySQL Slave Host              |
| MYSQL_SLAVE_PORT             | MYSQL_PORT           | string   | MySQL Slave Port              |
| MYSQL_SLAVE_USERNAME         | MYSQL_USERNAME       | string   | MySQL Slave User              |
| MYSQL_SLAVE_PASSWORD         | MYSQL_PASSWORD       | string   | MySQL Slave Password          |
| MYSQL_SLAVE_DB_NAME          | MYSQL_DB_NAME        | string   | MySQL Slave DB Name           |
| ---------------------------- | -------------------- | -------- | ----------------------------- |
| APP_SIGNIN_ACTIVE            | true                 | boolean  | App Sign In Active            |
| APP_SIGNUP_ACTIVE            | true                 | boolean  | App Sign Up Active            |
| APP_DEBUG                    | false                | boolean  | App Debug                     |
| ---------------------------- | -------------------- | -------- | ----------------------------- |
| SMTP_HOST                    |                      | string   | SMTP Host                     |
| SMTP_PORT                    | 25                   | integer  | SMTP Port                     |
| SMTP_USERNAME                |                      | string   | SMTP User                     |
| SMTP_PASSWORD                |                      | string   | SMTP Password                 |
| SMTP_TLS                     | false                | boolean  | SMTP TLS                      |
| SMTP_SSL                     | false                | boolean  | SMTP SSL                      |
| ---------------------------- | -------------------- | -------- | ----------------------------- |
| OPENIDCONNECT_PROVIDER       |                      | string   | OpenID Connect Provider URL   |
| OPENIDCONNECT_CLIENT_ID      |                      | string   | OpenID Connect Client Id      |
| OPENIDCONNECT_CLIENT_SECRET  |                      | string   | OpenID Connect Client Secret  |
| OPENIDCONNECT_BUTTON         |                      | string   | OpenID Connect Button Title   |
| OPENIDCONNECT_SCOPE          | email profile openid | string   | OpenID Connect Scope          |
| OPENIDCONNECT_REGISTER       | true                 | boolean  | OpenID Connect Register Allow |
| ---------------------------- | -------------------- | -------- | ----------------------------- |
| OAUTH_CLIENT_ID              |                      | string   | OAuth Client Id               |
| OAUTH_CLIENT_SECRET          |                      | string   | OAuth Client Secret           |
| OAUTH_AUTHORIZATION_ENDPOINT |                      | string   | OAuth Authorization Endpoint  |
| OAUTH_TOKEN_ENDPOINT         |                      | string   | OAuth Token Endpoint          |
| OAUTH_USERINFO_ENDPOINT      |                      | string   | OAuth Userinfo Endpoint       |
| OAUTH_BUTTON                 |                      | string   | OAuth Button Title            |
| OAUTH_SCOPE                  | self_profile         | string   | OAuth Scope                   |
| OAUTH_REGISTER               | true                 | boolean  | OAuth Register Allow          |
| ---------------------------- | -------------------- | -------- | ----------------------------- |
| MEMCACHED_HOST               |                      | string   | Memcached Host                |
| MEMCACHED_PORT               | 11211                | integer  | Memcached Port                |
| ---------------------------- | -------------------- | -------- | ----------------------------- |
| TELEGRAM_BOT_TOKEN           |                      | string   | Telegram Bot Token            |

### .env file

```ini
NGINX_PORT=80

MYSQL_ROOT_PASSWORD=RootPassword

MYSQL_HOST=mysql
MYSQL_PASSWORD=${MYSQL_ROOT_PASSWORD}

PASSWORD_SALT=bHchLzC3B99Ss1213127fdw876EDghc2gkDdtg

# Memcached
MEMCACHED_HOST=memcached

```

## Docker compose

```yml
---
services:
  mysql:
    container_name: monitoring-mysql
    image: mysql:latest
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    volumes:
      - ./mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysql", "-h", "127.0.0.1", "-u", "root", "--password=${MYSQL_ROOT_PASSWORD}", "-e", "show databases;"]
      start_period: 10s
      interval: 5s
      timeout: 5s
      retries: 55
    
  memcached:
    container_name: "monitoring-memcached"
    image: memcached
    restart: always
    command:
      - --conn-limit=1024
      - --memory-limit=32
      - --threads=4
    
  server:
    container_name: "monitoring-server"
    image: "akeb/monitoring:latest"
    restart: always
    env_file:
      - ./.env
    volumes:
      - ./logs/php/:/var/log/php/:rw
      - ./logs/nginx/:/var/log/nginx/:rw
    ports:
      - "${NGINX_PORT:-80}:80"
    depends_on:
      mysql:
        condition: service_healthy
      memcached:
        condition: service_started

```
