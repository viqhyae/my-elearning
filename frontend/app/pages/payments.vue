<script setup lang="ts">
definePageMeta({
  layout: false,
  ssr: false,
  middleware: 'auth',
})

useGeminiAssets()

const auth = useAuth()
await auth.ensureSession()

const initialRole = computed(() => {
  const role = auth.user.value?.role

  if (role === 'admin') {
    return 'admin'
  }

  if (role === 'mentor') {
    return 'mentor'
  }

  return 'student'
})
</script>

<template>
  <GeminiDashboardApp :initial-role="initialRole" initial-menu="transactions" />
</template>
