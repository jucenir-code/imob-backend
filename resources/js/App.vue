<script setup>
import { computed, ref, onMounted, onUnmounted } from "vue";
import { useRoute } from "vue-router";
import { session } from "./session";
import { http, errorText } from "./http";
import { disablePush } from "./push";
import Icon from "./components/Icon.vue";
const route = useRoute();
const online = ref(navigator.onLine);
const installPrompt = ref(null);
const update = ref(null);
const error = ref("");
const signingOut = ref(false);
const pushNotice = ref(null);
let noticeTimer;
function receivePush(event) {
    const data = event.data;
    if (data?.type !== 'CCI_PUSH' || Number(data.user_id) !== session.user?.id) return;
    if (!/^\/app\/(negociacoes|imoveis)\/\d+$/.test(data.url)) return;
    window.dispatchEvent(new CustomEvent('cci:push', { detail: data }));
    if (route.path === data.url) return;
    pushNotice.value = data;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => { pushNotice.value = null; }, 8000);
}
const links = computed(() => [
    { to: "/app/imoveis", label: "Imóveis", icon: "home" },
    { to: "/app/negociacoes", label: "Negociações", icon: "deals" },
    ...(session.user?.role === "admin"
        ? [{ to: "/app/gerenciar", label: "Gerenciar", icon: "admin" }]
        : []),
]);
const connectivity = () => {
    online.value = navigator.onLine;
};
const captureInstall = (event) => {
    event.preventDefault();
    installPrompt.value = event;
};
const installed = () => {
    installPrompt.value = null;
};
async function install() {
    await installPrompt.value?.prompt();
    installPrompt.value = null;
}
async function logout() {
    signingOut.value = true;
    try {
        await disablePush();
        await http.post("/logout", {}, { baseURL: "/" });
        session.user = null;
        location.assign("/login");
    } catch (e) {
        error.value = errorText(e);
        signingOut.value = false;
    }
}
onMounted(() => {
    navigator.serviceWorker?.addEventListener("message", receivePush);
    window.addEventListener("online", connectivity);
    window.addEventListener("offline", connectivity);
    window.addEventListener("beforeinstallprompt", captureInstall);
    window.addEventListener("appinstalled", installed);
    if ("serviceWorker" in navigator && import.meta.env.PROD)
        navigator.serviceWorker.ready.then((registration) => {
            if (registration.waiting) update.value = registration.waiting;
            registration.addEventListener("updatefound", () => {
                const worker = registration.installing;
                worker?.addEventListener("statechange", () => {
                    if (
                        worker.state === "installed" &&
                        navigator.serviceWorker.controller
                    )
                        update.value = worker;
                });
            });
        });
});
onUnmounted(() => {
    navigator.serviceWorker?.removeEventListener("message", receivePush);
    clearTimeout(noticeTimer);
    window.removeEventListener("online", connectivity);
    window.removeEventListener("offline", connectivity);
    window.removeEventListener("beforeinstallprompt", captureInstall);
    window.removeEventListener("appinstalled", installed);
});
function applyUpdate() {
    navigator.serviceWorker.addEventListener(
        "controllerchange",
        () => location.reload(),
        { once: true },
    );
    update.value?.postMessage("SKIP_WAITING");
}
</script>
<template>
    <aside v-if="pushNotice" class="push-toast" role="status">
        <RouterLink :to="pushNotice.url" @click="pushNotice = null"><strong>{{ pushNotice.title }}</strong><span>{{ pushNotice.body }}</span></RouterLink>
        <button class="icon-button" aria-label="Fechar aviso" @click="pushNotice = null"><Icon name="close" /></button>
    </aside>
    <a class="skip-link" href="#main">Ir para o conteúdo</a>
    <div v-if="!online" class="network-banner" role="status">
        Você está sem conexão. Reconecte-se para carregar e salvar informações.
    </div>
    <div v-if="update" class="network-banner">
        Uma nova versão está disponível.
        <button class="text-button" @click="applyUpdate">Atualizar</button>
    </div>
    <div v-if="route.meta.public" id="main"><RouterView /></div>
    <div v-else class="app-shell" :class="{ 'conversation-shell': route.meta.conversation }">
        <aside class="sidebar">
            <RouterLink to="/app/imoveis" class="brand"
                ><img src="/icons/icon-512.png" alt="CCI" /><span
                    >CCI<small>Conexões que geram negócios</small></span
                ></RouterLink
            >
            <p class="nav-caption">SEU ESPAÇO DE TRABALHO</p>
            <nav aria-label="Menu principal">
                <RouterLink v-for="link in links" :key="link.to" :to="link.to"
                    ><Icon :name="link.icon" />{{ link.label }}</RouterLink
                >
            </nav>
            <div class="sidebar-bottom">
                <div class="network-card">
                    <span class="eyebrow">JUNTOS, VAMOS MAIS LONGE</span
                    ><strong>Sua próxima parceria<br />começa aqui.</strong>
                    <p>Conecte imóveis, pessoas e oportunidades.</p>
                </div>
                <button v-if="installPrompt" class="secondary" @click="install">
                    <Icon name="download" />Instalar aplicativo
                </button>
                <details class="install-help">
                    <summary>Como instalar no celular</summary>
                    <p>
                        No iPhone, use Compartilhar → Adicionar à Tela de
                        Início. No Android, use Instalar aplicativo no menu do
                        navegador.
                    </p>
                </details>
                <div class="profile">
                    <span class="avatar">{{
                        session.user?.name?.slice(0, 1)
                    }}</span>
                    <div>
                        <strong>{{ session.user?.name }}</strong
                        ><small>{{
                            session.user?.role === "admin"
                                ? "Administrador"
                                : "Corretor de imóveis"
                        }}</small>
                    </div>
                    <button
                        class="icon-button"
                        aria-label="Sair da conta"
                        :disabled="signingOut"
                        @click="logout"
                    >
                        <Icon name="logout" />
                    </button>
                </div>
            </div>
        </aside>
        <div class="workspace">
            <header class="topbar">
                <span
                    >Central de Corretores de Imóveis
                    <span class="muted">/ {{ route.meta.title }}</span></span
                >
                <div class="topbar-actions">
                    <RouterLink class="icon-button" to="/app/notificacoes" aria-label="Notificações"><Icon name="bell" /></RouterLink>
                    <span class="connection"
                        ><i :class="{ offline: !online }"></i
                        >{{ online ? "Conectado" : "Sem conexão" }}</span
                    ><button
                        v-if="installPrompt"
                        class="secondary mobile-install"
                        @click="install"
                    >
                        Instalar</button
                    ><button
                        class="icon-button mobile-logout"
                        aria-label="Sair da conta"
                        :disabled="signingOut"
                        @click="logout"
                    >
                        <Icon name="logout" />
                    </button>
                </div>
            </header>
            <main id="main">
                <p v-if="error" class="alert error" role="alert">{{ error }}</p>
                <p
                    v-if="
                        session.user &&
                        !session.user.is_approved &&
                        session.user.role !== 'admin'
                    "
                    class="alert"
                >
                    Seu cadastro aguarda aprovação para publicar imóveis.
                </p>
                <RouterView :key="route.path" />
            </main>
        </div>
        <nav class="bottom-nav" aria-label="Menu do celular">
            <RouterLink v-for="link in links" :key="link.to" :to="link.to"
                ><Icon :name="link.icon" /><span>{{
                    link.label
                }}</span></RouterLink
            >
        </nav>
    </div>
</template>
