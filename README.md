# Circles Imobiliaria API

Laravel 10 API responsável pelo domínio imobiliário com grupos privados, deals e notificações push. Esta pasta segue o planejamento descrito em `../docs`.

## Requisitos
- PHP 8.2+
- Composer (`php bin/composer`)
- MySQL 8
- Redis (cache/fila) opcional

## Configuração
1. `cp .env.example .env`
2. Ajuste credenciais de banco, storage S3/MinIO e CORS.
3. `php artisan key:generate`
4. Configure permissões de storage (`php artisan storage:link` para ambiente local).

## Scripts Úteis
- `php artisan serve` — sobe a API em `APP_URL`.
- `php artisan migrate` — aplica migrations.
- `php artisan test` — roda suíte de testes (Pest/PHPUnit).

## Estrutura Inicial
- `app/Http/Controllers/API/V1` — controllers versionados (ex.: `HealthController`).
- `routes/api.php` — rotas REST prefixadas por `/api/v1`.
- `config/cors.php` — configuração alinhada com mobile (Expo) e web.

## Próximos Passos
- Implementar autenticação via Sanctum (login/logout/tokens).
- Criar migrations e models conforme `docs/data-model.md`.
- Escrever testes de contrato para filtros de imóveis e fluxo de deals.

## Versão web Vue/PWA

A versão web usa as rotas, sessões e regras do Laravel existente. Veja [como executar, funcionalidades e validação](docs/web.md).
