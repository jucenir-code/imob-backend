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
    await page
        .getByLabel("Título do anúncio")
        .fill("Imóvel publicado pela web");
    await page
        .getByLabel("Descrição", { exact: true })
        .fill("Imóvel de teste cadastrado com sessão Laravel.");
    await page.getByLabel("Bairro", { exact: true }).fill("Centro");
    await page.getByLabel("Cidade", { exact: true }).fill("São Paulo");
    await page.getByLabel("Preço (R$)").fill("550000");
    const saved = page.waitForResponse(response => response.url().endsWith('/web/properties') && response.request().method() === 'POST');
    await page.getByRole("button", { name: "Salvar imóvel" }).click();
    expect((await saved).status()).toBe(201);
    await expect(
        page.getByRole("heading", { name: "Imóvel publicado pela web" }),
    ).toBeVisible();
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
    await page.setViewportSize({ width: 320, height: 750 });
    await noOverflow(page);
});

test("group management and administrator invitations", async ({ page }) => {
    await login(page, "owner@cci.test");
    await page.goto("/app/grupos");
    await page.getByLabel("Nome do novo grupo").fill("Grupo criado na web");
    await page
        .getByRole("button", { name: "Criar grupo", exact: true })
        .click();
    await page
        .getByRole("link")
        .filter({ hasText: "Grupo criado na web" })
        .click();
    await page.getByLabel("E-mail do corretor").fill("agent@cci.test");
    await page.getByRole("button", { name: "Adicionar membro" }).click();
    await expect(
        page.locator(".member-row").filter({ hasText: "Rafael Santos" }),
    ).toBeVisible();
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
