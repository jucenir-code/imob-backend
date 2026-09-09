<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    http,
    unwrap,
    errorText,
    propertyTypes,
    propertyStatuses,
} from "../http";
import { session, loadSession } from "../session";
const route = useRoute();
const router = useRouter();
const editing = Boolean(route.params.id);
const form = reactive({
    title: "",
    description: "",
    type: "apartment",
    bedrooms: 0,
    bathrooms: 0,
    parking: 0,
    area_m2: null,
    neighborhood: "",
    city: "",
    state: "SP",
    price: null,
    price_visibility: "show",
    status: "active",
});
const states =
    "AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO".split(
        " ",
    );
const busy = ref(false);
const loading = ref(editing);
const error = ref("");
const allowed = computed(
    () => session.user?.is_approved || session.user?.role === "admin",
);
onMounted(async () => {
    if (editing) {
        try {
            const property = unwrap(
                await http.get(`/properties/${route.params.id}`),
            );
            for (const key of Object.keys(form))
                if (key in property) form[key] = property[key];
        } catch (e) {
            error.value = errorText(e);
        } finally {
            loading.value = false;
        }
    }
});
async function submit() {
    busy.value = true;
    error.value = "";
    try {
        const payload = { ...form };
        if (editing) delete payload.type;
        const property = unwrap(
            await http[editing ? "put" : "post"](
                editing ? `/properties/${route.params.id}` : "/properties",
                payload,
            ),
        );
        await loadSession();
        router.push(`/app/imoveis/${property.id}`);
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <RouterLink class="back-link" to="/app/imoveis"
        >← Voltar aos imóveis</RouterLink
    >
    <div class="page-heading">
        <div>
            <span class="eyebrow">SEU PORTFÓLIO</span>
            <h1>{{ editing ? "Editar imóvel" : "Uma nova oportunidade" }}</h1>
            <p class="muted">Apresente seu imóvel aos corretores da rede.</p>
        </div>
    </div>
    <p v-if="error" class="alert error" role="alert">{{ error }}</p>
    <p v-if="loading" role="status">Carregando…</p>
    <form v-else-if="allowed" class="editor" @submit.prevent="submit">
        <section class="panel">
            <h2>Sobre o imóvel</h2>
            <label
                >Título do anúncio<input
                    v-model="form.title"
                    required
                    maxlength="150"
                    placeholder="Ex.: Apartamento com varanda no centro" /></label
            ><label
                >Tipo de imóvel<select v-model="form.type" :disabled="editing">
                    <option
                        v-for="(label, key) in propertyTypes"
                        :value="key"
                        :key="key"
                    >
                        {{ label }}
                    </option>
                </select></label
            ><label
                >Descrição<textarea
                    v-model="form.description"
                    required
                    rows="5"
                    placeholder="Conte os diferenciais deste imóvel…"
                ></textarea>
            </label>
        </section>
        <section class="panel">
            <h2>Características</h2>
            <div class="form-grid">
                <label
                    >Quartos<input
                        v-model.number="form.bedrooms"
                        type="number"
                        min="0"
                        step="1" /></label
                ><label
                    >Banheiros<input
                        v-model.number="form.bathrooms"
                        type="number"
                        min="0"
                        step="1" /></label
                ><label
                    >Vagas<input
                        v-model.number="form.parking"
                        type="number"
                        min="0"
                        step="1" /></label
                ><label
                    >Área (m²)<input
                        v-model.number="form.area_m2"
                        type="number"
                        min="0"
                        step="0.01"
                /></label>
            </div>
        </section>
        <section class="panel">
            <h2>Localização</h2>
            <label
                >Bairro<input
                    v-model="form.neighborhood"
                    required
                    maxlength="120"
            /></label>
            <div class="form-grid">
                <label
                    >Cidade<input
                        v-model="form.city"
                        required
                        maxlength="80" /></label
                ><label
                    >Estado<select v-model="form.state" required>
                        <option v-for="state in states" :key="state">
                            {{ state }}
                        </option>
                    </select></label
                >
            </div>
        </section>
        <section class="panel">
            <h2>Valor e disponibilidade</h2>
            <div class="form-grid">
                <label
                    >Preço (R$)<input
                        v-model.number="form.price"
                        type="number"
                        required
                        min="0"
                        step="0.01" /></label
                ><label
                    >Exibir valor<select v-model="form.price_visibility">
                        <option value="show">Exibir preço</option>
                        <option value="hide">Sob consulta</option>
                    </select></label
                ><label
                    >Situação<select v-model="form.status">
                        <option
                            v-for="(label, key) in propertyStatuses"
                            :value="key"
                            :key="key"
                        >
                            {{ label }}
                        </option>
                    </select></label
                >
            </div>
        </section>
        <div class="form-actions">
            <RouterLink class="secondary" to="/app/imoveis">Cancelar</RouterLink
            ><button class="primary" :disabled="busy">
                {{ busy ? "Salvando…" : "Salvar imóvel" }}
            </button>
        </div>
    </form>
    <p v-else class="alert">
        Seu cadastro precisa ser aprovado para publicar imóveis.
    </p>
</template>
