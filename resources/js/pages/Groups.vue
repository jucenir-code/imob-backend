<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { http, unwrap, errorText } from "../http";
import { session, loadSession, managesGroup } from "../session";
import Pagination from "../components/Pagination.vue";
const route = useRoute();
const router = useRouter();
const id = route.params.id;
const groups = ref([]);
const group = ref(null);
const meta = ref(null);
const busy = ref(false);
const error = ref("");
const name = ref("");
const member = reactive({ email: "", role_in_group: "member" });
const canManage = computed(
    () =>
        group.value &&
        (session.user?.role === "admin" ||
            group.value.owner_id === session.user?.id ||
            managesGroup(group.value.id)),
);
const roles = {
    owner: "Proprietário",
    moderator: "Moderador",
    member: "Membro",
};
async function load(page = 1) {
    error.value = "";
    try {
        if (id) {
            group.value = unwrap(
                await http.get(`/groups/${id}`, {
                    params: { with_members: true },
                }),
            );
            name.value = group.value.name;
        } else {
            const { data } = await http.get("/groups", { params: { page } });
            groups.value = data.data;
            meta.value = data.meta;
        }
    } catch (e) {
        error.value = errorText(e);
    }
}
async function action(callback) {
    busy.value = true;
    error.value = "";
    try {
        await callback();
        await loadSession();
        await load(meta.value?.current_page || 1);
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
async function create() {
    await action(async () => {
        await http.post("/groups", { name: name.value });
        name.value = "";
    });
}
async function addMember() {
    await action(async () => {
        await http.post(`/groups/${id}/members`, member);
        member.email = "";
    });
}
async function removeMember(person) {
    if (confirm(`Remover ${person.name} deste grupo?`))
        await action(() => http.delete(`/groups/${id}/members/${person.id}`));
}
async function removeGroup() {
    if (!confirm(`Excluir o grupo “${group.value.name}”?`)) return;
    busy.value = true;
    try {
        await http.delete(`/groups/${id}`);
        await loadSession();
        router.push("/app/grupos");
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
onMounted(() => load());
</script>
<template>
    <RouterLink v-if="id" class="back-link" to="/app/grupos"
        >← Voltar aos grupos</RouterLink
    >
    <div class="page-heading">
        <div>
            <span class="eyebrow">SUA REDE DE PARCEIROS</span>
            <h1>{{ group?.name || "Conexões que fazem a diferença." }}</h1>
            <p class="muted">
                Organize suas parcerias e compartilhe oportunidades.
            </p>
        </div>
    </div>
    <p v-if="error" class="alert error" role="alert">{{ error }}</p>
    <template v-if="!id"
        ><form class="panel inline-form" @submit.prevent="create">
            <label
                >Nome do novo grupo<input
                    v-model="name"
                    required
                    maxlength="255"
                    placeholder="Ex.: Parceiros da região sul" /></label
            ><button class="primary" :disabled="busy">Criar grupo</button>
        </form>
        <div class="group-grid">
            <RouterLink
                v-for="item in groups"
                :key="item.id"
                :to="`/app/grupos/${item.id}`"
                class="panel group-card"
                ><span class="avatar">{{ item.name.slice(0, 1) }}</span>
                <h2>{{ item.name }}</h2>
                <p class="muted">
                    {{ item.members_count }} membros ·
                    {{ item.properties_active_count }} imóveis ativos
                </p>
                <span class="badge">{{ roles[item.role_in_group] }}</span
                ><span class="pink"> Abrir grupo →</span></RouterLink
            >
        </div>
        <p v-if="!groups.length" class="empty">
            Você ainda não participa de um grupo. Crie sua primeira rede de
            parceiros.
        </p>
        <Pagination :meta="meta" :busy="busy" @change="load" /></template
    ><template v-else-if="group"
        ><RouterLink class="secondary" :to="`/app/imoveis?group_id=${group.id}`"
            >Ver imóveis do grupo →</RouterLink
        >
        <section v-if="canManage" class="panel">
            <h2>Configurações do grupo</h2>
            <form
                class="inline-form"
                @submit.prevent="
                    action(() => http.put(`/groups/${id}`, { name }))
                "
            >
                <label
                    >Nome<input
                        v-model="name"
                        maxlength="255"
                        required /></label
                ><button class="secondary" :disabled="busy">Salvar nome</button>
            </form>
            <form class="inline-form" @submit.prevent="addMember">
                <label
                    >E-mail do corretor<input
                        v-model="member.email"
                        type="email"
                        required
                        placeholder="corretor@exemplo.com" /></label
                ><label
                    >Permissão<select v-model="member.role_in_group">
                        <option value="member">Membro</option>
                        <option value="moderator">Moderador</option>
                    </select></label
                ><button class="primary" :disabled="busy">
                    Adicionar membro
                </button>
            </form>
        </section>
        <section class="panel">
            <h2>
                Membros <span class="count">{{ group.members_count }}</span>
            </h2>
            <div
                v-for="person in group.members"
                :key="person.id"
                class="member-row"
            >
                <span class="avatar">{{ person.name.slice(0, 1) }}</span>
                <div class="grow">
                    <strong>{{ person.name }}</strong>
                    <p class="muted">{{ person.email }}</p>
                </div>
                <template v-if="canManage && person.id !== group.owner_id"
                    ><select
                        :aria-label="`Permissão de ${person.name}`"
                        :value="person.role_in_group"
                        :disabled="busy"
                        @change="
                            action(() =>
                                http.patch(
                                    `/groups/${id}/members/${person.id}`,
                                    { role_in_group: $event.target.value },
                                ),
                            )
                        "
                    >
                        <option value="member">Membro</option>
                        <option value="moderator">Moderador</option></select
                    ><button
                        class="danger text-button"
                        :disabled="busy"
                        @click="removeMember(person)"
                    >
                        Remover
                    </button></template
                ><span v-else class="badge">{{
                    roles[person.role_in_group]
                }}</span>
            </div>
        </section>
        <button
            v-if="group.owner_id === session.user?.id"
            class="danger secondary"
            :disabled="busy"
            @click="removeGroup"
        >
            Excluir grupo
        </button></template
    >
</template>
