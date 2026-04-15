<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'role'],
  role: 'admin',
})

type AdminCourseOption = {
  id: number
  title: string
  slug: string
}

type AdminVoucher = {
  id: number
  name: string
  code: string
  promo_price: number
  promo_price_label: string
  is_active: boolean
  starts_at: string | null
  ends_at: string | null
  notes: string | null
  course_ids: number[]
  courses: AdminCourseOption[]
  updated_at: string | null
}

const runtimeConfig = useRuntimeConfig()
const apiBase = runtimeConfig.public.apiBase
const auth = useAuth()

await auth.ensureSession()

if (process.client && !auth.isAuthenticated.value) {
  await navigateTo('/login')
}

const headers = computed(() => auth.authHeaders())

const {
  data: coursesData,
  pending: coursesPending,
  error: coursesError,
  refresh: refreshCourses,
} = await useFetch<AdminCourseOption[]>('/api/admin/courses', {
  baseURL: apiBase,
  server: false,
  headers,
  default: () => [],
  transform: (rows: Array<{ id: number; title: string; slug: string }>) =>
    rows.map((row) => ({
      id: row.id,
      title: row.title,
      slug: row.slug,
    })),
})

const {
  data: vouchersData,
  pending: vouchersPending,
  error: vouchersError,
  refresh: refreshVouchers,
} = await useFetch<AdminVoucher[]>('/api/admin/vouchers', {
  baseURL: apiBase,
  server: false,
  headers,
  default: () => [],
})

const editingVoucherId = ref<number | null>(null)
const savingVoucher = ref(false)
const deletingVoucherId = ref<number | null>(null)
const voucherError = ref('')
const voucherMessage = ref('')

const voucherForm = reactive({
  name: '',
  code: '',
  promo_price: 0,
  is_active: true,
  starts_at: '',
  ends_at: '',
  notes: '',
  course_ids: [] as number[],
})

const toDateTimeLocal = (value: string | null) => {
  if (!value) {
    return ''
  }

  const date = new Date(value)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hour = String(date.getHours()).padStart(2, '0')
  const minute = String(date.getMinutes()).padStart(2, '0')

  return `${year}-${month}-${day}T${hour}:${minute}`
}

const resetVoucherForm = () => {
  editingVoucherId.value = null
  voucherForm.name = ''
  voucherForm.code = ''
  voucherForm.promo_price = 0
  voucherForm.is_active = true
  voucherForm.starts_at = ''
  voucherForm.ends_at = ''
  voucherForm.notes = ''
  voucherForm.course_ids = []
  voucherError.value = ''
}

