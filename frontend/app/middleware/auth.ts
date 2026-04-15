export default defineNuxtRouteMiddleware(async () => {
  if (import.meta.server) {
    return
  }

  const auth = useAuth()
  await auth.ensureSession()

  if (!auth.isAuthenticated.value) {
    return navigateTo('/login')
  }

  if (auth.user.value?.status !== 'active') {
    await auth.logout()
    return navigateTo('/login')
  }
})
