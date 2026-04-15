<script setup lang="ts">
const route = useRoute()
const auth = useAuth()
const navDropdownRef = ref<HTMLElement | null>(null)
const isOverflowOpen = ref(false)

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

  const authenticatedItems = [
    { label: 'Dashboard', to: '/dashboard' },
    { label: 'Pembayaran', to: '/payments' },
    { label: 'Profil', to: '/profile' },
  ]

  if (currentUser.value?.role === 'admin') {
    authenticatedItems.push(
      { label: 'Admin', to: '/admin' },
      { label: 'User Role', to: '/admin/users' },
      { label: 'Voucher', to: '/admin/vouchers' },
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

const primaryNavItems = computed(() => {
  if (!isAuthenticated.value) {
    return navItems.value
  }

  const primaryOrder = ['/', '/courses', '/dashboard', '/payments']

  return navItems.value.filter((item) => primaryOrder.includes(item.to))
})

const overflowNavItems = computed(() => {
  const primaryPaths = new Set(primaryNavItems.value.map((item) => item.to))

  return navItems.value.filter((item) => !primaryPaths.has(item.to))
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
  isOverflowOpen.value = false
  await auth.logout()
  await navigateTo('/login')
}

const avatarInitial = computed(() => {
  const name = currentUser.value?.name || ''

  return name.trim().charAt(0).toUpperCase() || 'U'
})

const hasOverflowMenu = computed(() => overflowNavItems.value.length > 0)
const isOverflowActive = computed(() => overflowNavItems.value.some((item) => isActive(item.to)))

const toggleOverflowMenu = () => {
  isOverflowOpen.value = !isOverflowOpen.value
}

const closeOverflowMenu = () => {
  isOverflowOpen.value = false
}

const onClickOutside = (event: PointerEvent) => {
  const target = event.target as Node | null

  if (!target) {
    return
  }

  if (!navDropdownRef.value?.contains(target)) {
    closeOverflowMenu()
  }
}

const onEscape = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    closeOverflowMenu()
  }
}

onMounted(() => {
  document.addEventListener('pointerdown', onClickOutside)
  document.addEventListener('keydown', onEscape)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onClickOutside)
  document.removeEventListener('keydown', onEscape)
})
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
            v-for="item in primaryNavItems"
            :key="item.to"
            :to="item.to"
            class="nav-link"
            :class="{ 'nav-link-active': isActive(item.to) }"
            @click="closeOverflowMenu()"
          >
            {{ item.label }}
          </NuxtLink>

          <div v-if="hasOverflowMenu" ref="navDropdownRef" class="nav-dropdown">
            <button
              type="button"
              class="nav-link nav-dropdown-trigger"
              :class="{ 'nav-link-active': isOverflowActive || isOverflowOpen }"
              :aria-expanded="isOverflowOpen"
              @click="toggleOverflowMenu()"
            >
              Menu
              <span class="nav-dropdown-caret" aria-hidden="true" />
            </button>

            <div v-if="isOverflowOpen" class="nav-dropdown-menu">
              <NuxtLink
                v-for="item in overflowNavItems"
                :key="item.to"
                :to="item.to"
                class="nav-dropdown-link"
                :class="{ 'nav-dropdown-link-active': isActive(item.to) }"
                @click="closeOverflowMenu()"
              >
                {{ item.label }}
              </NuxtLink>
            </div>
          </div>
        </nav>
        <div class="auth-actions">
          <template v-if="isAuthenticated">
            <span class="role-pill">{{ roleBadge }}</span>
            <NuxtLink to="/profile" class="user-chip user-link">
              <span class="avatar-circle">
                <img
                  v-if="currentUser?.avatar_url"
                  :src="currentUser.avatar_url"
                  alt="Foto profil"
                  class="avatar-circle-image"
                />
                <span v-else>{{ avatarInitial }}</span>
              </span>
              <span class="user-name">{{ currentUser?.name }}</span>
            </NuxtLink>
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
