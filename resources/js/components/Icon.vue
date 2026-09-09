<script setup>
defineProps({ name: String });
const paths = {
    home: "m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z",
    deals: "M8 6V4h8v2M3 7h18v13H3ZM3 12c6 4 12 4 18 0M12 12v4",
    admin: "m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6ZM8 12l3 3 5-6",
    plus: "M12 5v14M5 12h14",
    arrow: "M19 12H5m7-7-7 7 7 7",
    search: "m21 21-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0",
    logout: "M9 4H3v16h6m5-13 5 5-5 5M7 12h14",
    download: "M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5",
};
</script>
<template>
    <svg
        width="21"
        height="21"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path :d="paths[name] || paths.home" />
    </svg>
</template>
