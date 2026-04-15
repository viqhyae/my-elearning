<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'role'],
  role: ['mentor', 'admin'],
})

type MentorCourseSummary = {
  id: number
  title: string
  slug: string
  description: string | null
  level: string
  duration_weeks: number | null
  category: string | null
  is_published: boolean
  trailer_video_url: string | null
  tools: string[]
  mentor_user_id: number | null
  mentor_name: string | null
  image_url: string
  module_count: number
  lesson_count: number
  updated_at: string | null
}

type MentorDashboardResponse = {
  summary: {
    total_courses: number
    published_courses: number
    total_modules: number
    total_lessons: number
  }
  courses: MentorCourseSummary[]
}

type MentorLesson = {
  id: number
  module_id: number
  title: string
  description: string | null
  video_url: string | null
  topics: string[]
  tools: string[]
  duration_minutes: number | null
  order_no: number
  is_published: boolean
}

type MentorModule = {
  id: number
  course_id: number
  title: string
  description: string | null
  order_no: number
  is_published: boolean
  lessons: MentorLesson[]
}

type MentorCourseDetail = {
  id: number
  title: string
  slug: string
  description: string | null
  level: string
  duration_weeks: number | null
  category: string | null
  is_published: boolean
  trailer_video_url: string | null
  tools: string[]
  image_url: string
  mentor: { id: number; name: string; email: string } | null
  modules: MentorModule[]
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
} = await useFetch<MentorDashboardResponse>('/api/mentor/dashboard', {
  baseURL: apiBase,
  server: false,
  headers,
  default: () => ({
    summary: {
      total_courses: 0,
      published_courses: 0,
      total_modules: 0,
      total_lessons: 0,
    },
    courses: [],
  }),
})

const selectedCourseId = ref<number | null>(null)
const courseDetail = ref<MentorCourseDetail | null>(null)
const courseLoading = ref(false)
const actionLoading = ref(false)
const actionMessage = ref('')
const actionError = ref('')
const trailerFile = ref<File | null>(null)

const courseForm = reactive({
  description: '',
  toolsInput: '',
  trailer_video_url: '',
})

const moduleForm = reactive({
  id: null as number | null,
  title: '',
  description: '',
  order_no: 1,
  is_published: true,
})

const lessonForm = reactive({
  id: null as number | null,
  module_id: null as number | null,
  title: '',
  description: '',
  video_url: '',
  topicsInput: '',
  toolsInput: '',
  duration_minutes: 20,
  order_no: 1,
  is_published: true,
})

const activeModuleId = ref<number | null>(null)
const activeLessonByModule = ref<Record<number, number | null>>({})

const summaryCards = computed(() => [
  { label: 'Course Diampu', value: dashboardData.value.summary.total_courses },
  { label: 'Published', value: dashboardData.value.summary.published_courses },
  { label: 'Total Modul', value: dashboardData.value.summary.total_modules },
  { label: 'Total Lesson', value: dashboardData.value.summary.total_lessons },
])

const splitTags = (value: string) =>
  value
    .split(',')
    .map((item) => item.trim())
    .filter((item) => item.length > 0)

const resetModuleForm = () => {
  moduleForm.id = null
  moduleForm.title = ''
  moduleForm.description = ''
  moduleForm.order_no = 1
  moduleForm.is_published = true
}

const resetLessonForm = () => {
  lessonForm.id = null
  lessonForm.module_id = null
  lessonForm.title = ''
  lessonForm.description = ''
  lessonForm.video_url = ''
  lessonForm.topicsInput = ''
  lessonForm.toolsInput = ''
  lessonForm.duration_minutes = 20
  lessonForm.order_no = 1
  lessonForm.is_published = true
}

const setCourseFormFromDetail = (detail: MentorCourseDetail) => {
  courseForm.description = detail.description || ''
  courseForm.toolsInput = (detail.tools || []).join(', ')
  courseForm.trailer_video_url = detail.trailer_video_url || ''
}

