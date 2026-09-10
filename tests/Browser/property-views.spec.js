import { test, expect } from "@playwright/test";

// Keep the large-count response fixture interceptable in Chromium and WebKit.
// Service worker behavior has its own tests.
test.use({ serviceWorkers: "block" });

test("property views persist without counting refreshes and fit responsive cards", async ({ page }) => {
    await page.goto("/login");
    await page.getByLabel("E-mail", { exact: true }).fill("agent@cci.test");
    await page.getByLabel("Senha", { exact: true }).fill("browser-test-123");
    await page.getByRole("button", { name: "Entrar na minha conta" }).click();
    const card = page.locator('.property-card[href="/app/imoveis/3"]');
    await expect(card.getByRole("img", { name: "0 visualizações", exact: true })).toBeVisible();
    const recorded = page.waitForResponse(response => response.url().endsWith("/properties/3/views"));
    await card.click();
    expect((await recorded).status()).toBe(200);
    await expect(page.getByRole("img", { name: "1 visualização", exact: true })).toBeVisible();
    const repeated = page.waitForResponse(response => response.url().endsWith("/properties/3/views"));
    await page.reload();
    expect((await repeated).status()).toBe(200);
    await expect(page.getByRole("img", { name: "1 visualização", exact: true })).toBeVisible();
    await page.getByRole("link", { name: "Voltar aos imóveis" }).click();
    await expect(card.getByRole("img", { name: "1 visualização", exact: true })).toBeVisible();
    for (const width of [1440, 1024, 768, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        const location = await card.locator('.location').boundingBox();
        const views = await card.locator('.property-views').boundingBox();
        expect(location.x + location.width).toBeLessThanOrEqual(views.x);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await card.screenshot({ path: "test-results/cci-property-views-card.png" });
    await page.screenshot({ path: "test-results/cci-property-views-mobile.png", fullPage: true });
    // Large totals and long locations must also fit on a narrow phone.
    await page.route("**/web/properties?*", async route => {
        const response = await route.fetch();
        const json = await response.json();
        json.data.forEach(property => {
            property.views_count = 1234567;
            property.neighborhood = "Bairro com uma localização bastante extensa";
        });
        await route.fulfill({ response, json });
    });
    await page.reload();
    await expect(card.getByRole("img", { name: "1.234.567 visualizações", exact: true })).toBeVisible();
    await page.setViewportSize({ width: 320, height: 568 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});
