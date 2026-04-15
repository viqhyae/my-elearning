<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'role'],
  role: 'admin',
})

type AdminSummary = {
  total_students: number
  active_instructors: number
  published_courses: number
  completion_rate_avg: number
}

type TrendData = {
  labels: string[]
  values: number[]
}

type CourseStatus = {
  label: string
  value: number
}

type RecentEnrollment = {
  student_name: string
  course_title: string
  enrolled_at: string
  avatar_image: string
}

type PendingReview = {
  item: string
  owner: string
  submitted_at: string
}

type AdminDashboardResponse = {
  hero_image: string
  summary: AdminSummary
  enrollment_trend: TrendData
  course_status: CourseStatus[]
  recent_enrollments: RecentEnrollment[]
  pending_reviews: PendingReview[]
}

type AdminCourse = {
  id: number
  title: string
  slug: string
  description: string | null
  level: string
  duration_weeks: number | null
  category: string | null
  price_amount: number
  currency: string
  price_label: string
  status: string
  is_published: boolean
  image_url: string
  enrolled_students: number
  completion_rate: number
  last_updated: string | null
}

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

const {
  data: dashboardData,
  pending: dashboardPending,
  error: dashboardError,
  refresh: refreshDashboard,
} = await useFetch<AdminDashboardResponse>('/api/admin/dashboard', {
  baseURL: apiBase,
  server: false,
  headers,
})

const {
  data: coursesData,
  pending: coursesPending,
  error: coursesError,
  refresh: refreshCourses,
} = await useFetch<AdminCourse[]>('/api/admin/courses', {
  baseURL: apiBase,
  server: false,
  headers,
  default: () => [],
})

const {
  data: usersData,
  pending: usersPending,
  error: usersError,
  refresh: refreshUsers,
} = await useFetch<AdminUser[]>('/api/admin/users', {
  baseURL: apiBase,
  server: false,
  headers,
  default: () => [],
})

const maxTrend = computed(() => {
  const values = dashboardData.value?.enrollment_trend.values || []
  return values.length ? Math.max(...values) : 1
})

const summaryCards = computed(() => {
  if (!dashboardData.value) {
    return []
  }

  return [
    { label: 'Total Student', value: dashboardData.value.summary.total_students },
    { label: 'Instructor Aktif', value: dashboardData.value.summary.active_instructors },
    { label: 'Course Published', value: dashboardData.value.summary.published_courses },
    { label: 'Avg Completion', value: `${dashboardData.value.summary.completion_rate_avg}%` },
  ]
})

const courseForm = reactive({
  title: '',
  description: '',
  level: 'Pemula',
  duration_weeks: 4,
  category: 'Productivity',
  price_amount: 299000,
  currency: 'IDR',
  is_published: true,
  image_url: '/images/course-project.svg',
})

const isCourseSubmitting = ref(false)
const editingCourseId = ref<number | null>(null)
const deletingCourseId = ref<number | null>(null)
const courseFormError = ref('')

const userDrafts = ref<Record<number, { role: 'admin' | 'mentor' | 'student'; status: 'active' | 'inactive' }>>({})
const isUserSaving = ref<Record<number, boolean>>({})
const userSaveMessage = ref('')

watch(
  usersData,
  (rows) => {
    const nextDrafts: Record<number, { role: 'admin' | 'mentor' | 'student'; status: 'active' | 'inactive' }> = {}

    for (const row of rows || []) {
      nextDrafts[row.id] = {
        role: row.role,
        status: row.status,
      }
    }

    userDrafts.value = nextDrafts
  },
  { immediate: true }
)

const resetCourseForm = () => {
  editingCourseId.value = null
  courseForm.title = ''
  courseForm.description = ''
  courseForm.level = 'Pemula'
  courseForm.duration_weeks = 4
  courseForm.category = 'Productivity'
  courseForm.price_amount = 299000
  courseForm.currency = 'IDR'
  courseForm.is_published = true
  courseForm.image_url = '/images/course-project.svg'
  courseFormError.value = ''
}

