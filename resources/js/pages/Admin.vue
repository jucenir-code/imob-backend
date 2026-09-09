<script setup>
import { onMounted, reactive, ref } from "vue";
import { http, cleanParams, errorText, dateTime } from "../http";
import { session } from "../session";
import Pagination from "../components/Pagination.vue";
const users = ref([]);
const meta = ref(null);
const busy = ref(false);
const error = ref("");
const success = ref("");
const filters = reactive({ status: "", role: "" });
const invite = reactive({ email: "", expires_in_hours: 72 });
const inviteUrl = ref("");
async function load(page = 1) {
    error.value = "";
    try {
        const { data } = await http.get("/admin/users", {
            params: cleanParams({ ...filters, page }),
        });
        users.value = data.data;
        meta.value = data;
    } catch (e) {
        error.value = errorText(e);
    }
}
async function act(user, action) {
    if (action === "delete" && !confirm(`Excluir a conta de ${user.name}?`))
        return;
    busy.value = true;
    error.value = "";
    success.value = "";
    try {
        if (action === "delete") await http.delete(`/admin/users/${user.id}`);
        else await http.post(`/admin/users/${user.id}/${action}`);
        success.value = "Usuário atualizado.";
        await load(meta.value?.current_page || 1);
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
async function createInvite() {
    busy.value = true;
    error.value = "";
    try {
        const { data } = await http.post("/admin/invites", cleanParams(invite));
        inviteUrl.value = data.invite_url;
        success.value = `Convite criado. Válido até ${dateTime(data.expires_at)}.`;
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
async function copy() {
    try {
        await navigator.clipboard.writeText(inviteUrl.value);
        success.value = "Link copiado.";
    } catch {
        error.value = "Selecione o link e copie manualmente.";
    }
}
onMounted(() => load());
</script>
<template>
    <div class="page-heading">
        <div>
            <span class="eyebrow">ADMINISTRAÇÃO</span>
            <h1>Uma rede bem cuidada<span class="pink">.</span></h1>
            <p class="muted">
                Gerencie os corretores e convide novos parceiros.
            </p>
        </div>
    </div>
    <p v-if="error" class="alert error" role="alert">{{ error }}</p>
    <p v-if="success" class="alert success" role="status">{{ success }}</p>
    <section class="panel">
        <h2>Convidar um corretor</h2>
        <form class="inline-form" @submit.prevent="createInvite">
            <label
                >E-mail (opcional)<input
                    v-model="invite.email"
                    type="email"
                    placeholder="corretor@exemplo.com" /></label
            ><label
                >Validade em horas<input
                    v-model.number="invite.expires_in_hours"
                    type="number"
                    required
                    min="1"
                    max="720" /></label
            ><button class="primary" :disabled="busy">Gerar convite</button>
        </form>
        <div v-if="inviteUrl" class="inline-form">
            <label
                >Link do convite<input
                    :value="inviteUrl"
                    readonly
                    @focus="$event.target.select()" /></label
            ><button class="secondary" @click="copy">Copiar link</button>
        </div>
    </section>
    <section class="panel">
        <div class="section-line">
            <h2>
                Corretores
                <span v-if="meta" class="count">{{ meta.total }}</span>
            </h2>
            <div class="inline-form">
                <label
                    >Aprovação<select v-model="filters.status" @change="load()">
                        <option value="">Todos</option>
                        <option value="pending">Pendentes</option>
                        <option value="approved">Aprovados</option>
                    </select></label
                ><label
                    >Perfil<select v-model="filters.role" @change="load()">
                        <option value="">Todos</option>
                        <option value="agent">Corretor</option>
                        <option value="admin">Administrador</option>
                    </select></label
                >
            </div>
        </div>
        <div v-for="user in users" :key="user.id" class="member-row">
            <span class="avatar">{{ user.name.slice(0, 1) }}</span>
            <div class="grow">
                <strong>{{ user.name }}</strong>
                <p class="muted">{{ user.email }}</p>
                <small class="muted"
                    >{{
                        user.role === "admin" ? "Administrador" : "Corretor"
                    }}
                    · {{ user.status }}</small
                >
            </div>
            <span class="badge" :class="{ approved: user.is_approved }">{{
                user.is_approved ? "Aprovado" : "Pendente"
            }}</span>
            <div class="row-actions">
                <button
                    class="secondary"
                    :disabled="busy"
                    @click="act(user, user.is_approved ? 'reject' : 'approve')"
                >
                    {{
                        user.is_approved ? "Remover aprovação" : "Aprovar"
                    }}</button
                ><button
                    v-if="user.id !== session.user?.id"
                    class="danger text-button"
                    :disabled="busy"
                    @click="act(user, 'delete')"
                >
                    Excluir
                </button>
            </div>
        </div>
        <p v-if="!users.length" class="empty">Nenhum usuário encontrado.</p>
        <Pagination :meta="meta" :busy="busy" @change="load" />
    </section>
</template>
