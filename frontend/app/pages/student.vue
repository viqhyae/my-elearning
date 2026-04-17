<script setup lang="ts">
definePageMeta({
  layout: false,
  ssr: false,
  middleware: ['auth', 'role'],
  role: 'student',
})

useGeminiAssets()
</script>

<template>
  <GeminiDashboardApp initial-role="student" initial-menu="dashboard" />
</template>