const loadCourseDetail = async (courseId: number) => {
  courseLoading.value = true
  actionError.value = ''

  try {
    const result = await $fetch<MentorCourseDetail>(`/api/mentor/courses/${courseId}`, {
      baseURL: apiBase,
      headers: headers.value,
    })
    courseDetail.value = result
    setCourseFormFromDetail(result)
  } catch (error: unknown) {
    const message = (error as { data?: { message?: string } })?.data?.message
    actionError.value = message || 'Gagal memuat detail course mentor.'
    courseDetail.value = null
  } finally {
    courseLoading.value = false
  }
}

watch(
  () => dashboardData.value.courses,
  async (courses) => {
    if (!courses.length) {
      selectedCourseId.value = null
      courseDetail.value = null
      return
    }

    if (!selectedCourseId.value || !courses.some((course) => course.id === selectedCourseId.value)) {
      selectedCourseId.value = courses[0].id
    }

    if (selectedCourseId.value) {
      await loadCourseDetail(selectedCourseId.value)
    }
  },
  { immediate: true }
)

watch(
  () => courseDetail.value?.modules,
  (modules) => {
    if (!modules?.length) {
      activeModuleId.value = null
      activeLessonByModule.value = {}
      return
    }

    if (!modules.some((module) => module.id === activeModuleId.value)) {
      activeModuleId.value = modules[0].id
    }

    const nextLessonState: Record<number, number | null> = {}

    for (const module of modules) {
      const selectedLessonId = activeLessonByModule.value[module.id] ?? null

      if (selectedLessonId && module.lessons.some((lesson) => lesson.id === selectedLessonId)) {
        nextLessonState[module.id] = selectedLessonId
      } else {
        nextLessonState[module.id] = module.lessons[0]?.id ?? null
      }
    }

    activeLessonByModule.value = nextLessonState
  },
  { immediate: true }
)

const isModuleOpen = (moduleId: number) => activeModuleId.value === moduleId

const isLessonOpen = (moduleId: number, lessonId: number) => activeLessonByModule.value[moduleId] === lessonId

const toggleModule = (module: MentorModule) => {
  if (activeModuleId.value === module.id) {
    activeModuleId.value = null
    return
  }

  activeModuleId.value = module.id

  if (!module.lessons.length) {
    activeLessonByModule.value[module.id] = null
    return
  }

  const selectedLessonId = activeLessonByModule.value[module.id] ?? null
  if (!module.lessons.some((lesson) => lesson.id === selectedLessonId)) {
    activeLessonByModule.value[module.id] = module.lessons[0].id
  }
}

const toggleLesson = (module: MentorModule, lesson: MentorLesson) => {
  const activeLessonId = activeLessonByModule.value[module.id] ?? null
  activeLessonByModule.value[module.id] = activeLessonId === lesson.id ? null : lesson.id
}

const onAccordionBeforeEnter = (el: Element) => {
  const element = el as HTMLElement
  element.style.height = '0'
  element.style.opacity = '0'
}

const onAccordionEnter = (el: Element) => {
  const element = el as HTMLElement
  element.style.height = `${element.scrollHeight}px`
  element.style.opacity = '1'
}

const onAccordionAfterEnter = (el: Element) => {
  const element = el as HTMLElement
  element.style.height = 'auto'
  element.style.opacity = '1'
}

const onAccordionBeforeLeave = (el: Element) => {
  const element = el as HTMLElement
  element.style.height = `${element.scrollHeight}px`
  element.style.opacity = '1'
}

const onAccordionLeave = (el: Element) => {
  const element = el as HTMLElement
  void element.offsetHeight
  element.style.height = '0'
  element.style.opacity = '0'
}

const onAccordionAfterLeave = (el: Element) => {
  const element = el as HTMLElement
  element.style.height = ''
  element.style.opacity = ''
}

const onChangeCourse = async () => {
  actionMessage.value = ''
  actionError.value = ''

  if (!selectedCourseId.value) {
    courseDetail.value = null
    return
  }

  await loadCourseDetail(selectedCourseId.value)
}

