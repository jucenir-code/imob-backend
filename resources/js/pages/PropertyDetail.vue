<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    http,
    unwrap,
    money,
    errorText,
    propertyTypes,
    propertyStatuses,
} from "../http";
import { session, managesGroup } from "../session";
import Icon from "../components/Icon.vue";
const route = useRoute();
const router = useRouter();
const property = ref(null);
const error = ref("");
const busy = ref(false);
const loading = ref(true);
const editable = computed(
    () =>
        property.value &&
        (session.user?.is_approved || session.user?.role === "admin") &&
        (property.value.owner_id === session.user?.id ||
            managesGroup(property.value.group_id)),
);
onMounted(async () => {
    try {
        property.value = unwrap(
            await http.get(`/properties/${route.params.id}`),
        );
    } catch (e) {
        error.value = errorText(e);
    } finally {
        loading.value = false;
    }
});
async function contact() {
    busy.value = true;
    error.value = "";
    try {
        const deal = unwrap(
            await http.post("/deals", { property_id: property.value.id }),
        );
        router.push(`/app/negociacoes/${deal.id}`);
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
async function remove() {
    if (!confirm(`Excluir o imóvel “${property.value.title}”?`)) return;
    busy.value = true;
    try {
        await http.delete(`/properties/${property.value.id}`);
        router.push("/app/imoveis");
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
    <p v-if="error" class="alert error" role="alert">{{ error }}</p>
    <p v-if="loading" role="status">Carregando imóvel…</p>
    <template v-if="property"
        ><div class="page-heading">
            <div>
                <span class="eyebrow"
                    >{{ propertyTypes[property.type] }} ·
                    {{ propertyStatuses[property.status] }}</span
                >
                <h1>{{ property.title }}</h1>
                <p class="muted">
                    {{ property.neighborhood }} · {{ property.city }}/{{
                        property.state
                    }}
                </p>
            </div>
            <RouterLink
                v-if="editable"
                class="secondary"
                :to="`/app/imoveis/${property.id}/editar`"
                >Editar imóvel</RouterLink
            >
        </div>
        <div class="detail-grid">
            <div>
                <div class="detail-photo">
                    <img
                        v-if="property.cover_image_url"
                        :src="property.cover_image_url"
                        :alt="property.title"
                    />
                    <div v-else class="photo-placeholder">
                        <Icon name="home" /><span
                            >Fotos ainda não disponíveis</span
                        >
                    </div>
                </div>
                <div v-if="property.images?.length" class="gallery">
                    <a
                        v-for="photo in property.images"
                        :key="photo.id"
                        :href="photo.url"
                        target="_blank"
                        rel="noopener"
                        ><img
                            :src="photo.url"
                            :alt="photo.description || property.title"
                            loading="lazy"
                    /></a>
                </div>
                <section class="panel">
                    <h2>Conheça o imóvel</h2>
                    <div class="spec-grid">
                        <div>
                            <strong>{{ property.bedrooms ?? "—" }}</strong
                            >quartos
                        </div>
                        <div>
                            <strong>{{ property.bathrooms ?? "—" }}</strong
                            >banheiros
                        </div>
                        <div>
                            <strong>{{ property.parking ?? "—" }}</strong
                            >vagas
                        </div>
                        <div>
                            <strong>{{ property.area_m2 ?? "—" }}</strong
                            >m²
                        </div>
                    </div>
                    <p class="preserve-lines">{{ property.description }}</p>
                </section>
            </div>
            <aside class="panel contact-card">
                <span class="eyebrow">VALOR DO IMÓVEL</span
                ><strong class="detail-price">{{
                    property.price_visibility === "hide"
                        ? "Sob consulta"
                        : money(property.price)
                }}</strong>
                <hr />
                <p class="muted">Corretor responsável</p>
                <h3>{{ property.owner?.name }}</h3>
                <p class="muted">{{ property.group?.name }}</p>
                <button
                    v-if="property.owner_id !== session.user?.id"
                    class="primary full"
                    :disabled="busy"
                    @click="contact"
                >
                    {{ busy ? "Abrindo…" : "Iniciar negociação →" }}
                </button>
                <p v-else class="alert">
                    Este imóvel faz parte da sua carteira.
                </p>
                <p class="small muted">
                    Converse e acompanhe sua parceria pelo chat interno.
                </p>
                <button
                    v-if="editable"
                    class="danger text-button"
                    :disabled="busy"
                    @click="remove"
                >
                    Excluir imóvel
                </button>
            </aside>
        </div></template
    >
</template>
