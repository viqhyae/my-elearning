<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'role'],
  role: 'admin',
})

type AdminUser = {
  id: number
  name: string
  email: string
  role: 'admin' | 'mentor' | 'student'
  status: 'active' | 'inactive'
  created_at: string
}

const runtimeConfig = useRuntimeConfig()
const apiBase = runtimeConfig.public.apiBase
const auth = useAuth()

await auth.ensureSession()

if (process.client && !auth.isAuthenticated.value) {
  await navigateTo('/login')
}

const headers = computed(() => auth.authHeaders())

const { data, pending, error, refresh } = await useFetch<AdminUser[]>('/api/admin/users', {
  baseURL: apiBase,
  server: false,
  headers,
  default: () => [],
})

const drafts = ref<Record<number, { role: 'admin' | 'mentor' | 'student'; status: 'active' | 'inactive' }>>({})
const saving = ref<Record<number, boolean>>({})
const resetSaving = ref<Record<number, boolean>>({})
const resetDrafts = ref<Record<number, string>>({})
const creating = ref(false)
const message = ref('')
const createMessage = ref('')

const createForm = reactive({
  name: '',
  email: '',
  role: 'student' as 'admin' | 'mentor' | 'student',
  status: 'active' as 'active' | 'inactive',
  password: 'password123',
})

watch(
  data,
  (rows) => {
    const next: Record<number, { role: 'admin' | 'mentor' | 'student'; status: 'active' | 'inactive' }> = {}
    const resetNext = { ...resetDrafts.value }

    for (const row of rows || []) {
      next[row.id] = {
        role: row.role,
        status: row.status,
      }

      if (!resetNext[row.id]) {
        resetNext[row.id] = 'password123'
      }
    }

    drafts.value = next
    resetDrafts.value = resetNext
  },
  { immediate: true }
)

const save = async (row: AdminUser) => {
  const draft = drafts.value[row.id]

  if (!draft) {
    return
  }

  saving.value[row.id] = true
  message.value = ''

  try {
    await $fetch(`/api/admin/users/${row.id}/role`, {
      method: 'PATCH',
      baseURL: apiBase,
      headers: headers.value,
      body: draft,
    })
    await refresh()
    message.value = `User ${row.name} berhasil diperbarui.`
  } catch (err: unknown) {
    const info = (err as { data?: { message?: string } })?.data?.message
    message.value = info || 'Gagal memperbarui role user.'
  } finally {
    saving.value[row.id] = false
  }
}

const createUser = async () => {
  if (!createForm.name.trim() || !createForm.email.trim()) {
    createMessage.value = 'Nama dan email wajib diisi.'
    return
  }

  creating.value = true
  createMessage.value = ''

  try {
    const response = await $fetch<{ password: string }>('/api/admin/users', {
      method: 'POST',
      baseURL: apiBase,
      headers: headers.value,
      body: {
        name: createForm.name,
        email: createForm.email,
        role: createForm.role,
        status: createForm.status,
        password: createForm.password || null,
      },
    })

    createMessage.value = `User berhasil dibuat. Password: ${response.password}`
    createForm.name = ''
    createForm.email = ''
    createForm.role = 'student'
    createForm.status = 'active'
    createForm.password = 'password123'
    await refresh()
  } catch (err: unknown) {
    const info = (err as { data?: { message?: string } })?.data?.message
    createMessage.value = info || 'Gagal membuat user.'
  } finally {
    creating.value = false
  }
}

const resetPassword = async (row: AdminUser) => {
  const password = resetDrafts.value[row.id]

  if (!password || password.length < 8) {
    message.value = 'Password minimal 8 karakter.'
    return
  }

  resetSaving.value[row.id] = true
  message.value = ''

  try {
    await $fetch(`/api/admin/users/${row.id}/reset-password`, {
      method: 'POST',
      baseURL: apiBase,
      headers: headers.value,
      body: {
        password,
      },
    })

    message.value = `Password user ${row.name} berhasil direset.`
  } catch (err: unknown) {
    const info = (err as { data?: { message?: string } })?.data?.message
    message.value = info || 'Gagal reset password.'
  } finally {
    resetSaving.value[row.id] = false
  }
}

const ensureDraft = (row: AdminUser) => {
  if (!drafts.value[row.id]) {
    drafts.value[row.id] = {
      role: row.role,
      status: row.status,
    }
  }

  return drafts.value[row.id]
}
</script>

<template>
  <section class="page-head page-head-admin">
    <div class="container">
      <p class="eyebrow">Back End - User Role</p>
      <h1 class="page-title">Manajemen Role User</h1>
      <p class="page-copy">
        Atur role dan status user untuk akses dashboard student/admin.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <article class="panel-card section-spacer-sm">
        <div class="split-row">
          <h2>Register User Baru</h2>
          <button type="button" class="btn btn-secondary btn-small" @click="refresh()">Refresh user</button>
        </div>
        <div class="form-grid">
          <label class="form-field">
            <span>Nama</span>
            <input v-model="createForm.name" type="text" placeholder="Nama user" />
          </label>
          <label class="form-field">
            <span>Email</span>
            <input v-model="createForm.email" type="email" placeholder="user@elearning.local" />
          </label>
          <label class="form-field">
            <span>Role</span>
            <select v-model="createForm.role">
              <option value="admin">admin</option>
              <option value="mentor">mentor</option>
              <option value="student">student</option>
            </select>
          </label>
          <label class="form-field">
            <span>Status</span>
            <select v-model="createForm.status">
              <option value="active">active</option>
              <option value="inactive">inactive</option>
            </select>
          </label>
          <label class="form-field form-field-full">
            <span>Password awal</span>
            <input v-model="createForm.password" type="text" placeholder="password123" />
          </label>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-primary" :disabled="creating" @click="createUser()">
            Buat User
          </button>
        </div>
        <p v-if="createMessage" class="status-meta">{{ createMessage }}</p>
      </article>

      <article class="panel-card">
        <div class="split-row">
          <h2>Daftar User</h2>
          <button type="button" class="btn btn-secondary" @click="refresh()">Refresh</button>
        </div>
        <p v-if="pending" class="status-meta">Memuat user...</p>
        <p v-else-if="error" class="status-meta status-error">Gagal memuat user.</p>
        <p v-if="message" class="status-meta">{{ message }}</p>

        <div class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Reset Password</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in data" :key="row.id">
                <td>{{ row.name }}</td>
                <td>{{ row.email }}</td>
                <td>
                  <select v-model="ensureDraft(row).role" class="table-select">
                    <option value="admin">admin</option>
                    <option value="mentor">mentor</option>
                    <option value="student">student</option>
                  </select>
                </td>
                <td>
                  <select v-model="ensureDraft(row).status" class="table-select">
                    <option value="active">active</option>
                    <option value="inactive">inactive</option>
                  </select>
                </td>
                <td>
                  <div class="table-actions">
                    <input
                      v-model="resetDrafts[row.id]"
                      type="text"
                      class="table-input"
                      placeholder="password baru"
                    />
                    <button
                      type="button"
                      class="btn btn-secondary btn-small"
                      :disabled="resetSaving[row.id]"
                      @click="resetPassword(row)"
                    >
                      Reset
                    </button>
                  </div>
                </td>
                <td>
                  <button
                    type="button"
                    class="btn btn-primary btn-small"
                    :disabled="saving[row.id]"
                    @click="save(row)"
                  >
                    Simpan
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </div>
  </section>
</template>
