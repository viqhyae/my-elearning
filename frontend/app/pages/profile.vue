<script setup lang="ts">
definePageMeta({
  middleware: ['auth'],
})

type ProfileUpdateResponse = {
  message: string
  user: {
    id: number
    name: string
    email: string
    role: 'admin' | 'mentor' | 'student'
    status: 'active' | 'inactive'
    avatar_url: string | null
  }
}

type PasswordUpdateResponse = {
  message: string
}

const auth = useAuth()
const runtimeConfig = useRuntimeConfig()
const apiBase = runtimeConfig.public.apiBase

await auth.ensureSession()

if (auth.isAuthenticated.value) {
  try {
    await auth.fetchMe()
  } catch {
    // auth middleware will redirect on next navigation when needed
  }
}

if (process.client && !auth.isAuthenticated.value) {
  await navigateTo('/login')
}

const headers = computed(() => auth.authHeaders())
const currentUser = computed(() => auth.user.value)
const profileMessage = ref('')
const profileError = ref('')
const passwordMessage = ref('')
const passwordError = ref('')
const savingProfile = ref(false)
const savingPassword = ref(false)

const profileForm = reactive({
  name: '',
  avatar_url: '',
})

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const avatarPresets = [
  '/images/avatar-alya.svg',
  '/images/avatar-bima.svg',
  '/images/avatar-citra.svg',
  '/images/hero-learning.svg',
]

const roleLabel = computed(() => {
  if (currentUser.value?.role === 'admin') {
    return 'Admin'
  }

  if (currentUser.value?.role === 'mentor') {
    return 'Mentor'
  }

  return 'Student'
})

const defaultWorkspace = computed(() => {
  if (currentUser.value?.role === 'admin') {
    return '/admin'
  }

  if (currentUser.value?.role === 'mentor') {
    return '/mentor'
  }

  return '/student'
})

const avatarInitial = computed(() => {
  const name = currentUser.value?.name || ''

  return name.trim().charAt(0).toUpperCase() || 'U'
})

watch(
  currentUser,
  (user) => {
    profileForm.name = user?.name || ''
    profileForm.avatar_url = user?.avatar_url || ''
  },
  { immediate: true }
)

const pickAvatar = (url: string) => {
  profileForm.avatar_url = url
}

const clearAvatar = () => {
  profileForm.avatar_url = ''
}

const extractError = (error: unknown, fallback: string) => {
  const data = (error as { data?: { message?: string; errors?: Record<string, string[]> } })?.data

  if (data?.errors) {
    const firstKey = Object.keys(data.errors)[0]
    const firstMessage = firstKey ? data.errors[firstKey]?.[0] : ''

    if (firstMessage) {
      return firstMessage
    }
  }

  return data?.message || fallback
}

const saveProfile = async () => {
  if (!profileForm.name.trim()) {
    profileError.value = 'Nama wajib diisi.'
    return
  }

  savingProfile.value = true
  profileError.value = ''
  profileMessage.value = ''

  try {
    await $fetch<ProfileUpdateResponse>('/api/me/profile', {
      method: 'PATCH',
      baseURL: apiBase,
      headers: headers.value,
      body: {
        name: profileForm.name.trim(),
        avatar_url: profileForm.avatar_url.trim() || null,
      },
    })

    await auth.fetchMe()
    profileMessage.value = 'Profil berhasil disimpan.'
  } catch (error: unknown) {
    profileError.value = extractError(error, 'Gagal menyimpan profil.')
  } finally {
    savingProfile.value = false
  }
}

