<script setup lang="ts">
definePageMeta({
  layout: false,
  ssr: false,
  middleware: 'guest',
})

useGeminiAssets()
</script>

<template>
  <GeminiFrontendApp initial-page="register" />
</template>
