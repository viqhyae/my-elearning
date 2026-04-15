export default defineNuxtRouteMiddleware(async (to) => {
  if (import.meta.server) {
    return
  }

  const auth = useAuth()
  await auth.ensureSession()

  if (!auth.user.value) {
    return navigateTo('/login')
  }

  const metaRole = to.meta.role as string | string[] | undefined

  if (!metaRole) {
    return
  }

  const roles = Array.isArray(metaRole) ? metaRole : [metaRole]

  if (!roles.includes(auth.user.value.role)) {
    return navigateTo(auth.defaultPathByRole(auth.user.value.role))
  }
})
