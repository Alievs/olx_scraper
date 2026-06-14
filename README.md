# OLX Price Tracker API

## Запуск проекту

1. Скопіювати env:

   ```bash
   cp .env.example .env
   ```

2. Згенерувати APP key:

   ```bash
   php artisan key:generate
   ```

3. Встановити залежності:

   ```bash
   composer install
   ```

4. Зібрати контейнери:

   ```bash
   docker compose build
   ```

5. Запустити контейнери:

   ```bash
   docker compose up -d
   ```

6. Виконати міграції:

   ```bash
   docker compose exec app php artisan migrate
   ```

7. Далі на вибір:

   - Скористатися `postman_collection.json` для роботи з endpoints.
   - Або в `api.php` поміняти `post /subscriptions` на `get` і працювати в браузерному рядку, наприклад:

     ```
     http://localhost:8000/api/subscriptions?url=
     ```

## Запуск тестів

```bash
php artisan test
```
