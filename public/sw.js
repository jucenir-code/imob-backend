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
