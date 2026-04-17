<script setup lang="ts">
definePageMeta({
  layout: false,
  ssr: false,
})

useGeminiAssets()

const auth = useAuth()
await auth.ensureSession()
const loggedIn = computed(() => auth.isAuthenticated.value)
</script>

<template>
  <GeminiFrontendApp initial-page="landing" :logged-in="loggedIn" />
</template>