<script setup>
defineProps({ name: String });
const paths = {
    bell: "M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4",
    info: "M12 11v6M12 7h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0",
    clip: "m8 12 6-6a3 3 0 0 1 4 4l-8 8a5 5 0 0 1-7-7l9-9M6 14l8-8",
    send: "m22 2-7 20-4-9-9-4ZM22 2 11 13",
    close: "m6 6 12 12M6 18 18 6",
    down: "M12 4v16m-7-7 7 7 7-7",
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