const startEditCourse = (course: AdminCourse) => {
  editingCourseId.value = course.id
  courseForm.title = course.title
  courseForm.description = course.description || ''
  courseForm.level = course.level
  courseForm.duration_weeks = course.duration_weeks || 1
  courseForm.category = course.category || ''
  courseForm.price_amount = course.price_amount || 0
  courseForm.currency = course.currency || 'IDR'
  courseForm.is_published = course.is_published
  courseForm.image_url = course.image_url
  courseFormError.value = ''
}

const submitCourse = async () => {
  if (!courseForm.title.trim()) {
    courseFormError.value = 'Judul course wajib diisi.'
    return
  }

  isCourseSubmitting.value = true
  courseFormError.value = ''

  try {
    const payload = {
      title: courseForm.title,
      description: courseForm.description || null,
      level: courseForm.level,
      duration_weeks: Number(courseForm.duration_weeks) || null,
      category: courseForm.category || null,
      price_amount: Number(courseForm.price_amount) || 0,
      currency: courseForm.currency || 'IDR',
      is_published: courseForm.is_published,
      image_url: courseForm.image_url,
    }

    if (editingCourseId.value) {
      await $fetch(`/api/admin/courses/${editingCourseId.value}`, {
        method: 'PUT',
        baseURL: apiBase,
        headers: headers.value,
        body: payload,
      })
    } else {
      await $fetch('/api/admin/courses', {
        method: 'POST',
        baseURL: apiBase,
        headers: headers.value,
        body: payload,
      })
    }

    await refreshCourses()
    await refreshDashboard()
    resetCourseForm()
  } catch (error: unknown) {
    const message = (error as { data?: { message?: string } })?.data?.message
    courseFormError.value = message || 'Gagal menyimpan course.'
  } finally {
    isCourseSubmitting.value = false
  }
}

const removeCourse = async (course: AdminCourse) => {
  if (process.client) {
    const ok = window.confirm(`Hapus course "${course.title}"?`)

    if (!ok) {
      return
    }
  }

  deletingCourseId.value = course.id

  try {
    await $fetch(`/api/admin/courses/${course.id}`, {
      method: 'DELETE',
      baseURL: apiBase,
      headers: headers.value,
    })

    await refreshCourses()
    await refreshDashboard()

    if (editingCourseId.value === course.id) {
      resetCourseForm()
    }
  } finally {
    deletingCourseId.value = null
  }
}

const saveUserRole = async (user: AdminUser) => {
  const draft = userDrafts.value[user.id]

  if (!draft) {
    return
  }

  isUserSaving.value[user.id] = true
  userSaveMessage.value = ''

  try {
    await $fetch(`/api/admin/users/${user.id}/role`, {
      method: 'PATCH',
      baseURL: apiBase,
      headers: headers.value,
      body: {
        role: draft.role,
        status: draft.status,
      },
    })

    userSaveMessage.value = `Role user ${user.name} berhasil diperbarui.`
    await refreshUsers()
  } catch (error: unknown) {
    const message = (error as { data?: { message?: string } })?.data?.message
    userSaveMessage.value = message || 'Gagal update role user.'
  } finally {
    isUserSaving.value[user.id] = false
  }
}

const ensureUserDraft = (row: AdminUser) => {
  if (!userDrafts.value[row.id]) {
    userDrafts.value[row.id] = {
      role: row.role,
      status: row.status,
    }
  }

  return userDrafts.value[row.id]
}

const formatDateTime = (value: string) => new Date(value).toLocaleString('id-ID')
</script>

