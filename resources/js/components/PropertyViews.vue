<script setup>
import { computed } from "vue";
import Icon from "./Icon.vue";

const props = defineProps({ count: { type: Number, default: 0 } });
const formatted = computed(() => new Intl.NumberFormat("pt-BR").format(props.count));
const label = computed(() => `${formatted.value} ${props.count === 1 ? "visualização" : "visualizações"}`);
</script>

<template>
    <span class="property-views" :aria-label="label" :title="label" role="img">
        <Icon name="eye" />
        <span aria-hidden="true">{{ formatted }}</span>
    </span>
</template>
