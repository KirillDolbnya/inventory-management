# Inventory Management

Backend системы учёта спортивного инвентаря для соревнований и других мероприятий. Позволяет управлять складами, стеллажами и ячейками, размещать и перемещать инвентарь, а также бронировать его количество на время событий.

Для складов поддерживаются адреса и координаты через DaData, сохранение 3D-модели и схемы размещения стеллажей. Frontend разрабатывается отдельно и работает с приложением через REST API.

## Стек и технологии

- **PHP 8.5, Laravel 13** — приложение и API.
- **MySQL 8.0, Eloquent ORM** — хранение данных.
- **Laravel Sanctum** — авторизация через сессии и cookie.
- **Docker Compose, PHP-FPM, Caddy** — локальное окружение.
- **DaData** — подсказки адресов и получение координат по ФИАС ID.
- **Scramble / OpenAPI** — интерактивная документация API.
- **Pest, Larastan / PHPStan, Laravel Pint** — тесты, статический анализ и форматирование.

## Локальный запуск

Нужен запущенный Docker с Docker Compose. PHP и Composer установлены в контейнере `app`. Все команды ниже выполняются из корня репозитория.

### 1. Настроить окружение

```bash
cp .env.example .env
```

В `.env` замени соответствующие значения и добавь недостающие параметры:

```dotenv
APP_NAME="Inventory Management"
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=inventory_management
DB_USERNAME=inventory
DB_PASSWORD=inventory
DB_ROOT_PASSWORD=root

FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8080
SESSION_DOMAIN=null

DADATA_API_KEY=
```

Для подсказок адресов и создания или изменения адреса склада заполни `DADATA_API_KEY` своим API-ключом DaData. Остальное приложение можно запустить без ключа.

### 2. Запустить контейнеры и подготовить приложение

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan storage:link
```

Команды собирают и запускают окружение, устанавливают зависимости, создают ключ приложения, применяют миграции и открывают доступ к сохранённым файлам 3D-моделей через `/storage`.

### 3. Создать пользователя

```bash
docker compose exec app php artisan create:user
```

Команда запросит имя, email и пароль. Публичной регистрации нет — для входа используется созданный пользователь.

## API и авторизация

- Базовый адрес API — `http://localhost:8080/api`.
- [Интерактивная документация API](http://localhost:8080/docs/api).

Для запросов из документации сначала выполни `GET /sanctum/csrf-cookie`, затем `POST /api/auth/sign-in` с данными созданного пользователя. После входа защищённые методы работают с текущей сессией.

При подключении отдельного frontend укажи его адрес в `FRONTEND_URL`, а хост с портом — в `SANCTUM_STATEFUL_DOMAINS`. HTTP-клиент должен отправлять cookie и CSRF-заголовок `X-XSRF-TOKEN` со значением из cookie `XSRF-TOKEN` после URL-декодирования.

## Основные команды

| Команда | Что делает |
| --- | --- |
| `docker compose up -d` | Запускает контейнеры в фоне. |
| `docker compose up -d --build` | Пересобирает образ приложения и запускает окружение. |
| `docker compose down` | Останавливает и удаляет контейнеры и сеть, сохраняя volume с данными MySQL. |
| `docker compose logs -f app caddy db` | Показывает логи приложения, веб-сервера и базы данных. |
| `docker compose exec app sh` | Открывает shell внутри контейнера приложения. |
| `docker compose exec app php artisan migrate` | Применяет новые миграции. |
| `docker compose exec app php artisan optimize:clear` | Очищает кеши приложения, конфигурации, маршрутов и шаблонов. |
| `docker compose exec app composer test` | Очищает кеш конфигурации и запускает тесты Pest. |
| `docker compose exec app composer lint` | Проверяет форматирование без изменения файлов. |
| `docker compose exec app composer lint:fix` | Исправляет форматирование через Pint. |
| `docker compose exec app composer analyse` | Запускает статический анализ Larastan / PHPStan. |
| `docker compose exec app composer quality` | Последовательно запускает проверку форматирования, статический анализ и тесты. |

Тесты настроены на отдельную SQLite-базу в памяти через `phpunit.xml`.

Для запуска одного файла тестов:

```bash
docker compose exec app php artisan test tests/Feature/WarehouseTest.php
```
