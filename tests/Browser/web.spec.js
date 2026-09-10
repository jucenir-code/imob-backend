import { test, expect } from "@playwright/test";

async function login(page, email = "agent@cci.test") {
    await page.goto("/login");
    await page.getByLabel("E-mail", { exact: true }).fill(email);
    await page.getByLabel("Senha", { exact: true }).fill("browser-test-123");
    await page.getByRole("button", { name: "Entrar na minha conta" }).click();
    await expect(page).toHaveURL(/\/app\/imoveis$/);
    await expect(page.locator(".property-card").first()).toBeVisible();
}
async function noOverflow(page) {
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
}

test("native login, responsive navigation, property filters and logout", async ({
    page,
}) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await login(page);
    for (const width of [1440, 1024, 768, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        await noOverflow(page);
    }
    await page.setViewportSize({ width: 1440, height: 960 });
    await page.screenshot({
        path: "test-results/cci-desktop.png",
        fullPage: true,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({
        path: "test-results/cci-mobile.png",
        fullPage: true,
    });
    await page.getByLabel("Bairro", { exact: true }).fill("Vila Mariana");
    await page.getByRole("button", { name: "Buscar imóveis" }).click();
    await expect(page.locator(".property-card")).toHaveCount(1);
    await page.locator(".property-card").click();
    await expect(
        page.getByRole("heading", { name: "Casa com jardim na Vila Mariana" }),
    ).toBeVisible();
    await noOverflow(page);
    await page.getByRole("button", { name: "Sair da conta" }).last().click();
    await expect(page).toHaveURL(/\/login$/);
    expect(
        (
            await page.request.get("/web/properties", {
                headers: { Accept: "application/json" },
            })
        ).status(),
    ).toBe(401);
    expect(errors).toEqual([]);
});

test("create property and start a negotiation with chat", async ({ page }) => {
    await login(page);
    await page.getByRole("link", { name: "Cadastrar imóvel" }).click();
    // Installed iPhone PWAs reserve extra space for the home indicator.
    await page.setViewportSize({ width: 390, height: 844 });
    await page.evaluate(() => document.documentElement.style.setProperty('--mobile-safe-bottom', '34px'));
    const saveButton = page.getByRole("button", { name: "Salvar imóvel", exact: true });
    const assertFormActionsVisible = async () => {
        const menu = await page.getByRole("navigation", { name: "Menu do celular" }).boundingBox();
        for (const action of [saveButton, page.getByRole("link", { name: "Cancelar", exact: true })]) {
            await expect(action).toBeInViewport({ ratio: 1 });
            const bounds = await action.boundingBox();
            expect(bounds.y + bounds.height).toBeLessThanOrEqual(menu.y);
            await action.click({ trial: true });
        }
    };
    for (const size of [{ width: 320, height: 568 }, { width: 390, height: 844 }]) {
        await page.setViewportSize(size);
        await assertFormActionsVisible();
    }
    await page.screenshot({ path: "test-results/cci-property-form-pwa.png" });
    await page
        .getByLabel("Título do anúncio")
        .fill("Imóvel publicado pela web");
    await page
        .getByLabel("Descrição", { exact: true })
        .fill("Imóvel de teste cadastrado com sessão Laravel.");
    await page.getByLabel("Bairro", { exact: true }).fill("Centro");
    await page.getByLabel("Cidade", { exact: true }).fill("São Paulo");
    await page.getByLabel("Preço (R$)").fill("550000");
    await assertFormActionsVisible();
    const saved = page.waitForResponse(response => response.url().endsWith('/web/properties') && response.request().method() === 'POST');
    await page.getByRole("button", { name: "Salvar imóvel" }).click();
    expect((await saved).status()).toBe(201);
    await expect(
        page.getByRole("heading", { name: "Imóvel publicado pela web" }),
    ).toBeVisible();
    await page.evaluate(() => document.documentElement.style.removeProperty('--mobile-safe-bottom'));
    await page.goto("/app/imoveis/1");
    await page.getByRole("button", { name: "Iniciar negociação" }).click();
    await expect(page).toHaveURL(/\/app\/negociacoes\/\d+$/);
    await page
        .getByLabel("Mensagem", { exact: true })
        .fill("Olá, tenho interesse neste imóvel.");
    await page.getByRole("button", { name: "Enviar →", exact: true }).click();
    await expect(
        page
            .locator(".message")
            .filter({ hasText: "Olá, tenho interesse neste imóvel." }),
    ).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator('input[type="file"]').setInputFiles({
        name: "conversa.png",
        mimeType: "image/png",
        buffer: await page.screenshot(),
    });
    const uploaded = page.waitForResponse(response =>
        response.url().includes('/messages') && response.request().method() === 'POST');
    await page.getByRole("button", { name: "Enviar →", exact: true }).click();
    expect((await uploaded).status()).toBe(201);
    await page.reload();
    await expect(page.locator(".message img")).toBeVisible();
    for (const viewport of [
        { width: 390, height: 844 },
        { width: 320, height: 568 },
        { width: 390, height: 500 },
    ]) {
        await page.setViewportSize(viewport);
        await page.evaluate(() => window.scrollTo(0, 0));
        const input = page.getByLabel("Mensagem", { exact: true });
        await expect(input).toBeInViewport({ ratio: 1 });
        const composer = await page.locator(".composer").boundingBox();
        await expect(page.getByRole("navigation", { name: "Menu do celular" })).toBeHidden();
        expect(composer.y + composer.height).toBeLessThanOrEqual(viewport.height + 1);
        expect(composer.height).toBeLessThan(90);
        const history = await page.locator(".messages").boundingBox();
        expect(history.y + history.height).toBeLessThanOrEqual(composer.y + 1);
        await expect(page.getByRole("link", { name: "Voltar às negociações" })).toBeInViewport({ ratio: 1 });
        await noOverflow(page);
    }
    // Simulate the visual viewport shrinking and panning when a mobile keyboard opens.
    await page.getByLabel("Mensagem", { exact: true }).focus();
    await page.evaluate(() => {
        Object.defineProperty(window.visualViewport, 'height', { configurable: true, value: 300 });
        Object.defineProperty(window.visualViewport, 'offsetTop', { configurable: true, value: 80 });
        window.visualViewport.dispatchEvent(new Event('resize'));
    });
    await expect.poll(async () => {
        const bounds = await page.locator('.conversation').boundingBox();
        return Math.round(bounds.y + bounds.height);
    }).toBe(380);
    await expect(page.getByLabel("Mensagem", { exact: true })).toBeInViewport({ ratio: 1 });
    await page.evaluate(() => {
        delete window.visualViewport.height;
        delete window.visualViewport.offsetTop;
        window.visualViewport.dispatchEvent(new Event('resize'));
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect.poll(async () => Math.round((await page.locator(".conversation").boundingBox()).height)).toBe(844);
    await page.screenshot({ path: "test-results/cci-chat-mobile.png" });
    await page.getByLabel("Mensagem", { exact: true }).fill("Mensagem enviada pelo celular.");
    await page.getByRole("button", { name: "Enviar →", exact: true }).click();
    await expect(page.locator(".message").filter({ hasText: "Mensagem enviada pelo celular." })).toBeInViewport();
    for (let index = 0; index < 5; index++) {
        await page.getByLabel("Mensagem", { exact: true }).fill(`Detalhes do imóvel ${index}. ` + "Podemos combinar uma visita para conhecer os ambientes. ".repeat(4));
        await page.getByRole("button", { name: "Enviar →", exact: true }).click();
        await expect(page.getByLabel("Mensagem", { exact: true })).toHaveValue("");
        await expect(page.getByRole("button", { name: "Enviar →", exact: true })).toBeVisible();
    }
    const history = page.locator(".messages");
    await expect.poll(() => history.evaluate(el => el.scrollHeight - el.scrollTop - el.clientHeight)).toBeLessThan(5);
    await history.evaluate(el => { el.scrollTop = 0; });
    await expect(page.getByRole("button", { name: "Ir para últimas mensagens" })).toBeVisible();
    // A polling response must preserve the position while reading older messages.
    await page.waitForResponse(response => response.url().includes('/messages') && response.request().method() === 'GET');
    await expect.poll(() => history.evaluate(el => el.scrollTop)).toBeLessThan(5);
    await page.getByRole("button", { name: "Ir para últimas mensagens" }).click();
    await expect.poll(() => history.evaluate(el => el.scrollHeight - el.scrollTop - el.clientHeight)).toBeLessThan(5);
    await page.getByRole("button", { name: "Dados da negociação", exact: true }).click();
    await expect(page.getByRole("dialog")).toBeVisible();
    await page.getByLabel("Observações").fill("Visita combinada pelo chat.");
    const updated = page.waitForResponse(response => response.request().method() === 'PATCH');
    await page.getByRole("button", { name: "Salvar alterações" }).click();
    expect((await updated).status()).toBe(200);
    await page.getByRole("button", { name: "Fechar detalhes" }).click();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.setViewportSize({ width: 1440, height: 960 });
    await page.screenshot({ path: "test-results/cci-chat-desktop.png" });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole("link", { name: "Voltar às negociações" }).click();
    await expect(page.getByRole("navigation", { name: "Menu do celular" })).toBeVisible();
});

test("administrator invitations", async ({ page }) => {
    await login(page, "owner@cci.test");
    await page.goto("/app/gerenciar");
    await page.getByRole("button", { name: "Gerar convite" }).click();
    await expect(page.getByLabel("Link do convite")).toHaveValue(
        /register\?invite_token=/,
    );
    await page.setViewportSize({ width: 320, height: 750 });
    await noOverflow(page);
});

test("PWA manifest and offline fallback do not cache session pages", async ({
    page,
    context,
}) => {
    await login(page);
    const manifest = await (
        await page.request.get("/manifest.webmanifest")
    ).json();
    expect(manifest.display).toBe("standalone");
    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.waitForFunction(() => navigator.serviceWorker.controller);
    await context.setOffline(true);
    await page.goto("/app/imoveis");
    await expect(
        page.getByRole("heading", { name: "Você está sem conexão" }),
    ).toBeVisible();
    await context.setOffline(false);
    await page.getByRole("link", { name: "Tentar novamente" }).click();
    await expect(page.locator(".property-card").first()).toBeVisible();
    const cached = await page.evaluate(async () =>
        (
            await Promise.all(
                (await caches.keys()).map(async (key) =>
                    (await (await caches.open(key)).keys()).map(
                        (request) => new URL(request.url).pathname,
                    ),
                ),
            )
        ).flat(),
    );
    expect(
        cached.some(
            (path) =>
                path.startsWith("/web/") ||
                path.startsWith("/app/") ||
                path === "/login",
        ),
    ).toBe(false);
});

test('notification opt-in, denial, disable and logout cleanup', async ({ page, browserName }) => {
    test.skip(browserName === 'webkit', 'The automated WebKit runtime does not expose the Push API; install guidance is tested separately.');
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'standalone', { get: () => true });
        let permission = sessionStorage.getItem('push-test-permission') || 'default';
        Object.defineProperty(Notification, 'permission', { get: () => permission });
        Notification.requestPermission = async () => {
            permission = window.__permissionResult || 'granted';
            sessionStorage.setItem('push-test-permission', permission);
            return permission;
        };
        const restore = () => {
            const stored = JSON.parse(sessionStorage.getItem('push-test-sub') || 'null');
            return stored && {
                endpoint: stored.endpoint,
                options: {},
                toJSON: () => stored,
                unsubscribe: async () => { sessionStorage.removeItem('push-test-sub'); return true; },
            };
        };
        PushManager.prototype.getSubscription = async () => restore();
        PushManager.prototype.subscribe = async (options) => {
            const key = btoa(String.fromCharCode(...options.applicationServerKey)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
            sessionStorage.setItem('push-test-sub', JSON.stringify({ endpoint: 'https://fcm.googleapis.com/fcm/send/browser-push-test', keys: { p256dh: key, auth: 'AAAAAAAAAAAAAAAAAAAAAA' } }));
            return restore();
        };
    });
    await login(page);
    await page.getByRole('link', { name: 'Notificações', exact: true }).click();
    await page.evaluate(() => { window.__permissionResult = 'denied'; });
    await page.getByRole('button', { name: 'Ativar notificações', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('Permita as notificações');
    await page.evaluate(() => { window.__permissionResult = 'granted'; });
    const subscribed = page.waitForResponse(response => response.url().endsWith('/push/subscriptions') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Ativar notificações', exact: true }).click();
    expect((await subscribed).status()).toBe(200);
    await expect(page.getByRole('status')).toContainText('Notificações ativadas');
    const disabled = page.waitForResponse(response => response.url().endsWith('/push/subscriptions') && response.request().method() === 'DELETE');
    await page.getByRole('button', { name: 'Desativar notificações', exact: true }).click();
    expect((await disabled).status()).toBe(204);
    await expect(page.getByRole('status')).toContainText('Notificações desativadas');
    await page.getByRole('button', { name: 'Ativar notificações', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Notificações ativadas');
    const deleted = page.waitForResponse(response => response.url().endsWith('/push/subscriptions') && response.request().method() === 'DELETE');
    await page.setViewportSize({ width: 390, height: 844 });
    await noOverflow(page);
    await page.screenshot({ path: 'test-results/cci-notifications-mobile.png' });
    await page.getByRole('button', { name: 'Sair da conta' }).last().click();
    expect((await deleted).status()).toBe(204);
    await expect(page).toHaveURL(/\/login$/);
    expect(await page.evaluate(() => localStorage.getItem('cci-push-user'))).toBeNull();
});


test('iPhone notification setup explains Home Screen installation', async ({ page }) => {
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'userAgent', { get: () => 'iPhone' });
        Object.defineProperty(navigator, 'standalone', { get: () => false });
    });
    await login(page);
    await page.goto('/app/notificacoes');
    await expect(page.getByRole('status')).toContainText('Adicionar à Tela de Início');
    await expect(page.getByRole('button', { name: 'Ativar notificações', exact: true })).toHaveCount(0);
    await page.setViewportSize({ width: 390, height: 844 });
    await noOverflow(page);
});