const saveCourseInfo = async () => {
  if (!selectedCourseId.value) {
    return
  }

  actionLoading.value = true
  actionMessage.value = ''
  actionError.value = ''

  try {
    await $fetch(`/api/mentor/courses/${selectedCourseId.value}`, {
      method: 'PUT',
      baseURL: apiBase,
      headers: headers.value,
      body: {
        description: courseForm.description || null,
        tools: splitTags(courseForm.toolsInput),
        trailer_video_url: courseForm.trailer_video_url || null,
      },
    })

    await loadCourseDetail(selectedCourseId.value)
    await refreshDashboard()
    actionMessage.value = 'Informasi course berhasil disimpan.'
  } catch (error: unknown) {
    const message = (error as { data?: { message?: string } })?.data?.message
    actionError.value = message || 'Gagal menyimpan informasi course.'
  } finally {
    actionLoading.value = false
  }
}

const onTrailerChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  trailerFile.value = target.files?.[0] || null
}

const uploadTrailer = async () => {
  if (!selectedCourseId.value || !trailerFile.value) {
    return
  }

  actionLoading.value = true
  actionMessage.value = ''
  actionError.value = ''

  try {
    const formData = new FormData()
    formData.append('video', trailerFile.value)

    const response = await $fetch<{ trailer_video_url: string }>(
      `/api/mentor/courses/${selectedCourseId.value}/trailer/upload`,
      {
        method: 'POST',
        baseURL: apiBase,
        headers: headers.value,
        body: formData,
      }
    )

    courseForm.trailer_video_url = response.trailer_video_url
    await loadCourseDetail(selectedCourseId.value)
    await refreshDashboard()
    trailerFile.value = null
    actionMessage.value = 'Video trailer berhasil diupload.'
  } catch (error: unknown) {
    const message = (error as { data?: { message?: string } })?.data?.message
    actionError.value = message || 'Upload trailer gagal.'
  } finally {
    actionLoading.value = false
  }
}

const submitModule = async () => {
  if (!selectedCourseId.value || !moduleForm.title.trim()) {
    actionError.value = 'Judul modul wajib diisi.'
    return
  }

  actionLoading.value = true
  actionMessage.value = ''
  actionError.value = ''

  const payload = {
    title: moduleForm.title,
    description: moduleForm.description || null,
    order_no: Number(moduleForm.order_no) || 1,
    is_published: moduleForm.is_published,
  }

  try {
    if (moduleForm.id) {
      await $fetch(`/api/mentor/modules/${moduleForm.id}`, {
        method: 'PUT',
        baseURL: apiBase,
        headers: headers.value,
        body: payload,
      })
      actionMessage.value = 'Modul berhasil diperbarui.'
    } else {
      await $fetch(`/api/mentor/courses/${selectedCourseId.value}/modules`, {
        method: 'POST',
        baseURL: apiBase,
        headers: headers.value,
        body: payload,
      })
      actionMessage.value = 'Modul berhasil ditambahkan.'
    }

    resetModuleForm()
    await loadCourseDetail(selectedCourseId.value)
    await refreshDashboard()
  } catch (error: unknown) {
    const message = (error as { data?: { message?: string } })?.data?.message
    actionError.value = message || 'Gagal menyimpan modul.'
  } finally {
    actionLoading.value = false
  }
}

const editModule = (module: MentorModule) => {
  moduleForm.id = module.id
  moduleForm.title = module.title
  moduleForm.description = module.description || ''
  moduleForm.order_no = module.order_no
  moduleForm.is_published = module.is_published
}

const removeModule = async (module: MentorModule) => {
  if (!selectedCourseId.value) {
    return
  }

  if (process.client && !window.confirm(`Hapus modul "${module.title}"?`)) {
    return
  }

  actionLoading.value = true
  actionMessage.value = ''
  actionError.value = ''

  try {
    await $fetch(`/api/mentor/modules/${module.id}`, {
      method: 'DELETE',
      baseURL: apiBase,
      headers: headers.value,
    })
    await loadCourseDetail(selectedCourseId.value)
    await refreshDashboard()
    if (moduleForm.id === module.id) {
      resetModuleForm()
    }
    actionMessage.value = 'Modul berhasil dihapus.'
  } catch (error: unknown) {
    const message = (error as { data?: { message?: string } })?.data?.message
    actionError.value = message || 'Gagal menghapus modul.'
  } finally {
    actionLoading.value = false
  }
}

