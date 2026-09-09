<script setup>
defineProps({ meta: Object, busy: Boolean });
defineEmits(["change"]);
</script>
<template>
    <nav v-if="meta?.last_page > 1" class="pagination" aria-label="Paginação">
        <button
            class="secondary"
            :disabled="busy || meta.current_page <= 1"
            @click="$emit('change', meta.current_page - 1)"
        >
            Anterior</button
        ><span>{{ meta.current_page }} de {{ meta.last_page }}</span
        ><button
            class="secondary"
            :disabled="busy || meta.current_page >= meta.last_page"
            @click="$emit('change', meta.current_page + 1)"
        >
            Próxima
        </button>
    </nav>
</template>
