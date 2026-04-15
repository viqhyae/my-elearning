<script setup lang="ts">
const route = useRoute()
const auth = useAuth()

await auth.ensureSession()

const isAuthenticated = computed(() => auth.isAuthenticated.value)
const currentUser = computed(() => auth.user.value)

const navItems = computed(() => {
  const baseItems = [
    { label: 'Home', to: '/' },
    { label: 'Katalog', to: '/courses' },
  ]

  if (!isAuthenticated.value) {
    return baseItems
  }

  const authenticatedItems = [{ label: 'Dashboard', to: '/dashboard' }]

  if (currentUser.value?.role === 'admin') {
    authenticatedItems.push(
      { label: 'Admin', to: '/admin' },
      { label: 'User Role', to: '/admin/users' },
      { label: 'Mentor Studio', to: '/mentor' }
    )
  } else if (currentUser.value?.role === 'mentor') {
    authenticatedItems.push(
      { label: 'Mentor Studio', to: '/mentor' },
      { label: 'Student View', to: '/student' }
    )
  } else {
    authenticatedItems.push({ label: 'Student View', to: '/student' })
  }

  return [...baseItems, ...authenticatedItems]
})

const isActive = (to: string) => {
  if (to.includes('#')) {
    const [path, hash] = to.split('#')
    const expectedPath = path || '/'

    return route.path === expectedPath && route.hash === `#${hash}`
  }

  if (to === '/') {
    return route.path === '/'
  }

  return route.path === to || route.path.startsWith(`${to}/`)
}

const roleBadge = computed(() => {
  const role = currentUser.value?.role

  if (role === 'admin') {
    return 'Admin'
  }

  if (role === 'mentor') {
    return 'Mentor'
  }

  return 'Student'
})

const handleLogout = async () => {
  await auth.logout()
  await navigateTo('/login')
}
</script>

<template>
  <div class="shell">
    <header class="topbar topbar-animated">
      <div class="container topbar-inner">
        <NuxtLink to="/" class="brand">
          <span class="brand-mark">E</span>
          <span>EduFlow LMS</span>
        </NuxtLink>
        <nav class="nav-links">
          <NuxtLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="nav-link"
            :class="{ 'nav-link-active': isActive(item.to) }"
          >
            {{ item.label }}
          </NuxtLink>
        </nav>
        <div class="auth-actions">
          <template v-if="isAuthenticated">
            <span class="role-pill">{{ roleBadge }}</span>
            <span class="user-name">{{ currentUser?.name }}</span>
            <button type="button" class="btn btn-secondary btn-small" @click="handleLogout">
              Logout
            </button>
          </template>
          <template v-else>
            <NuxtLink to="/login" class="btn btn-primary btn-small">Login</NuxtLink>
          </template>
        </div>
      </div>
    </header>

    <main>
      <slot />
    </main>
  </div>
</template>