const submitLesson = async () => {
  if (!selectedCourseId.value || !lessonForm.module_id || !lessonForm.title.trim()) {
    actionError.value = 'Module dan judul lesson wajib diisi.'
    return
  }

  actionLoading.value = true
  actionMessage.value = ''
  actionError.value = ''

  const payload = {
    title: lessonForm.title,
    description: lessonForm.description || null,
    video_url: lessonForm.video_url || null,
    topics: splitTags(lessonForm.topicsInput),
    tools: splitTags(lessonForm.toolsInput),
    duration_minutes: Number(lessonForm.duration_minutes) || null,
    order_no: Number(lessonForm.order_no) || 1,
    is_published: lessonForm.is_published,
  }

  try {
    if (lessonForm.id) {
      await $fetch(`/api/mentor/lessons/${lessonForm.id}`, {
        method: 'PUT',
        baseURL: apiBase,
        headers: headers.value,
        body: payload,
      })
      actionMessage.value = 'Lesson berhasil diperbarui.'
    } else {
      await $fetch(`/api/mentor/modules/${lessonForm.module_id}/lessons`, {
        method: 'POST',
        baseURL: apiBase,
        headers: headers.value,
        body: payload,
      })
      actionMessage.value = 'Lesson berhasil ditambahkan.'
    }

    resetLessonForm()
    await loadCourseDetail(selectedCourseId.value)
    await refreshDashboard()
  } catch (error: unknown) {
    const message = (error as { data?: { message?: string } })?.data?.message
    actionError.value = message || 'Gagal menyimpan lesson.'
  } finally {
    actionLoading.value = false
  }
}

const editLesson = (lesson: MentorLesson) => {
  lessonForm.id = lesson.id
  lessonForm.module_id = lesson.module_id
  lessonForm.title = lesson.title
  lessonForm.description = lesson.description || ''
  lessonForm.video_url = lesson.video_url || ''
  lessonForm.topicsInput = (lesson.topics || []).join(', ')
  lessonForm.toolsInput = (lesson.tools || []).join(', ')
  lessonForm.duration_minutes = lesson.duration_minutes || 20
  lessonForm.order_no = lesson.order_no
  lessonForm.is_published = lesson.is_published
}

const removeLesson = async (lesson: MentorLesson) => {
  if (!selectedCourseId.value) {
    return
  }

  if (process.client && !window.confirm(`Hapus lesson "${lesson.title}"?`)) {
    return
  }

  actionLoading.value = true
  actionMessage.value = ''
  actionError.value = ''

  try {
    await $fetch(`/api/mentor/lessons/${lesson.id}`, {
      method: 'DELETE',
      baseURL: apiBase,
      headers: headers.value,
    })
    await loadCourseDetail(selectedCourseId.value)
    await refreshDashboard()
    if (lessonForm.id === lesson.id) {
      resetLessonForm()
    }
    actionMessage.value = 'Lesson berhasil dihapus.'
  } catch (error: unknown) {
    const message = (error as { data?: { message?: string } })?.data?.message
    actionError.value = message || 'Gagal menghapus lesson.'
  } finally {
    actionLoading.value = false
  }
}

const formatDateTime = (value: string | null) =>
  value ? new Date(value).toLocaleString('id-ID') : '-'
</script>

