import { createApp } from "vue";
import { createRouter, createWebHistory } from "vue-router";
import App from "./App.vue";
import { loadSession, session } from "./session";
const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: "/login",
            component: () => import("./pages/Login.vue"),
            meta: { public: true, title: "Entrar" },
        },
        {
            path: "/app/imoveis",
            component: () => import("./pages/Properties.vue"),
            meta: { title: "Imóveis" },
        },
        {
            path: "/app/imoveis/novo",
            component: () => import("./pages/PropertyForm.vue"),
            meta: { title: "Novo imóvel" },
        },
        {
            path: "/app/imoveis/:id/editar",
            component: () => import("./pages/PropertyForm.vue"),
            meta: { title: "Editar imóvel" },
        },
        {
            path: "/app/imoveis/:id",
            component: () => import("./pages/PropertyDetail.vue"),
            meta: { title: "Detalhes do imóvel" },
        },
        {
            path: "/app/negociacoes",
            component: () => import("./pages/Deals.vue"),
            meta: { title: "Negociações" },
        },
        {
            path: "/app/negociacoes/:id",
            component: () => import("./pages/DealDetail.vue"),
            meta: { title: "Negociação" },
        },
        {
            path: "/app/grupos",
            component: () => import("./pages/Groups.vue"),
            meta: { title: "Grupos" },
        },
        {
            path: "/app/grupos/:id",
            component: () => import("./pages/Groups.vue"),
            meta: { title: "Detalhes do grupo" },
        },
        {
            path: "/app/gerenciar",
            component: () => import("./pages/Admin.vue"),
            meta: { title: "Gerenciar", admin: true },
        },
        { path: "/:pathMatch(.*)*", redirect: "/app/imoveis" },
    ],
    scrollBehavior: () => ({ top: 0 }),
});
router.beforeEach(async (to) => {
    if (!to.meta.public && !session.user) {
        try {
            await loadSession();
        } catch {
            return "/login";
        }
    }
    if (to.meta.admin && session.user?.role !== "admin") return "/app/imoveis";
    document.title = `${to.meta.title || "Imóveis"} · CCI`;
});
createApp(App).use(router).mount("#app");
if (import.meta.env.PROD && "serviceWorker" in navigator) {
    window.addEventListener("load", () =>
        navigator.serviceWorker.register("/sw.js").catch(() => {}),
    );
}
