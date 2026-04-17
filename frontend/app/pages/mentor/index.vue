<script setup lang="ts">
definePageMeta({
  layout: false,
  ssr: false,
})

useGeminiAssets()
</script>

<template>
  <GeminiDashboardApp initial-role="mentor" initial-menu="dashboard" />
</template>