<template>
  <section class="page-head page-head-admin">
    <div class="container">
      <p class="eyebrow">Back End - Admin View</p>
      <h1 class="page-title">Dashboard Operasional LMS</h1>
      <p class="page-copy">
        Tampilan backend untuk admin memonitor enrollment, kualitas konten, mengelola course,
        dan mengatur role user.
      </p>
      <img
        v-if="dashboardData?.hero_image"
        :src="dashboardData.hero_image"
        alt="Visual dashboard admin"
        class="dashboard-hero"
        loading="lazy"
      />
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div v-if="dashboardPending" class="notice-card">Memuat dashboard admin...</div>
      <div v-else-if="dashboardError" class="notice-card notice-error">
        Gagal memuat dashboard admin.
        <button type="button" class="btn btn-secondary" @click="refreshDashboard()">Refresh</button>
      </div>

      <template v-else>
        <div class="stats-grid">
          <article v-for="item in summaryCards" :key="item.label" class="stat-card stat-card-admin">
            <p class="stat-label">{{ item.label }}</p>
            <p class="stat-value">{{ item.value }}</p>
          </article>
        </div>

        <div class="panel-grid">
          <article class="panel-card">
            <h2>Trend Enrollment Mingguan</h2>
            <div class="trend-bars">
              <div
                v-for="(value, index) in dashboardData?.enrollment_trend.values"
                :key="dashboardData?.enrollment_trend.labels[index]"
                class="trend-item"
              >
                <p class="trend-label">{{ dashboardData?.enrollment_trend.labels[index] }}</p>
                <div class="trend-track">
                  <div class="trend-fill" :style="{ width: `${(value / maxTrend) * 100}%` }" />
                </div>
                <p class="trend-value">{{ value }}</p>
              </div>
              <p v-if="!dashboardData?.enrollment_trend.values.length" class="empty-state">
                Data trend enrollment belum tersedia.
              </p>
            </div>
          </article>

          <article class="panel-card">
            <h2>Status Konten</h2>
            <div class="stack-list">
              <div v-for="status in dashboardData?.course_status" :key="status.label" class="stack-row">
                <div class="split-row">
                  <p class="stack-title">{{ status.label }}</p>
                  <p class="stack-percent">{{ status.value }}</p>
                </div>
              </div>
              <p v-if="!dashboardData?.course_status.length" class="empty-state">
                Status konten belum tersedia.
              </p>
            </div>
          </article>
        </div>

        <div class="panel-grid">
          <article class="panel-card">
            <h2>Recent Enrollment</h2>
            <div class="stack-list">
              <div
                v-for="item in dashboardData?.recent_enrollments"
                :key="item.student_name + item.course_title"
                class="stack-row"
              >
                <div class="enrollment-row">
                  <img :src="item.avatar_image" :alt="item.student_name" class="avatar-mini" loading="lazy" />
                  <div>
                    <p class="stack-title">{{ item.student_name }}</p>
                    <p class="stack-meta">{{ item.course_title }}</p>
                    <p class="stack-meta">{{ formatDateTime(item.enrolled_at) }}</p>
                  </div>
                </div>
              </div>
              <p v-if="!dashboardData?.recent_enrollments.length" class="empty-state">
                Belum ada data enrollment terbaru.
              </p>
            </div>
          </article>

          <article class="panel-card">
            <h2>Pending Review</h2>
            <div class="stack-list">
              <div
                v-for="item in dashboardData?.pending_reviews"
                :key="item.item + item.owner"
                class="stack-row"
              >
                <p class="stack-title">{{ item.item }}</p>
                <p class="stack-meta">Owner: {{ item.owner }}</p>
                <p class="stack-meta">{{ formatDateTime(item.submitted_at) }}</p>
              </div>
              <p v-if="!dashboardData?.pending_reviews.length" class="empty-state">
                Tidak ada antrian review saat ini.
              </p>
            </div>
          </article>
        </div>

        <article class="panel-card">
          <div class="split-row">
            <h2>CRUD Course</h2>
            <button type="button" class="btn btn-secondary" @click="resetCourseForm">Reset form</button>
          </div>

          <div class="form-grid">
            <label class="form-field">
              <span>Judul</span>
              <input v-model="courseForm.title" type="text" placeholder="Nama course" />
            </label>
            <label class="form-field">
              <span>Level</span>
              <select v-model="courseForm.level">
                <option>Pemula</option>
                <option>Menengah</option>
                <option>Lanjutan</option>
              </select>
            </label>
            <label class="form-field">
              <span>Durasi (minggu)</span>
              <input v-model.number="courseForm.duration_weeks" type="number" min="1" max="52" />
            </label>
            <label class="form-field">
              <span>Kategori</span>
              <input v-model="courseForm.category" type="text" placeholder="Analytics" />
            </label>
            <label class="form-field">
              <span>Harga (IDR)</span>
              <input v-model.number="courseForm.price_amount" type="number" min="0" step="1000" />
            </label>
            <label class="form-field">
              <span>Mata uang</span>
              <select v-model="courseForm.currency">
                <option value="IDR">IDR</option>
              </select>
            </label>
            <label class="form-field">
              <span>Gambar Dummy</span>
              <select v-model="courseForm.image_url">
                <option value="/images/course-project.svg">Project</option>
                <option value="/images/course-communication.svg">Communication</option>
                <option value="/images/course-data.svg">Data</option>
              </select>
            </label>
            <label class="form-field form-field-checkbox">
              <input v-model="courseForm.is_published" type="checkbox" />
              <span>Published</span>
            </label>
            <label class="form-field form-field-full">
              <span>Deskripsi</span>
              <textarea v-model="courseForm.description" rows="3" placeholder="Deskripsi course" />
            </label>
          </div>

          <p v-if="courseFormError" class="status-meta status-error">{{ courseFormError }}</p>

          <div class="form-actions">
            <button type="button" class="btn btn-primary" :disabled="isCourseSubmitting" @click="submitCourse()">
              {{ editingCourseId ? 'Update Course' : 'Tambah Course' }}
            </button>
          </div>

          <div class="split-row section-spacer-sm">
            <h2>Daftar Course</h2>
            <button type="button" class="btn btn-secondary" @click="refreshCourses()">Refresh list</button>
          </div>

          <p v-if="coursesPending" class="status-meta">Memuat data course...</p>
          <p v-else-if="coursesError" class="status-meta status-error">Data course gagal dimuat.</p>
          <p v-else-if="!coursesData.length" class="empty-state">
            Belum ada course. Tambah course baru dari form di atas.
          </p>

          <div v-else class="table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Course</th>
                  <th>Level</th>
                  <th>Status</th>
                  <th>Harga</th>
                  <th>Students</th>
                  <th>Completion</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="course in coursesData" :key="course.id">
                  <td>
                    <div class="course-cell">
                      <img :src="course.image_url" :alt="course.title" class="table-thumb" loading="lazy" />
                      <div>
                        <p class="table-title">{{ course.title }}</p>
                        <p class="stack-meta">{{ course.category || '-' }}</p>
                      </div>
                    </div>
                  </td>
                  <td>{{ course.level }}</td>
                  <td>
                    <span class="tag" :class="{ 'tag-success': course.status === 'Published' }">
                      {{ course.status }}
                    </span>
                  </td>
                  <td>{{ course.price_label }}</td>
                  <td>{{ course.enrolled_students }}</td>
                  <td>{{ course.completion_rate }}%</td>
                  <td>
                    <div class="table-actions">
                      <button type="button" class="btn btn-secondary btn-small" @click="startEditCourse(course)">
                        Edit
                      </button>
                      <button
                        type="button"
                        class="btn btn-danger btn-small"
                        :disabled="deletingCourseId === course.id"
                        @click="removeCourse(course)"
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

        <article class="panel-card section-spacer">
          <div class="split-row">
            <h2>Role Management</h2>
            <button type="button" class="btn btn-secondary" @click="refreshUsers()">Refresh users</button>
          </div>

          <p v-if="usersPending" class="status-meta">Memuat data user...</p>
          <p v-else-if="usersError" class="status-meta status-error">Data user gagal dimuat.</p>
          <p v-if="userSaveMessage" class="status-meta">{{ userSaveMessage }}</p>
          <p v-if="!usersPending && !usersError && !usersData.length" class="empty-state">
            Belum ada data user yang bisa dikelola.
          </p>

          <div v-if="usersData.length" class="table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Nama</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in usersData" :key="row.id">
                  <td>{{ row.name }}</td>
                  <td>{{ row.email }}</td>
                  <td>
                    <select v-model="ensureUserDraft(row).role" class="table-select">
                      <option value="admin">admin</option>
                      <option value="mentor">mentor</option>
                      <option value="student">student</option>
                    </select>
                  </td>
                  <td>
                    <select v-model="ensureUserDraft(row).status" class="table-select">
                      <option value="active">active</option>
                      <option value="inactive">inactive</option>
                    </select>
                  </td>
                  <td>
                    <button
                      type="button"
                      class="btn btn-primary btn-small"
                      :disabled="isUserSaving[row.id]"
                      @click="saveUserRole(row)"
                    >
                      Simpan
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>
      </template>
    </div>
  </section>
</template>
