<script setup lang="ts">
definePageMeta({
  layout: false,
  ssr: false,
  middleware: ['auth', 'role'],
  role: 'mentor',
})

useGeminiAssets()
</script>

<template>
  <GeminiDashboardApp initial-role="mentor" initial-menu="dashboard" />
</template>
