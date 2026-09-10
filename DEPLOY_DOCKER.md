# Deploy Docker Hub / Portainer

A imagem `jcinformatica/circles-backend` contém Laravel, a web Vue/PWA compilada, PHP-FPM, Nginx, worker e scheduler. A porta HTTP do container é `80`. O build usa Node 22 em uma etapa separada; Node e node_modules não entram na imagem final.

## Build e publicação

Execute na raiz deste repositório (a pasta `backend/` no workspace):

```sh
docker login
docker buildx build --platform linux/amd64 \
  -t jcinformatica/circles-backend:latest \
  -t jcinformatica/circles-backend:VERSAO \
  --push .
```

Use o hash do commit em `VERSAO`, para permitir rollback. A imagem de produção atual usa Linux AMD64. O Dockerfile também aceita build para ARM64 quando necessário.

Se já houver um build configurado no Docker Hub, a origem é `jucenir-code/imob-backend`, branch `master`, contexto `/` e Dockerfile na raiz. Um push no GitHub só dispara o build se essa integração estiver configurada.

## Configuração da stack

Use `portainer-stack.yml` no Portainer ou `docker-compose.prod.yml` com Docker Compose. Defina as variáveis da stack:

- `PROD_APP_KEY`: **mantenha a chave da instalação existente**.
- `PROD_APP_URL`: domínio público completo, com HTTPS.
- `PROD_DB_PASSWORD`: senha atual do usuário do MySQL.
- `PROD_DB_ROOT_PASSWORD`: senha atual do root do MySQL.
- `BACKEND_IMAGE`: `jcinformatica/circles-backend:latest` ou uma tag de versão.
- `PROD_SESSION_SECURE_COOKIE`: `true` em produção com HTTPS.

Preserve os nomes da stack e dos volumes `db_data` e `backend_storage` ao atualizar. Mudar variáveis de senha não altera as contas de um volume MySQL que já existe.

Somente para uma instalação nova, gere a chave uma única vez:

```sh
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

A migration incluída cria a tabela `jobs` quando ela ainda não existe. O intervalo de recuperação da fila é 180 segundos, maior que o timeout de 120 segundos do worker; a tabela é preservada no rollback para manter tarefas pendentes.

O container recebe configuração pelas variáveis de ambiente; não precisa de um `.env` dentro da imagem. Na inicialização ele prepara permissões, aguarda o banco, executa migrations e cria os caches Laravel. Para executar migrations separadamente, use `RUN_MIGRATIONS=false`.

## Atualizar produção

No Portainer, atualize a stack com a opção de baixar novamente a imagem. Com Compose:

```sh
docker compose -f docker-compose.prod.yml pull app
docker compose -f docker-compose.prod.yml up -d
```

Não é necessário executar `npm run build` no servidor. O Vue já está compilado dentro da imagem.

## Verificar

- `/login`: tela de entrada da web.
- `/app/imoveis`: redireciona ao login quando não há sessão.
- `/api/v1/health`: saúde da API do aplicativo.
- `/manifest.webmanifest` e `/sw.js`: arquivos do PWA.

O healthcheck da imagem verifica a resposta HTTP de `/login`. Para ver os processos e os logs:

```sh
docker compose -f docker-compose.prod.yml exec app supervisorctl -c /etc/supervisord.conf status
docker compose -f docker-compose.prod.yml logs --tail=100 app
```

O acesso público deve passar por um proxy com HTTPS. O Laravel reconhece os cabeçalhos encaminhados pelo proxy e gera URLs HTTPS em produção.

## Teste isolado da imagem

```sh
python3 docker/smoke-test.py jcinformatica/circles-backend:VERSAO
```

Esse teste cria containers temporários, usa um banco MySQL separado e remove seus próprios recursos ao terminar. Verifica HTTP, assets Vue, API, PWA, login, administração, os quatro processos e persistência da sessão após reiniciar o container.

## Notificações no celular

Esta versão inclui as migrations `web_push_keys` e `web_push_subscriptions`. O entrypoint as executa normalmente. Não precisa gerar nem copiar chaves manualmente: a identidade VAPID é criada na primeira visita autenticada a `/app/notificacoes` e fica criptografada no banco. Preserve o banco e a mesma `APP_KEY`, incluindo nos backups e nas réplicas.

- `APP_URL` deve ser a URL pública HTTPS; é usada também como contato VAPID.
- `WEB_PUSH_SUBJECT` é opcional (URL HTTPS ou `mailto:email@dominio`).
- `WEB_PUSH_ENABLED` é `true` por padrão; use `false` para interromper novas inscrições e envios.
- O worker precisa estar executando a fila `database`, com saída HTTPS liberada para os serviços de push dos navegadores.
- O acesso à tela de notificações não solicita permissão automaticamente: o usuário toca em **Ativar notificações**.

No iPhone, adicione à Tela de Início e abra pelo ícone (iOS 16.4+). Android permite em navegadores compatíveis. Para conferir a entrega, ative em duas contas de teste em dispositivos diferentes, envie uma mensagem entre elas e cadastre um imóvel ativo; confira o aviso e a abertura da conversa/imóvel. Avisos de imóveis são enviados aos usuários ativos aprovados e administradores, excluindo quem cadastrou.

Os testes automatizados simulam o serviço de push; o smoke test verifica geração, persistência das chaves e inscrição no container, sem enviar avisos para pessoas reais.
