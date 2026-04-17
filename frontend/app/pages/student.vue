<script setup lang="ts">
definePageMeta({
  layout: false,
  ssr: false,
})

useGeminiAssets()
</script>

<template>
  <GeminiDashboardApp initial-role="student" initial-menu="dashboard" />
</template>