const savePassword = async () => {
  if (!passwordForm.current_password || !passwordForm.password || !passwordForm.password_confirmation) {
    passwordError.value = 'Semua kolom password wajib diisi.'
    return
  }

  savingPassword.value = true
  passwordError.value = ''
  passwordMessage.value = ''

  try {
    const response = await $fetch<PasswordUpdateResponse>('/api/me/password', {
      method: 'POST',
      baseURL: apiBase,
      headers: headers.value,
      body: {
        current_password: passwordForm.current_password,
        password: passwordForm.password,
        password_confirmation: passwordForm.password_confirmation,
      },
    })

    passwordMessage.value = response.message
    await auth.clearSession()
    await navigateTo('/login')
  } catch (error: unknown) {
    passwordError.value = extractError(error, 'Gagal mengubah password.')
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <section class="page-head">
    <div class="container">
      <p class="eyebrow">Profil Login</p>
      <h1 class="page-title">Akun Saya</h1>
      <p class="page-copy">
        Halaman ini dibuat sederhana: ubah foto, ubah nama, dan ganti password dari satu tempat.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="panel-grid profile-grid">
        <article class="panel-card profile-summary">
          <h2>Informasi Akun</h2>
          <div class="profile-avatar-wrap">
            <span class="profile-avatar-preview">
              <img
                v-if="profileForm.avatar_url"
                :src="profileForm.avatar_url"
                alt="Foto profil"
                class="profile-avatar-image"
              />
              <span v-else>{{ avatarInitial }}</span>
            </span>
            <div>
              <p class="stack-title">{{ currentUser?.name || '-' }}</p>
              <p class="stack-meta">{{ currentUser?.email || '-' }}</p>
            </div>
          </div>

          <p class="status-meta section-spacer-sm">Gunakan foto preset cepat:</p>
          <div class="profile-avatar-list">
            <button
              v-for="avatar in avatarPresets"
              :key="avatar"
              type="button"
              class="profile-avatar-option"
              @click="pickAvatar(avatar)"
            >
              <img :src="avatar" alt="Preset avatar" class="profile-avatar-option-image" />
            </button>
            <button type="button" class="btn btn-secondary btn-small" @click="clearAvatar()">Hapus foto</button>
          </div>

          <p class="status-meta">Nama</p>
          <p class="stack-title">{{ currentUser?.name || '-' }}</p>

          <p class="status-meta section-spacer-sm">Email</p>
          <p class="stack-title">{{ currentUser?.email || '-' }}</p>

          <div class="split-row section-spacer-sm">
            <span class="tag">{{ roleLabel }}</span>
            <span class="tag" :class="currentUser?.status === 'active' ? 'tag-success' : 'tag-warning'">
              {{ currentUser?.status || 'unknown' }}
            </span>
          </div>
        </article>

        <article class="panel-card">
          <h2>Ubah Profil</h2>
          <p class="stack-meta">Isi data di bawah, lalu klik Simpan Profil.</p>
          <div class="form-grid">
            <label class="form-field form-field-full">
              <span>Nama lengkap</span>
              <input v-model="profileForm.name" type="text" placeholder="Nama lengkap" />
            </label>
            <label class="form-field form-field-full">
              <span>URL Foto Profil</span>
              <input
                v-model="profileForm.avatar_url"
                type="text"
                placeholder="/images/avatar-alya.svg atau https://..."
              />
            </label>
          </div>
          <p v-if="profileError" class="status-meta status-error">{{ profileError }}</p>
          <p v-if="profileMessage" class="status-meta">{{ profileMessage }}</p>
          <div class="form-actions">
            <button type="button" class="btn btn-primary" :disabled="savingProfile" @click="saveProfile()">
              {{ savingProfile ? 'Menyimpan...' : 'Simpan Profil' }}
            </button>
          </div>
        </article>

        <article class="panel-card">
          <h2>Ganti Password</h2>
          <p class="stack-meta">Masukkan password saat ini dan password baru.</p>
          <div class="form-grid">
            <label class="form-field form-field-full">
              <span>Password saat ini</span>
              <input v-model="passwordForm.current_password" type="password" placeholder="Password saat ini" />
            </label>
            <label class="form-field">
              <span>Password baru</span>
              <input v-model="passwordForm.password" type="password" placeholder="Minimal 8 karakter" />
            </label>
            <label class="form-field">
              <span>Konfirmasi password baru</span>
              <input v-model="passwordForm.password_confirmation" type="password" placeholder="Ulangi password baru" />
            </label>
          </div>
          <p v-if="passwordError" class="status-meta status-error">{{ passwordError }}</p>
          <p v-if="passwordMessage" class="status-meta">{{ passwordMessage }}</p>
          <div class="form-actions">
            <button type="button" class="btn btn-primary" :disabled="savingPassword" @click="savePassword()">
              {{ savingPassword ? 'Memproses...' : 'Ganti Password' }}
            </button>
          </div>
        </article>

        <article class="panel-card">
          <h2>Navigasi Cepat</h2>
          <p class="stack-meta">Jika bingung mulai dari mana, gunakan tombol ini.</p>
          <div class="form-actions">
            <NuxtLink to="/dashboard" class="btn btn-secondary">Dashboard</NuxtLink>
            <NuxtLink :to="defaultWorkspace" class="btn btn-primary">Workspace</NuxtLink>
            <NuxtLink to="/payments" class="btn btn-secondary">Pembayaran</NuxtLink>
          </div>

          <p class="status-meta section-spacer">
            Tips: setelah ganti password, sistem otomatis meminta login ulang demi keamanan.
          </p>
        </article>
      </div>
    </div>
  </section>
</template>
