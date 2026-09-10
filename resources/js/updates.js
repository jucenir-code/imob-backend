import { computed, onMounted, onUnmounted, ref } from "vue";

export function useAppUpdates() {
    const available = ref(false);
    const applying = ref(false);
    const error = ref("");
    const dismissed = ref(false);
    const visible = computed(() => available.value && !dismissed.value);
    const loadedVersion = document.querySelector('meta[name="app-version"]')?.content;
    let registration;
    let waiting;
    let remoteVersion;
    let checking = false;
    let disposed = false;
    let timer;
    let request;
    const workers = new Map();

    function report() {
        if (disposed) return;
        if (waiting && waiting.state !== "installed") waiting = null;
        available.value = Boolean(waiting || (remoteVersion && remoteVersion !== loadedVersion));
    }
    function observeWorker() {
        waiting = registration?.waiting || waiting;
        const worker = registration?.installing;
        if (worker && !workers.has(worker)) {
            const changed = () => {
                if (worker.state === "installed" && navigator.serviceWorker.controller) {
                    waiting = worker;
                    dismissed.value = false;
                }
                report();
            };
            workers.set(worker, changed);
            worker.addEventListener("statechange", changed);
            changed();
        }
        report();
    }
    async function check() {
        if (disposed || checking || document.hidden || !navigator.onLine) return;
        checking = true;
        registration?.update().catch(() => {});
        request = new AbortController();
        const timeout = setTimeout(() => request?.abort(), 10000);
        try {
            if (loadedVersion) {
                const response = await fetch("/app-version", {
                    cache: "no-store", credentials: "same-origin",
                    headers: { Accept: "application/json" }, signal: request.signal,
                });
                if (response.ok) {
                    const data = await response.json();
                    if (!disposed && /^[a-f0-9]{64}$/.test(data.version)) {
                        if (data.version !== remoteVersion) dismissed.value = false;
                        remoteVersion = data.version;
                    }
                }
            }
        } catch { /* Offline/deploy failures must not interrupt the current screen. */ }
        finally {
            clearTimeout(timeout);
            checking = false;
            report();
        }
    }
    function resume() {
        if (document.hidden) return;
        dismissed.value = false;
        check();
    }
    async function register() {
        if (!("serviceWorker" in navigator)) return;
        try {
            registration = await navigator.serviceWorker.register("/sw.js", { updateViaCache: "none" });
            if (disposed) return;
            registration.addEventListener("updatefound", observeWorker);
            observeWorker();
        } catch { /* Version checks also work when service workers are unavailable. */ }
    }
    async function apply() {
        if (applying.value) return;
        if (!navigator.onLine) {
            error.value = "Conecte-se à internet para atualizar.";
            return;
        }
        applying.value = true;
        error.value = "";
        try {
            const worker = registration?.waiting || waiting;
            if (worker?.state === "installed") {
                await new Promise((resolve, reject) => {
                    const finish = (failure) => {
                        clearTimeout(timeout);
                        worker.removeEventListener("statechange", changed);
                        failure ? reject(failure) : resolve();
                    };
                    const changed = () => {
                        if (worker.state === "activated") finish();
                        if (worker.state === "redundant") finish(new Error("Tente atualizar novamente."));
                    };
                    const timeout = setTimeout(() => finish(new Error("Não foi possível atualizar. Tente novamente.")), 10000);
                    worker.addEventListener("statechange", changed);
                    worker.postMessage("SKIP_WAITING");
                    changed();
                });
            }
            window.location.reload();
        } catch (failure) {
            error.value = failure.message;
            applying.value = false;
        }
    }
    onMounted(() => {
        if (!import.meta.env.PROD) return;
        register();
        check();
        timer = setInterval(check, 60000);
        document.addEventListener("visibilitychange", resume);
        window.addEventListener("pageshow", resume);
        window.addEventListener("online", resume);
    });
    onUnmounted(() => {
        disposed = true;
        clearInterval(timer);
        request?.abort();
        registration?.removeEventListener("updatefound", observeWorker);
        for (const [worker, changed] of workers) worker.removeEventListener("statechange", changed);
        document.removeEventListener("visibilitychange", resume);
        window.removeEventListener("pageshow", resume);
        window.removeEventListener("online", resume);
    });
    return { visible, applying, error, apply, dismiss: () => { dismissed.value = true; } };
}
