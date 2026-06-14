# OLX Price Tracker

Laravel 11 app that tracks OLX listing prices and notifies subscribers by email when price changes.

## Stack

| Layer | Choice |
|---|---|
| Framework | PHP 8.3, Laravel 11 |
| Database | PostgreSQL 16 |
| Queue / Cache | Redis |
| Scheduler | Laravel Scheduler |
| HTTP Scraping | Guzzle HTTP + Symfony DomCrawler |
| Email | Laravel Mail (SMTP) |
| Containerization | Docker + Docker Compose |

---

## Running the Project (Docker)

### Prerequisites

- Docker + Docker Compose installed
- Port `8000` free

### Step 1 — Build and start containers

```bash
docker compose up --build -d
```

Wait for `db` to become healthy (~15–20 sec):

```bash
docker compose ps
```

All services should be `Up`.

### Step 2 — Run migrations

```bash
docker compose exec app php artisan migrate --force
```

### Step 3 — Verify server responds

```bash
curl -s http://localhost:8000/api/subscriptions \
  -X POST -H "Content-Type: application/json" \
  -d '{}' | python3 -m json.tool
```

Expected: `422` with validation errors.

---

## Manual API Testing

### Subscribe to a listing

```bash
curl -s -X POST http://localhost:8000/api/subscriptions \
  -H "Content-Type: application/json" \
  -d '{"url":"https://www.olx.ua/d/uk/obyavlenie/XXXXX/","email":"test@example.com"}' \
  | python3 -m json.tool
```

Expected: `201` with `listing.title` and `listing.current_price`.

> Replace URL with a real OLX listing, otherwise scraper returns `422`.

### Duplicate subscription

Repeat same request → expected: `409`.

### Email verification

Mail driver is `log` — token appears in logs:

```bash
docker compose exec app tail -f storage/logs/laravel.log | grep verification
```

Copy token, then:

```bash
curl -s http://localhost:8000/api/subscriptions/verify/{TOKEN} | python3 -m json.tool
```

Expected: `200` with confirmation message.

### Expired / invalid token

```bash
curl -s http://localhost:8000/api/subscriptions/verify/fakeinvalidtoken
```

Expected: `410 Gone`.

### Validation errors

```bash
# Invalid domain (not olx.ua / olx.kz)
curl -s -X POST http://localhost:8000/api/subscriptions \
  -H "Content-Type: application/json" \
  -d '{"url":"https://rozetka.com.ua/item/1","email":"test@example.com"}' \
  | python3 -m json.tool
# Expected: 422

# Invalid email
curl -s -X POST http://localhost:8000/api/subscriptions \
  -H "Content-Type: application/json" \
  -d '{"url":"https://www.olx.ua/d/...","email":"notanemail"}' \
  | python3 -m json.tool
# Expected: 422
```

### Trigger scheduler manually

```bash
docker compose exec app php artisan schedule:run
```

### Stop all containers

```bash
docker compose down
```

---

## Running Tests

Tests require PostgreSQL at `127.0.0.1:5480` with database `olx_tracker_test`. Docker Compose already exposes PostgreSQL on port `5480`.

### Step 1 — Start PostgreSQL

```bash
docker compose up -d db
docker compose ps db   # wait until healthy
```

### Step 2 — Create test database

```bash
docker compose exec db psql -U postgres -c "CREATE DATABASE olx_tracker_test;"
```

### Step 3 — Run tests

```bash
# All tests
php artisan test

# By suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Single file
php artisan test tests/Unit/OlxScraperServiceTest.php
php artisan test tests/Feature/SubscriptionApiTest.php
php artisan test tests/Feature/EmailVerificationTest.php

# Verbose output
php artisan test --verbose
```

If `php` is not available locally, run inside the container:

```bash
docker compose exec app ./vendor/bin/phpunit
```

---

## Troubleshooting

| Problem | Solution |
|---|---|
| `could not connect to server` | `docker compose up -d db` and wait until healthy |
| `olx_tracker_test does not exist` | Run Step 2 above |
| `php` not found locally | Run tests via `docker compose exec app` |
| Unit scraper test fails | Test uses HTML fixture, not real OLX — verify fixture file exists |
