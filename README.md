# Async Tasks Service

Backend system for processing long-running tasks asynchronously using queues.

## Tech Stack

| Technology | Role |
|---|---|
| PHP 8.2 | Language |
| Laravel 12 | Framework |
| MySQL | Primary database |
| Redis | Queue driver |
| PHPUnit | Testing |

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
```

Configure `.env` with your MySQL and Redis connection details before running migrations.

## Running the App

Starts the dev server, queue worker, logs, and Vite in one command:

```bash
composer dev
```

## Running Tests

```bash
composer test
```

## Authorization

**Sanctum SPA** — cookie-based session authentication for SPA frontends.

- Uses CSRF cookie + session cookies (not bearer tokens)
- Endpoints:
  - `POST /api/auth/register`
  - `POST /api/auth/login`
  - `POST /api/auth/logout`
- Protected routes use `auth:sanctum` middleware
- Requires `SANCTUM_STATEFUL_DOMAINS` and `SESSION_DOMAIN` set in `.env`
