import { defineConfig } from "@playwright/test";
import os from "node:os";
const database = `${os.tmpdir()}/cci-browser-${process.pid}.sqlite`;
export default defineConfig({
    testDir: "./tests/Browser",
    testMatch: "*.spec.js",
    fullyParallel: false,
    workers: 1,
    timeout: 45000,
    use: {
        baseURL: "http://127.0.0.1:8765",
        headless: true,
        trace: "retain-on-failure",
    },
    webServer: {
        command:
            "php tests/Browser/prepare.php && php artisan serve --host=127.0.0.1 --port=8765 --no-reload",
        url: "http://127.0.0.1:8765/login",
        timeout: 240000,
        reuseExistingServer: false,
        env: {
            APP_ENV: "testing",
            APP_URL: "http://127.0.0.1:8765",
            APP_DEBUG: "true",
            APP_KEY: "base64:MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=",
            DB_CONNECTION: "sqlite",
            DB_DATABASE: database,
            SESSION_DRIVER: "file",
            CACHE_DRIVER: "array",
            QUEUE_CONNECTION: "sync",
            SESSION_SECURE_COOKIE: "false",
            MAIL_MAILER: "array",
        },
    },
});
