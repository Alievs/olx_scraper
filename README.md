# OLX Price Tracker API

## Запуск проекту

1. Зібрати контейнери:

   ```bash
   docker compose build
   ```

2. Запустити контейнери:

   ```bash
   docker compose up -d
   ```

3. Виконати міграції:

   ```bash
   docker compose exec app php artisan migrate
   ```

4. Далі на вибір:

   - Скористатися `postman_collection.json` для роботи з endpoints.
   - Або в `api.php` поміняти `post /subscriptions` на `get` і працювати в браузерному рядку, наприклад:

     ```
     http://localhost:8000/api/subscriptions?url=
     ```

## Запуск тестів

```bash
php artisan test
```
