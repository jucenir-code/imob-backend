import { test, expect } from "@playwright/test";

async function viewport(page, height, offsetTop, event = "resize") {
    await page.evaluate(({ height, offsetTop, event }) => {
        Object.defineProperty(window.visualViewport, "height", { configurable: true, value: height });
        Object.defineProperty(window.visualViewport, "offsetTop", { configurable: true, value: offsetTop });
        window.visualViewport.dispatchEvent(new Event(event));
    }, { height, offsetTop, event });
}

async function fullConversation(page, height) {
    await expect.poll(async () => Math.round((await page.locator(".conversation").boundingBox()).y)).toBe(0);
    await expect.poll(async () => Math.round((await page.locator(".conversation").boundingBox()).height)).toBe(height);
    await expect(page.getByLabel("Mensagem", { exact: true })).toBeInViewport({ ratio: 1 });
    await expect(page.getByRole("link", { name: "Voltar às negociações" })).toBeInViewport({ ratio: 1 });
}

test("home screen chat restores its viewport after keyboard dismissal and resume", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    // Playwright cannot install an iOS PWA; simulate its flag and stale viewport metrics.
    await page.addInitScript(() => Object.defineProperty(navigator, "standalone", { configurable: true, value: true }));
    await page.goto("/login");
    await page.getByLabel("E-mail", { exact: true }).fill("agent@cci.test");
    await page.getByLabel("Senha", { exact: true }).fill("browser-test-123");
    await page.getByRole("button", { name: "Entrar na minha conta" }).click();
    await expect(page).toHaveURL(/\/app\/imoveis$/);
    await page.goto("/app/imoveis/1");
    await page.getByRole("button", { name: "Iniciar negociação" }).click();
    await expect(page.getByLabel("Mensagem", { exact: true })).toBeVisible();
    await fullConversation(page, 844);

    // A resumed app can retain the keyboard's old offset and height with no focused input.
    await viewport(page, 300, 380);
    await page.evaluate(() => window.dispatchEvent(new Event("pageshow")));
    await fullConversation(page, 844);

    const input = page.getByLabel("Mensagem", { exact: true });
    await input.focus();
    await input.fill("Mensagem após abrir pelo atalho.");
    await viewport(page, 300, 80);
    await expect.poll(async () => {
        const rect = await page.locator(".conversation").boundingBox();
        return Math.round(rect.y + rect.height);
    }).toBe(380);
    await expect(input).toBeInViewport({ ratio: 1 });

    // Safari may restore the height before clearing offsetTop, even while focus remains.
    await viewport(page, 844, 380);
    await fullConversation(page, 844);

    await viewport(page, 300, 80);
    await input.blur();
    await fullConversation(page, 844);
    await expect(input).toHaveValue("Mensagem após abrir pelo atalho.");

    // Do not let an out-of-range offset place the composer beyond the layout viewport.
    await input.focus();
    await viewport(page, 300, 900);
    await expect.poll(async () => {
        const rect = await page.locator(".conversation").boundingBox();
        return Math.round(rect.y + rect.height);
    }).toBeLessThanOrEqual(844);
    await input.blur();
    await fullConversation(page, 844);

    // Returning from another app dismisses a stale keyboard without losing the draft.
    await input.focus();
    await viewport(page, 300, 380);
    await page.evaluate(() => document.dispatchEvent(new Event("visibilitychange")));
    await fullConversation(page, 844);
    await expect(input).toHaveValue("Mensagem após abrir pelo atalho.");

    await page.getByRole("button", { name: "Enviar →", exact: true }).click();
    await expect(page.locator(".message").filter({ hasText: "Mensagem após abrir pelo atalho." })).toBeVisible();
    await page.screenshot({ path: "test-results/cci-chat-home-screen.png" });
    for (const size of [{ width: 320, height: 568 }, { width: 667, height: 375 }]) {
        await page.setViewportSize(size);
        await fullConversation(page, size.height);
    }
    await page.getByRole("link", { name: "Voltar às negociações" }).click();
    await expect(page.getByRole("navigation", { name: "Menu do celular" })).toBeVisible();
    expect(await page.evaluate(() => getComputedStyle(document.body).position)).not.toBe("fixed");
});
