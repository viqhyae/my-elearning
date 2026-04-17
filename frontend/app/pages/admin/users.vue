<script setup lang="ts">
definePageMeta({
  layout: false,
  ssr: false,
  middleware: ['auth', 'role'],
  role: 'admin',
})

useGeminiAssets()
</script>

<template>
  <GeminiDashboardApp initial-role="admin" initial-menu="users" />
</template>
