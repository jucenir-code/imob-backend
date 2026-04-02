# Deploy Docker Production

## 1. Preparar ambiente

Crie o arquivo `.env` de produção com pelo menos:

```env
APP_NAME="Circles Imobiliaria"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.seudominio.com
API_VERSION=v1

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=imobiliaria
DB_USERNAME=imobiliaria
DB_PASSWORD=senha-forte
DB_ROOT_PASSWORD=senha-root-forte

CACHE_DRIVER=file
QUEUE_CONNECTION=database
SESSION_DRIVER=file
FILESYSTEM_DISK=public

LOG_CHANNEL=stack
LOG_LEVEL=warning
```

Defina também suas variáveis reais de mail, AWS, push, CORS e Sanctum.

## 2. Subir containers

```bash
cd backend
docker compose -f docker-compose.prod.yml up -d --build
```

## 3. Acessar

API:

```text
http://SEU_SERVIDOR:8000
```

## 4. Comandos úteis

Logs:

```bash
docker compose -f docker-compose.prod.yml logs -f
```

Rebuild:

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Executar comando artisan:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan about
```

## 5. Produção real

Para produção pública, o ideal é colocar um proxy reverso com HTTPS na frente, por exemplo:

- Nginx no host
- Traefik
- Caddy

Também é recomendável mover o MySQL para um serviço gerenciado ou volume dedicado com backup.
