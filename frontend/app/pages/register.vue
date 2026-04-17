<script setup lang="ts">
definePageMeta({
  layout: false,
  ssr: false,
})

useGeminiAssets()
</script>

<template>
  <GeminiFrontendApp initial-page="register" />
</template>
