# Versão web CCI

A web é servida pelo **mesmo Laravel em `backend/`**. Não há outro backend, banco de dados ou servidor Vue em produção.

- Blade entrega o documento em `resources/views/app.blade.php`.
- Vue 3 e Vue Router implementam as telas em `resources/js/`.
- `routes/browser.php`, carregado no grupo `web`, reutiliza os controllers, requests, resources e policies da API existente.
- O navegador usa sessão Laravel e CSRF. Não armazena tokens em localStorage; a autenticação Sanctum por token do aplicativo continua disponível.
- O Dockerfile existente já executa `npm ci` e `npm run build`.

## Executar

Dentro de `backend/`, usando Node 20.19+ (ou 22.12+), com o `.env` e banco já configurados:

```sh
npm ci
npm run build
php artisan serve
```

Abra `http://127.0.0.1:8000`. O acesso inicial é redirecionado para `/app/imoveis`, com login em `/login` quando necessário. Use a mesma conta do aplicativo.

Para desenvolvimento com atualização automática, execute `npm run dev` em outro terminal junto ao servidor Laravel. O service worker só é registrado pelo build de produção.

## Funcionalidades

- Login, logout e cadastro pelo convite existente (`/register`).
- Listagem e filtros de imóveis; detalhes, galeria, criação, edição e exclusão conforme as permissões atuais.
- Início e aceite de negociações, alteração de situação, cliente, comissão, divisão da comissão e observações.
- Chat com cabeçalho e barra de envio compactos, histórico com rolagem independente, envio de texto e imagens. Áudios existentes continuam disponíveis para reprodução. No celular, ocupa a tela e acompanha a área visível ao abrir o teclado. Os dados da negociação ficam no botão de informações. Atualização a cada 15 segundos enquanto a página está visível, preservando a leitura de mensagens antigas.
- Administração: aprovação de corretores, remoção de aprovação, exclusão de contas e geração de convites.
- Navegação lateral no desktop e inferior no celular, com layouts para tablet e telas estreitas.

As regras de domínio continuam nos controllers/policies existentes. A interface não amplia permissões. Os dados usados nas verificações do navegador são fictícios e ficam em SQLite temporário separado.

## PWA

`public/manifest.webmanifest` e `public/sw.js` permitem instalação e disponibilizam uma página informativa quando não há conexão. Apenas recursos estáticos públicos entram no cache; documentos com sessão/CSRF, respostas de domínio e anexos privados não são armazenados pelo service worker. Consultas e alterações exigem conexão; não há fila de gravações offline.

Em produção, use HTTPS, configure `APP_URL` com a URL pública e `SESSION_SECURE_COOKIE=true`. Use o mesmo domínio para páginas e requisições da web. A instalação depende do suporte do navegador; no iPhone, use Compartilhar → Adicionar à Tela de Início. Na atualização do service worker, a interface oferece um botão para recarregar a nova versão.

**Limitação de paridade:** as notificações push existentes usam Expo e continuam atendendo o aplicativo. Entrega em segundo plano no navegador ainda requer a integração de Web Push, assinaturas de navegador e configuração VAPID. Instalar o PWA não converte automaticamente o push Expo em push web.

## Verificações

```sh
php artisan test
npm run build
npx playwright install chromium
npm run test:web
npx playwright install webkit
PLAYWRIGHT_BROWSER=webkit npm run test:web
```

O Playwright inicia um Laravel isolado na porta 8765, cria dados fictícios no diretório temporário do sistema, em `cci-browser-*.sqlite` e verifica login real, filtros, publicação de imóvel, chat, administração, larguras de 320 a 1440 pixels e fallback offline. Não usa o banco configurado para o aplicativo. Capturas ficam em `backend/test-results/`.

Validação desta implementação: build de produção aprovado; 14 testes de autenticação/web aprovados e 4 cenários Playwright aprovados. A suíte completa também contém 10 falhas nos testes legados de imóveis, grupos e negociações: expectativas de acesso/status divergentes dos controllers atuais, associações duplicadas nas factories e payloads incompletos. As policies e os controllers de domínio não foram alterados para acomodar esses testes.

Referências de implementação: [Laravel + Vue/Vite](https://laravel.com/docs/10.x/vite#vue), [Vue](https://vuejs.org/guide/quick-start.html), [estratégias de cache de PWA](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Caching).

O ajuste do chat usa [VisualViewport](https://developer.mozilla.org/en-US/docs/Web/API/VisualViewport) para acompanhar teclado e barras do navegador. Os testes simulam a redução e o deslocamento da área visível; não substituem uma verificação em iPhone físico.

Validação do novo chat: os cenários de login, conversa e administração passaram no WebKit 26.6. O cenário de navegação offline retorna `WebKit encountered an internal error` ao navegar após `context.setOffline(true)`, mesmo com o service worker controlando a página; esse cenário não foi validado nesse navegador. O service worker não foi alterado nesta revisão.
