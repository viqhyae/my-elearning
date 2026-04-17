<script setup lang="ts">
definePageMeta({
  layout: false,
  ssr: false,
})

useGeminiAssets()
</script>

<template>
  <GeminiDashboardApp initial-role="admin" initial-menu="users" />
</template>