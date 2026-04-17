<script setup lang="ts">
definePageMeta({
  layout: false,
  ssr: false,
})

useGeminiAssets()

const route = useRoute()
const auth = useAuth()
await auth.ensureSession()
const loggedIn = computed(() => auth.isAuthenticated.value)
const courseSlug = computed(() => String(route.params.slug || ''))
</script>

<template>
  <GeminiFrontendApp
    initial-page="course-detail"
    :initial-course-slug="courseSlug"
    :logged-in="loggedIn"
  />
</template>