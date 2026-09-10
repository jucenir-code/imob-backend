const CACHE = "cci-static-v1";
const FALLBACK = "/offline.html";
self.addEventListener("install", (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll([FALLBACK, "/icons/icon-512.png"])),
    );
});
self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith("cci-static-") && key !== CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});
self.addEventListener("message", (event) => {
    if (event.data === "SKIP_WAITING") self.skipWaiting();
});
self.addEventListener("fetch", (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== "GET" || url.origin !== self.location.origin)
        return;
    // Never store HTML with session/CSRF data, domain responses, or private attachments.
    if (event.request.mode === "navigate") {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(FALLBACK)),
        );
    } else if (
        url.pathname.startsWith("/build/assets/") ||
        url.pathname.startsWith("/icons/")
    ) {
        event.respondWith(
            caches.match(event.request).then(
                (cached) =>
                    cached ||
                    fetch(event.request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            event.waitUntil(
                                caches
                                    .open(CACHE)
                                    .then((cache) =>
                                        cache.put(event.request, copy),
                                    ),
                            );
                        }
                        return response;
                    }),
            ),
        );
    }
});

self.addEventListener('push', (event) => {
    let payload = {};
    try { payload = event.data?.json() || {}; } catch {}
    // Always show a visible notification, including on iOS. No private chat text.
    const url = /^\/app\/(negociacoes|imoveis)\/\d+$/.test(payload.url) ? payload.url : '/app/imoveis';
    event.waitUntil((async () => {
        await self.registration.showNotification(payload.title || 'Novidade na CCI', {
        body: payload.body || 'Abra a CCI para conferir.',
        icon: '/icons/icon-512.png',
        badge: '/icons/icon-512.png',
        tag: payload.tag || 'cci-notification',
        data: { url },
        });
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) client.postMessage({ type: 'CCI_PUSH', title: payload.title, body: payload.body, url, user_id: payload.user_id });
    })());
});
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const path = event.notification.data?.url;
    const url = new URL(/^\/app\/(negociacoes|imoveis)\/\d+$/.test(path) ? path : '/app/imoveis', self.location.origin).href;
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const existing = windows.find(client => new URL(client.url).origin === self.location.origin);
        if (existing) {
            try {
                await existing.navigate(url);
                await existing.focus();
                return;
            } catch { /* The window may have closed since matchAll(). */ }
        }
        await self.clients.openWindow(url);
    })());
});
