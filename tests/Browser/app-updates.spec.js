import { test, expect } from "@playwright/test";

// Prove version detection works independently of the service worker's update event.
test.use({ serviceWorkers: "block" });

async function login(page) {
    await page.goto("/login");
    await page.getByLabel("E-mail", { exact: true }).fill("agent@cci.test");
    await page.getByLabel("Senha", { exact: true }).fill("browser-test-123");
    await page.getByRole("button", { name: "Entrar na minha conta" }).click();
    await expect(page).toHaveURL(/\/app\/imoveis$/);
    await expect(page.locator(".property-card").first()).toBeVisible();
}

test("home screen update reloads the whole app and keeps property view counters visible", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.addInitScript(() => Object.defineProperty(navigator, "standalone", { value: true }));
    const current = (await (await page.request.get("/app-version")).json()).version;
    let deployed = current;
    await page.route("**/app-version", route => route.fulfill({ json: { version: deployed } }));
    await page.route("**/app/imoveis", async route => {
        const response = await route.fetch();
        const html = (await response.text()).replace(`name="app-version" content="${current}"`, `name="app-version" content="${deployed}"`);
        await route.fulfill({ response, body: html });
    });
    await login(page);
    const notice = page.getByRole("status", { name: "Atualização do aplicativo" });
    await expect(notice).toBeHidden();
    const originalDocument = await page.locator('meta[name="app-version"]').getAttribute("content");
    deployed = "b".repeat(64);
    await page.evaluate(() => window.dispatchEvent(new Event("pageshow")));
    await expect(notice).toBeVisible();
    // Detection is advisory: no automatic navigation or loss of current work.
    await expect(page.locator('meta[name="app-version"]')).toHaveAttribute("content", originalDocument);
    await page.getByRole("button", { name: "Depois", exact: true }).click();
    await expect(notice).toBeHidden();
    await page.evaluate(() => document.dispatchEvent(new Event("visibilitychange")));
    await expect(notice).toBeVisible();
    await page.screenshot({ path: "test-results/cci-update-properties.png" });
    await page.getByRole("button", { name: "Atualizar agora", exact: true }).click();
    await expect(page.locator('meta[name="app-version"]')).toHaveAttribute("content", deployed);
    await expect(notice).toBeHidden();
    await expect(page.locator(".property-card .property-views").first()).toBeVisible();
    await expect(page).toHaveURL(/\/app\/imoveis$/);
});

test("updates remain visible above the fullscreen chat without discarding a draft", async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 568 });
    const current = (await (await page.request.get("/app-version")).json()).version;
    let deployed = current;
    await page.route("**/app-version", route => route.fulfill({ json: { version: deployed } }));
    await login(page);
    await page.goto("/app/imoveis/1");
    await page.getByRole("button", { name: "Iniciar negociação" }).click();
    const input = page.getByLabel("Mensagem", { exact: true });
    await input.fill("Rascunho que ainda não enviei.");
    deployed = "c".repeat(64);
    await page.evaluate(() => window.dispatchEvent(new Event("online")));
    const notice = page.getByRole("status", { name: "Atualização do aplicativo" });
    await expect(notice).toBeInViewport({ ratio: 1 });
    await expect(input).toHaveValue("Rascunho que ainda não enviei.");
    await expect(input).toBeInViewport({ ratio: 1 });
    const button = page.getByRole("button", { name: "Atualizar agora", exact: true });
    await button.click({ trial: true });
    await page.screenshot({ path: "test-results/cci-update-chat.png" });
    await page.getByRole("button", { name: "Depois", exact: true }).click();
    await expect(notice).toBeHidden();
    await expect(input).toHaveValue("Rascunho que ainda não enviei.");
    // A temporary failed version check must not interrupt the conversation.
    await page.route("**/app-version", route => route.fulfill({ status: 503, json: { version: null } }));
    await page.evaluate(() => window.dispatchEvent(new Event("online")));
    await expect(input).toHaveValue("Rascunho que ainda não enviei.");
});

test("a waiting worker is activated before the user-requested reload", async ({ page }) => {
    await page.addInitScript(() => {
        const worker = new EventTarget();
        worker.state = "installed";
        worker.postMessage = message => {
            if (message !== "SKIP_WAITING") throw new Error("Unexpected worker message");
            sessionStorage.setItem("cci-test-worker-activated", "yes");
            worker.state = "activated";
            worker.dispatchEvent(new Event("statechange"));
        };
        const registration = new EventTarget();
        registration.waiting = sessionStorage.getItem("cci-test-worker-activated") ? null : worker;
        registration.installing = null;
        registration.update = async () => {};
        registration.pushManager = { getSubscription: async () => null };
        navigator.serviceWorker.register = async () => registration;
        navigator.serviceWorker.getRegistration = async () => registration;
    });
    await login(page);
    await expect(page.getByRole("status", { name: "Atualização do aplicativo" })).toBeVisible();
    const navigation = page.waitForEvent("framenavigated", frame => frame === page.mainFrame());
    await page.getByRole("button", { name: "Atualizar agora", exact: true }).click();
    await navigation;
    await expect(page.locator(".property-card .property-views").first()).toBeVisible();
    expect(await page.evaluate(() => sessionStorage.getItem("cci-test-worker-activated"))).toBe("yes");
    await expect(page.getByRole("status", { name: "Atualização do aplicativo" })).toBeHidden();
});