const startEditVoucher = (voucher: AdminVoucher) => {
  editingVoucherId.value = voucher.id
  voucherForm.name = voucher.name
  voucherForm.code = voucher.code
  voucherForm.promo_price = voucher.promo_price
  voucherForm.is_active = voucher.is_active
  voucherForm.starts_at = toDateTimeLocal(voucher.starts_at)
  voucherForm.ends_at = toDateTimeLocal(voucher.ends_at)
  voucherForm.notes = voucher.notes || ''
  voucherForm.course_ids = [...voucher.course_ids]
  voucherError.value = ''
  voucherMessage.value = ''
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

const saveVoucher = async () => {
  if (!voucherForm.name.trim() || !voucherForm.code.trim()) {
    voucherError.value = 'Nama promo dan kode voucher wajib diisi.'
    return
  }

  if (!voucherForm.course_ids.length) {
    voucherError.value = 'Pilih minimal satu kelas untuk promo.'
    return
  }

  savingVoucher.value = true
  voucherError.value = ''
  voucherMessage.value = ''

  try {
    const payload = {
      name: voucherForm.name.trim(),
      code: voucherForm.code.trim().toUpperCase(),
      promo_price: Number(voucherForm.promo_price) || 0,
      is_active: voucherForm.is_active,
      starts_at: voucherForm.starts_at ? new Date(voucherForm.starts_at).toISOString() : null,
      ends_at: voucherForm.ends_at ? new Date(voucherForm.ends_at).toISOString() : null,
      notes: voucherForm.notes.trim() || null,
      course_ids: voucherForm.course_ids,
    }

    if (editingVoucherId.value) {
      await $fetch(`/api/admin/vouchers/${editingVoucherId.value}`, {
        method: 'PUT',
        baseURL: apiBase,
        headers: headers.value,
        body: payload,
      })
      voucherMessage.value = 'Voucher berhasil diperbarui.'
    } else {
      await $fetch('/api/admin/vouchers', {
        method: 'POST',
        baseURL: apiBase,
        headers: headers.value,
        body: payload,
      })
      voucherMessage.value = 'Voucher baru berhasil dibuat.'
    }

    await refreshVouchers()
    resetVoucherForm()
  } catch (error: unknown) {
    voucherError.value = extractError(error, 'Gagal menyimpan voucher.')
  } finally {
    savingVoucher.value = false
  }
}

const removeVoucher = async (voucher: AdminVoucher) => {
  if (process.client) {
    const confirmed = window.confirm(`Hapus voucher "${voucher.name}"?`)

    if (!confirmed) {
      return
    }
  }

  deletingVoucherId.value = voucher.id
  voucherError.value = ''
  voucherMessage.value = ''

  try {
    await $fetch(`/api/admin/vouchers/${voucher.id}`, {
      method: 'DELETE',
      baseURL: apiBase,
      headers: headers.value,
    })

    if (editingVoucherId.value === voucher.id) {
      resetVoucherForm()
    }

    voucherMessage.value = 'Voucher berhasil dihapus.'
    await refreshVouchers()
  } catch (error: unknown) {
    voucherError.value = extractError(error, 'Gagal menghapus voucher.')
  } finally {
    deletingVoucherId.value = null
  }
}

const formatDateTime = (value: string | null) => {
  if (!value) {
    return '-'
  }

  return new Date(value).toLocaleString('id-ID')
}

const isCourseChecked = (courseId: number) => voucherForm.course_ids.includes(courseId)

const toggleCourse = (courseId: number) => {
  if (isCourseChecked(courseId)) {
    voucherForm.course_ids = voucherForm.course_ids.filter((id) => id !== courseId)
    return
  }

  voucherForm.course_ids = [...voucherForm.course_ids, courseId]
}
</script>

<template>
  <section class="page-head page-head-admin">
    <div class="container">
      <p class="eyebrow">Back End - Voucher Promo</p>
      <h1 class="page-title">Manajemen Voucher Kelas</h1>
      <p class="page-copy">
        Admin bisa membuat promo berdasarkan kelas tertentu dan menentukan harga promo final.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <article class="panel-card">
        <div class="split-row">
          <h2>{{ editingVoucherId ? 'Edit Voucher' : 'Buat Voucher Baru' }}</h2>
          <button type="button" class="btn btn-secondary btn-small" @click="resetVoucherForm()">Reset form</button>
        </div>

        <div class="form-grid">
          <label class="form-field">
            <span>Nama Promo</span>
            <input v-model="voucherForm.name" type="text" placeholder="Promo Ramadhan" />
          </label>
          <label class="form-field">
            <span>Kode Voucher</span>
            <input v-model="voucherForm.code" type="text" placeholder="HEMAT50K" />
          </label>
          <label class="form-field">
            <span>Harga Promo (IDR)</span>
            <input v-model.number="voucherForm.promo_price" type="number" min="0" step="1000" />
          </label>
          <label class="form-field">
            <span>Status</span>
            <select v-model="voucherForm.is_active">
              <option :value="true">Aktif</option>
              <option :value="false">Nonaktif</option>
            </select>
          </label>
          <label class="form-field">
            <span>Mulai Promo</span>
            <input v-model="voucherForm.starts_at" type="datetime-local" />
          </label>
          <label class="form-field">
            <span>Akhir Promo</span>
            <input v-model="voucherForm.ends_at" type="datetime-local" />
          </label>
          <label class="form-field form-field-full">
            <span>Catatan</span>
            <textarea v-model="voucherForm.notes" rows="2" placeholder="Catatan internal promo" />
          </label>
        </div>

        <h2 class="section-spacer-sm">Pilih Kelas Promo</h2>
        <p v-if="coursesPending" class="status-meta">Memuat daftar kelas...</p>
        <p v-else-if="coursesError" class="status-meta status-error">
          Gagal memuat daftar kelas.
          <button type="button" class="btn btn-secondary btn-small" @click="refreshCourses()">Coba lagi</button>
        </p>
        <div v-else class="voucher-course-grid">
          <button
            v-for="course in coursesData"
            :key="course.id"
            type="button"
            class="voucher-course-item"
            :class="{ 'is-selected': isCourseChecked(course.id) }"
            @click="toggleCourse(course.id)"
          >
            <p class="stack-title">{{ course.title }}</p>
            <p class="stack-meta">{{ course.slug }}</p>
          </button>
        </div>

        <p v-if="voucherError" class="status-meta status-error">{{ voucherError }}</p>
        <p v-if="voucherMessage" class="status-meta">{{ voucherMessage }}</p>

        <div class="form-actions">
          <button type="button" class="btn btn-primary" :disabled="savingVoucher" @click="saveVoucher()">
            {{ savingVoucher ? 'Menyimpan...' : editingVoucherId ? 'Update Voucher' : 'Tambah Voucher' }}
          </button>
        </div>
      </article>

      <article class="panel-card section-spacer">
        <div class="split-row">
          <h2>Daftar Voucher</h2>
          <button type="button" class="btn btn-secondary btn-small" @click="refreshVouchers()">Refresh</button>
        </div>
        <p v-if="vouchersPending" class="status-meta">Memuat voucher...</p>
        <p v-else-if="vouchersError" class="status-meta status-error">Gagal memuat voucher.</p>
        <p v-else-if="!vouchersData.length" class="empty-state">Belum ada voucher promo.</p>

        <div v-else class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Nama Promo</th>
                <th>Kode</th>
                <th>Harga Promo</th>
                <th>Kelas Promo</th>
                <th>Periode</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="voucher in vouchersData" :key="voucher.id">
                <td>{{ voucher.name }}</td>
                <td>{{ voucher.code }}</td>
                <td>{{ voucher.promo_price_label }}</td>
                <td>
                  <p class="stack-meta">
                    {{ voucher.courses.map((course) => course.title).join(', ') || '-' }}
                  </p>
                </td>
                <td>
                  <p class="stack-meta">Mulai: {{ formatDateTime(voucher.starts_at) }}</p>
                  <p class="stack-meta">Akhir: {{ formatDateTime(voucher.ends_at) }}</p>
                </td>
                <td>
                  <span class="tag" :class="voucher.is_active ? 'tag-success' : 'tag-warning'">
                    {{ voucher.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td>
                  <div class="table-actions">
                    <button type="button" class="btn btn-secondary btn-small" @click="startEditVoucher(voucher)">
                      Edit
                    </button>
                    <button
                      type="button"
                      class="btn btn-danger btn-small"
                      :disabled="deletingVoucherId === voucher.id"
                      @click="removeVoucher(voucher)"
                    >
                      Hapus
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </div>
  </section>
</template>
