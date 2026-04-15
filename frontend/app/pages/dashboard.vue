<script setup lang="ts">
definePageMeta({
  middleware: ['auth'],
})

const auth = useAuth()
await auth.ensureSession()

if (process.client && !auth.isAuthenticated.value) {
  await navigateTo('/login')
}

const currentUser = computed(() => auth.user.value)

const cards = computed(() => {
  const baseCards = [
    {
      title: 'Profil Login',
      description: `${currentUser.value?.name || '-'} (${currentUser.value?.email || '-'})`,
      to: '/dashboard',
    },
  ]

  if (currentUser.value?.role === 'admin') {
    return [
      ...baseCards,
      {
        title: 'Admin Dashboard',
        description: 'Kelola metrik, course CRUD, dan role user.',
        to: '/admin',
      },
      {
        title: 'Role Management',
        description: 'Atur role serta status akun user.',
        to: '/admin/users',
      },
    ]
  }

  if (currentUser.value?.role === 'mentor') {
    return [
      ...baseCards,
      {
        title: 'Mentor Studio',
        description: 'Kelola trailer video, modul, dan lesson course.',
        to: '/mentor',
      },
      {
        title: 'Student View',
        description: 'Cek tampilan halaman pembelajar.',
        to: '/student',
      },
    ]
  }

  return [
    ...baseCards,
    {
      title: 'Student Dashboard',
      description: 'Lihat progress belajar, session, dan task.',
      to: '/student',
    },
  ]
})
</script>

<template>
  <section class="page-head">
    <div class="container">
      <p class="eyebrow">Login Dashboard</p>
      <h1 class="page-title">Selamat Datang, {{ currentUser?.name }}</h1>
      <p class="page-copy">
        Kamu login sebagai <strong>{{ currentUser?.role }}</strong>. Pilih area kerja dari kartu di bawah.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="mode-grid">
        <NuxtLink v-for="card in cards" :key="card.title" :to="card.to" class="mode-card">
          <p class="stack-title">{{ card.title }}</p>
          <p class="stack-meta">{{ card.description }}</p>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>
