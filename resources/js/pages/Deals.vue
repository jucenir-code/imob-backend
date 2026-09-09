<script setup>
import { onMounted, ref } from "vue";
import { http, cleanParams, dealStatuses, errorText, dateTime } from "../http";
import Pagination from "../components/Pagination.vue";
const items = ref([]);
const meta = ref(null);
const status = ref("");
const busy = ref(false);
const error = ref("");
async function load(page = 1) {
    busy.value = true;
    error.value = "";
    try {
        const { data } = await http.get("/deals", {
            params: cleanParams({ status: status.value, page }),
        });
        items.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
onMounted(() => load());
</script>
<template>
    <div class="page-heading">
        <div>
            <span class="eyebrow">PARCERIAS EM MOVIMENTO</span>
            <h1>Suas negociações<span class="pink">.</span></h1>
            <p class="muted">Da primeira conversa ao negócio fechado.</p>
        </div>
        <RouterLink class="secondary" to="/app/imoveis"
            >Explorar imóveis</RouterLink
        >
    </div>
    <div class="section-line">
        <h2>Conversas e oportunidades</h2>
        <label class="inline-label"
            >Situação<select v-model="status" :disabled="busy" @change="load()">
                <option value="">Todas</option>
                <option
                    v-for="(label, key) in dealStatuses"
                    :key="key"
                    :value="key"
                >
                    {{ label }}
                </option>
            </select></label
        >
    </div>
    <p v-if="error" class="alert error" role="alert">
        {{ error }}
        <button class="text-button" @click="load()">Tentar novamente</button>
    </p>
    <p v-if="busy" class="empty" role="status">Carregando negociações…</p>
    <div v-else-if="!items.length && !error" class="panel empty">
        <h3>Suas parcerias começam aqui</h3>
        <p>Abra um imóvel da rede e inicie uma negociação.</p>
    </div>
    <div v-else class="deal-list">
        <RouterLink
            v-for="deal in items"
            :key="deal.id"
            :to="`/app/negociacoes/${deal.id}`"
            class="panel deal-row"
            ><span class="avatar">{{
                deal.buyer_agent?.name?.slice(0, 1)
            }}</span>
            <div class="grow">
                <h3>{{ deal.property?.title }}</h3>
                <p>
                    {{ deal.seller_agent?.name }} · {{ deal.buyer_agent?.name }}
                </p>
                <small>{{ dateTime(deal.updated_at) }}</small>
            </div>
            <span class="badge">{{ dealStatuses[deal.status] }}</span
            ><span class="pink">→</span></RouterLink
        >
    </div>
    <Pagination :meta="meta" :busy="busy" @change="load" />
</template>
