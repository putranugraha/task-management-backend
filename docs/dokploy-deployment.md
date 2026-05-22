# Dokploy Deployment

Deploy backend and frontend as separate Dokploy services.

## Backend Laravel

Use this repository with Compose path:

```text
./docker-compose.prod.yml
```

Point the Dokploy domain to service port `8000`.

Required production environment:

```env
APP_NAME=TaskManagement
APP_ENV=production
APP_KEY=base64:REPLACE_WITH_GENERATED_KEY
APP_DEBUG=false
APP_URL=https://api.example.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=backend-db
DB_PORT=5432
DB_DATABASE=task_management
DB_USERNAME=task_user
DB_PASSWORD=replace_with_strong_password

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

CORS_ALLOWED_ORIGINS=https://task.example.com
SANCTUM_STATEFUL_DOMAINS=task.example.com
SESSION_DOMAIN=null
```

Generate `APP_KEY` once and store it in Dokploy environment variables:

```bash
php artisan key:generate --show
```

After the first deploy, run migrations from the backend app container:

```bash
php artisan migrate --force
```

## Frontend Next.js

Use the frontend repository with Compose path:

```text
./docker-compose.prod.yml
```

Point the Dokploy domain to service port `3000`.

Required production environment:

```env
NEXT_PUBLIC_API_BASE_URL=https://api.example.com
NEXT_PUBLIC_API_URL=https://api.example.com
NEXT_PUBLIC_USE_SANCTUM=0
NEXT_PUBLIC_PROXY_API=0
NEXT_PUBLIC_USE_SERVER_AUTH=0
NEXT_PUBLIC_AUTO_LOGIN=false
```

These values are passed as Docker build args, so rebuild the frontend service after changing the API domain.