<template>
  <section class="page-head page-head-admin">
    <div class="container">
      <p class="eyebrow">Mentor Dashboard</p>
      <h1 class="page-title">Mentor Studio</h1>
      <p class="page-copy">
        Upload video trailer, isi kurikulum, dan kelola modul-lesson untuk course yang kamu ampu.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div v-if="dashboardPending" class="notice-card">Memuat data mentor dashboard...</div>
      <div v-else-if="dashboardError" class="notice-card notice-error">
        Gagal memuat data mentor dashboard.
        <button type="button" class="btn btn-secondary btn-small" @click="refreshDashboard()">Refresh</button>
      </div>

      <template v-else>
        <div class="stats-grid">
          <article v-for="item in summaryCards" :key="item.label" class="stat-card stat-card-admin">
            <p class="stat-label">{{ item.label }}</p>
            <p class="stat-value">{{ item.value }}</p>
          </article>
        </div>

        <article v-if="dashboardData.courses.length" class="panel-card">
          <div class="form-grid">
            <label class="form-field form-field-full">
              <span>Pilih Course</span>
              <select v-model.number="selectedCourseId" @change="onChangeCourse()">
                <option v-for="course in dashboardData.courses" :key="course.id" :value="course.id">
                  {{ course.title }} - {{ course.module_count }} modul - {{ course.lesson_count }} lesson
                </option>
              </select>
            </label>
          </div>
          <p v-if="actionMessage" class="status-meta">{{ actionMessage }}</p>
          <p v-if="actionError" class="status-meta status-error">{{ actionError }}</p>
        </article>
        <article v-else class="panel-card">
          <p class="empty-state">
            Belum ada course untuk mentor ini. Tambahkan course dari dashboard admin.
          </p>
        </article>

        <article v-if="courseLoading" class="panel-card section-spacer">Memuat detail course...</article>

        <template v-else-if="courseDetail">
          <div class="panel-grid section-spacer">
            <article class="panel-card">
              <div class="course-cell">
                <img :src="courseDetail.image_url" :alt="courseDetail.title" class="table-thumb" loading="lazy" />
                <div>
                  <p class="stack-title">{{ courseDetail.title }}</p>
                  <p class="stack-meta">
                    Mentor: {{ courseDetail.mentor?.name || '-' }} - Update:
                    {{ formatDateTime(dashboardData.courses.find((item) => item.id === courseDetail?.id)?.updated_at || null) }}
                  </p>
                </div>
              </div>

              <h2 class="section-spacer-sm">Informasi Course</h2>
              <div class="form-grid">
                <label class="form-field form-field-full">
                  <span>Deskripsi Course</span>
                  <textarea v-model="courseForm.description" rows="3" placeholder="Apa saja yang dibahas?" />
                </label>
                <label class="form-field form-field-full">
                  <span>Tools (pisahkan dengan koma)</span>
                  <input
                    v-model="courseForm.toolsInput"
                    type="text"
                    placeholder="Notion, Trello, Google Sheets"
                  />
                </label>
                <label class="form-field form-field-full">
                  <span>Trailer URL (YouTube Embed / Video URL)</span>
                  <input
                    v-model="courseForm.trailer_video_url"
                    type="text"
                    placeholder="https://www.youtube.com/embed/..."
                  />
                </label>
              </div>
              <div class="form-actions">
                <button type="button" class="btn btn-primary" :disabled="actionLoading" @click="saveCourseInfo()">
                  Simpan Informasi Course
                </button>
              </div>
            </article>

            <article class="panel-card">
              <h2>Upload Video Trailer</h2>
              <p class="status-meta">Format: mp4/webm/mov - max 100MB</p>
              <div class="form-grid">
                <label class="form-field form-field-full">
                  <span>Pilih File Video</span>
                  <input type="file" accept="video/*" @change="onTrailerChange" />
                </label>
              </div>
              <div class="form-actions">
                <button type="button" class="btn btn-primary" :disabled="actionLoading || !trailerFile" @click="uploadTrailer()">
                  Upload Trailer
                </button>
              </div>
              <p class="status-meta">
                Trailer aktif:
                <a v-if="courseDetail.trailer_video_url" :href="courseDetail.trailer_video_url" target="_blank" rel="noreferrer">
                  {{ courseDetail.trailer_video_url }}
                </a>
                <span v-else>-</span>
              </p>
            </article>
          </div>

          <div class="panel-grid section-spacer">
            <article class="panel-card">
              <div class="split-row">
                <h2>{{ moduleForm.id ? 'Edit Modul' : 'Tambah Modul' }}</h2>
                <button type="button" class="btn btn-secondary btn-small" @click="resetModuleForm()">
                  Reset
                </button>
              </div>
              <div class="form-grid">
                <label class="form-field form-field-full">
                  <span>Judul Modul</span>
                  <input v-model="moduleForm.title" type="text" placeholder="Contoh: Data Preparation" />
                </label>
                <label class="form-field">
                  <span>Urutan</span>
                  <input v-model.number="moduleForm.order_no" type="number" min="1" />
                </label>
                <label class="form-field form-field-checkbox">
                  <input v-model="moduleForm.is_published" type="checkbox" />
                  <span>Published</span>
                </label>
                <label class="form-field form-field-full">
                  <span>Deskripsi Modul</span>
                  <textarea v-model="moduleForm.description" rows="3" placeholder="Ringkasan modul" />
                </label>
              </div>
              <div class="form-actions">
                <button type="button" class="btn btn-primary" :disabled="actionLoading" @click="submitModule()">
                  {{ moduleForm.id ? 'Update Modul' : 'Tambah Modul' }}
                </button>
              </div>
            </article>

            <article class="panel-card">
              <div class="split-row">
                <h2>{{ lessonForm.id ? 'Edit Lesson' : 'Tambah Lesson' }}</h2>
                <button type="button" class="btn btn-secondary btn-small" @click="resetLessonForm()">
                  Reset
                </button>
              </div>
              <div class="form-grid">
                <label class="form-field form-field-full">
                  <span>Pilih Modul</span>
                  <select v-model.number="lessonForm.module_id">
                    <option :value="null">-- pilih modul --</option>
                    <option v-for="module in courseDetail.modules" :key="module.id" :value="module.id">
                      {{ module.order_no }}. {{ module.title }}
                    </option>
                  </select>
                </label>
                <label class="form-field form-field-full">
                  <span>Judul Lesson</span>
                  <input v-model="lessonForm.title" type="text" placeholder="Contoh: Cleaning Dataset" />
                </label>
                <label class="form-field">
                  <span>Urutan</span>
                  <input v-model.number="lessonForm.order_no" type="number" min="1" />
                </label>
                <label class="form-field">
                  <span>Durasi (menit)</span>
                  <input v-model.number="lessonForm.duration_minutes" type="number" min="1" />
                </label>
                <label class="form-field form-field-checkbox">
                  <input v-model="lessonForm.is_published" type="checkbox" />
                  <span>Published</span>
                </label>
                <label class="form-field form-field-full">
                  <span>Video URL</span>
                  <input v-model="lessonForm.video_url" type="text" placeholder="https://..." />
                </label>
                <label class="form-field form-field-full">
                  <span>Topics (pisahkan dengan koma)</span>
                  <input v-model="lessonForm.topicsInput" type="text" placeholder="Missing value, Outlier" />
                </label>
                <label class="form-field form-field-full">
                  <span>Tools (pisahkan dengan koma)</span>
                  <input v-model="lessonForm.toolsInput" type="text" placeholder="Google Colab, Spreadsheet" />
                </label>
                <label class="form-field form-field-full">
                  <span>Deskripsi Lesson</span>
                  <textarea v-model="lessonForm.description" rows="3" placeholder="Detail pembahasan lesson" />
                </label>
              </div>
              <div class="form-actions">
                <button type="button" class="btn btn-primary" :disabled="actionLoading" @click="submitLesson()">
                  {{ lessonForm.id ? 'Update Lesson' : 'Tambah Lesson' }}
                </button>
              </div>
            </article>
          </div>

          <article class="panel-card section-spacer">
            <h2>Daftar Kurikulum</h2>
            <div class="stack-list">
              <div
                v-for="module in courseDetail.modules"
                :key="module.id"
                class="curriculum-item curriculum-module"
                :class="{ 'is-open': isModuleOpen(module.id) }"
              >
                <div class="curriculum-summary">
                  <button
                    type="button"
                    class="curriculum-toggle"
                    :class="{ 'is-open': isModuleOpen(module.id) }"
                    @click="toggleModule(module)"
                  >
                    <span class="curriculum-heading">
                      <span class="curriculum-caret" aria-hidden="true" />
                      <span class="stack-title">{{ module.order_no }}. {{ module.title }}</span>
                    </span>
                    <span class="curriculum-pill">{{ module.lessons.length }} lesson</span>
                  </button>
                  <div class="table-actions">
                    <button type="button" class="btn btn-secondary btn-small" @click.stop="editModule(module)">
                      Edit Modul
                    </button>
                    <button type="button" class="btn btn-danger btn-small" @click.stop="removeModule(module)">
                      Hapus Modul
                    </button>
                  </div>
                </div>

                <Transition
                  name="accordion-slide"
                  @before-enter="onAccordionBeforeEnter"
                  @enter="onAccordionEnter"
                  @after-enter="onAccordionAfterEnter"
                  @before-leave="onAccordionBeforeLeave"
                  @leave="onAccordionLeave"
                  @after-leave="onAccordionAfterLeave"
                >
                  <div v-if="isModuleOpen(module.id)" class="curriculum-panel">
                    <div class="curriculum-panel-inner">
                      <p class="stack-meta">{{ module.description || 'Deskripsi modul belum diisi.' }}</p>
                      <p class="stack-meta">
                        Status: <strong>{{ module.is_published ? 'Published' : 'Draft' }}</strong>
                      </p>

                      <div class="stack-list section-spacer-sm">
                        <div
                          v-for="lesson in module.lessons"
                          :key="lesson.id"
                          class="curriculum-item curriculum-lesson"
                          :class="{ 'is-open': isLessonOpen(module.id, lesson.id) }"
                        >
                          <div class="curriculum-summary">
                            <button
                              type="button"
                              class="curriculum-toggle curriculum-toggle-lesson"
                              :class="{ 'is-open': isLessonOpen(module.id, lesson.id) }"
                              @click="toggleLesson(module, lesson)"
                            >
                              <span class="curriculum-heading">
                                <span class="curriculum-caret" aria-hidden="true" />
                                <span class="stack-title">{{ lesson.order_no }}. {{ lesson.title }}</span>
                              </span>
                              <span class="curriculum-pill curriculum-pill-soft">
                                {{ lesson.duration_minutes ? `${lesson.duration_minutes} menit` : 'Durasi -' }}
                              </span>
                            </button>
                            <div class="table-actions">
                              <button type="button" class="btn btn-secondary btn-small" @click.stop="editLesson(lesson)">
                                Edit Lesson
                              </button>
                              <button type="button" class="btn btn-danger btn-small" @click.stop="removeLesson(lesson)">
                                Hapus Lesson
                              </button>
                            </div>
                          </div>

                          <Transition
                            name="accordion-slide"
                            @before-enter="onAccordionBeforeEnter"
                            @enter="onAccordionEnter"
                            @after-enter="onAccordionAfterEnter"
                            @before-leave="onAccordionBeforeLeave"
                            @leave="onAccordionLeave"
                            @after-leave="onAccordionAfterLeave"
                          >
                            <div v-if="isLessonOpen(module.id, lesson.id)" class="curriculum-panel">
                              <div class="curriculum-panel-inner">
                                <p class="stack-meta">{{ lesson.description || 'Deskripsi lesson belum diisi.' }}</p>
                                <p class="stack-meta">Topik: {{ lesson.topics.join(', ') || '-' }}</p>
                                <p class="stack-meta">Tools: {{ lesson.tools.join(', ') || '-' }}</p>
                                <p class="stack-meta">Video: {{ lesson.video_url || '-' }}</p>
                              </div>
                            </div>
                          </Transition>
                        </div>

                        <p v-if="!module.lessons.length" class="empty-state">
                          Belum ada lesson pada modul ini.
                        </p>
                      </div>
                    </div>
                  </div>
                </Transition>
              </div>

              <p v-if="!courseDetail.modules.length" class="status-meta">
                Belum ada modul. Mulai dari form "Tambah Modul".
              </p>
            </div>
          </article>
        </template>
      </template>
    </div>
  </section>
</template>


