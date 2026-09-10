<script setup>
import { onMounted, reactive, ref } from "vue";
import {
    http,
    cleanParams,
    errorText,
    money,
    propertyTypes,
    propertyStatuses,
} from "../http";
import { session } from "../session";
import Pagination from "../components/Pagination.vue";
import Icon from "../components/Icon.vue";
const filters = reactive({
    type: "",
    neighborhood: "",
    bedrooms: "",
    price_min: "",
    price_max: "",
    status: "active",
});
const items = ref([]);
const meta = ref(null);
const busy = ref(false);
const error = ref("");
let request = 0;
async function load(page = 1) {
    const current = ++request;
    busy.value = true;
    error.value = "";
    try {
        const { data } = await http.get("/properties", {
            params: { ...cleanParams(filters), page, per_page: 12 },
        });
        if (current === request) {
            items.value = data.data;
            meta.value = data.meta;
        }
    } catch (e) {
        if (current === request) error.value = errorText(e);
    } finally {
        if (current === request) busy.value = false;
    }
}
function reset() {
    Object.assign(filters, {
        type: "",
        neighborhood: "",
        bedrooms: "",
        price_min: "",
        price_max: "",
        status: "active",
    });
    load();
}
onMounted(() => load());
</script>
<template>
    <section>
        <div class="page-heading">
            <div>
                <span class="eyebrow">SUA REDE DE OPORTUNIDADES</span>
                <h1>Encontre o próximo negócio<span class="pink">.</span></h1>
                <p class="muted">
                    Explore imóveis e conecte-se com outros corretores.
                </p>
            </div>
            <RouterLink
                v-if="
                    session.user?.is_approved || session.user?.role === 'admin'
                "
                class="primary"
                to="/app/imoveis/novo"
                ><Icon name="plus" />Cadastrar imóvel</RouterLink
            >
        </div>
        <RouterLink class="notification-invite" to="/app/notificacoes">Ative os avisos de novas mensagens e imóveis →</RouterLink>
        <form class="panel filters" @submit.prevent="load()">
            <label class="search-field"
                >Bairro
                <div class="input-icon">
                    <Icon name="search" /><input
                        v-model="filters.neighborhood"
                        placeholder="Em qual bairro você procura?"
                    /></div></label
            ><label
                >Tipo de imóvel<select v-model="filters.type">
                    <option value="">Todos os tipos</option>
                    <option
                        v-for="(label, key) in propertyTypes"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </option>
                </select></label
            ><label
                >Quartos<select v-model="filters.bedrooms">
                    <option value="">Qualquer</option>
                    <option v-for="n in 6" :value="n" :key="n">{{ n }}</option>
                </select></label
            ><button class="primary">Buscar imóveis</button>
            <details class="advanced-filters">
                <summary>Mais filtros</summary>
                <div class="form-grid">
                    <label
                        >Preço mínimo<input
                            v-model="filters.price_min"
                            type="number"
                            min="0"
                            placeholder="R$ 0" /></label
                    ><label
                        >Preço máximo<input
                            v-model="filters.price_max"
                            type="number"
                            min="0"
                            placeholder="Sem limite" /></label
                    ><label
                        >Situação<select v-model="filters.status">
                            <option
                                v-for="(label, key) in propertyStatuses"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select></label
                    >
                </div>
                <button type="button" class="text-button" @click="reset">
                    Limpar filtros
                </button>
            </details>
        </form>
        <div class="section-line">
            <h2>
                Imóveis da rede
                <span v-if="meta" class="count">{{ meta.total }}</span>
            </h2>
            <span class="muted small">Mais recentes primeiro</span>
        </div>
        <p v-if="error" class="alert error" role="alert">
            {{ error }}
            <button class="text-button" @click="load()">
                Tentar novamente
            </button>
        </p>
        <div v-if="busy" class="empty" role="status">Carregando imóveis…</div>
        <div v-else-if="!items.length && !error" class="panel empty">
            <Icon name="home" />
            <h3>Nenhum imóvel encontrado</h3>
            <p>Experimente outro bairro ou ajuste os filtros.</p>
            <button class="secondary" @click="reset">Limpar filtros</button>
        </div>
        <div v-else class="property-grid">
            <RouterLink
                v-for="property in items"
                :key="property.id"
                :to="`/app/imoveis/${property.id}`"
                class="property-card"
                ><div class="property-photo">
                    <img
                        v-if="property.cover_image_url"
                        :src="property.cover_image_url"
                        :alt="property.title"
                        loading="lazy"
                    />
                    <div v-else class="photo-placeholder">
                        <Icon name="home" /><span>{{
                            propertyTypes[property.type]
                        }}</span>
                    </div>
                    <span class="photo-badge">{{
                        propertyTypes[property.type]
                    }}</span
                    ><span class="status-dot">{{
                        propertyStatuses[property.status]
                    }}</span>
                </div>
                <div class="property-body">
                    <p class="location">
                        {{ property.neighborhood }} · {{ property.city }}/{{
                            property.state
                        }}
                    </p>
                    <h3>{{ property.title }}</h3>
                    <strong class="price">{{
                        property.price_visibility === "hide"
                            ? "Sob consulta"
                            : money(property.price)
                    }}</strong>
                    <div class="property-specs">
                        <span>{{ property.bedrooms ?? "—" }} quartos</span
                        ><span>{{ property.bathrooms ?? "—" }} banheiros</span
                        ><span>{{ property.area_m2 ?? "—" }} m²</span>
                    </div>
                    <div class="property-owner">
                        <span class="mini-avatar">{{
                            property.owner?.name?.slice(0, 1) || "C"
                        }}</span
                        ><span>{{
                            property.owner?.name || "Corretor da rede"
                        }}</span
                        ><span class="pink">Ver imóvel ↗</span>
                    </div>
                </div></RouterLink
            >
        </div>
        <Pagination :meta="meta" :busy="busy" @change="load" />
    </section>
</template>
