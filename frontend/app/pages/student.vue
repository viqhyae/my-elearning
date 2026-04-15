<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'role'],
  role: ['student', 'mentor', 'admin'],
})

type DashboardSummary = {
  active_courses: number
  completion_rate: number
  learning_streak_days: number
  certificates: number
}

type LearningPathItem = {
  title: string
  progress_percent: number
  current_lesson: string
  next_deadline: string
  cover_image: string
}

type SessionItem = {
  title: string
  mentor: string
  start_at: string
  duration_minutes: number
  banner_image: string
}

type TaskItem = {
  title: string
  course: string
  status: string
  due_date: string
}

type StudentDashboardResponse = {
  summary: DashboardSummary
  learning_path: LearningPathItem[]
  upcoming_sessions: SessionItem[]
  tasks: TaskItem[]
}

const runtimeConfig = useRuntimeConfig()
const apiBase = runtimeConfig.public.apiBase
const auth = useAuth()

await auth.ensureSession()

if (process.client && !auth.isAuthenticated.value) {
  await navigateTo('/login')
}

const headers = computed(() => auth.authHeaders())

const { data, pending, error, refresh } = await useFetch<StudentDashboardResponse>(
  '/api/student/dashboard',
  {
    baseURL: apiBase,
    server: false,
    headers,
  }
)

const summaryCards = computed(() => {
  if (!data.value) {
    return []
  }

  return [
    { label: 'Kursus Aktif', value: data.value.summary.active_courses, suffix: '' },
    { label: 'Rata-rata Progress', value: data.value.summary.completion_rate, suffix: '%' },
    { label: 'Learning Streak', value: data.value.summary.learning_streak_days, suffix: ' hari' },
    { label: 'Sertifikat', value: data.value.summary.certificates, suffix: '' },
  ]
})

const formatDate = (value: string) => new Date(value).toLocaleDateString('id-ID')
const formatDateTime = (value: string) => new Date(value).toLocaleString('id-ID')
</script>

<template>
  <section class="page-head">
    <div class="container">
      <p class="eyebrow">Front End - Student View</p>
      <h1 class="page-title">Dashboard Pembelajar</h1>
      <p class="page-copy">
        Tampilan ini mensimulasikan pengalaman user student dengan data dummy dari API backend.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div v-if="pending" class="notice-card">Memuat dashboard student...</div>
      <div v-else-if="error" class="notice-card notice-error">
        Gagal memuat dashboard.
        <button type="button" class="btn btn-secondary" @click="refresh()">Refresh</button>
      </div>

      <template v-else>
        <div class="stats-grid">
          <article v-for="item in summaryCards" :key="item.label" class="stat-card">
            <p class="stat-label">{{ item.label }}</p>
            <p class="stat-value">{{ item.value }}{{ item.suffix }}</p>
          </article>
        </div>

        <div class="panel-grid">
          <article class="panel-card">
            <h2>Progress Belajar</h2>
            <div class="stack-list">
              <div v-for="item in data?.learning_path" :key="item.title" class="stack-row">
                <img :src="item.cover_image" :alt="`Cover ${item.title}`" class="stack-cover" loading="lazy" />
                <div class="stack-row-head">
                  <p class="stack-title">{{ item.title }}</p>
                  <p class="stack-percent">{{ item.progress_percent }}%</p>
                </div>
                <div class="progress-track">
                  <div class="progress-fill" :style="{ width: `${item.progress_percent}%` }" />
                </div>
                <p class="stack-meta">Lesson: {{ item.current_lesson }}</p>
                <p class="stack-meta">Deadline: {{ formatDate(item.next_deadline) }}</p>
              </div>
              <p v-if="!data?.learning_path.length" class="empty-state">
                Belum ada progres belajar yang tercatat.
              </p>
            </div>
          </article>

          <article class="panel-card">
            <h2>Live Session Terdekat</h2>
            <div class="stack-list">
              <div v-for="session in data?.upcoming_sessions" :key="session.title" class="stack-row">
                <img
                  :src="session.banner_image"
                  :alt="`Banner ${session.title}`"
                  class="stack-cover"
                  loading="lazy"
                />
                <p class="stack-title">{{ session.title }}</p>
                <p class="stack-meta">Mentor: {{ session.mentor }}</p>
                <p class="stack-meta">Mulai: {{ formatDateTime(session.start_at) }}</p>
                <p class="stack-meta">Durasi: {{ session.duration_minutes }} menit</p>
              </div>
              <p v-if="!data?.upcoming_sessions.length" class="empty-state">
                Belum ada live session terdekat.
              </p>
            </div>
          </article>
        </div>

        <article class="panel-card">
          <h2>Task Mingguan</h2>
          <div class="tasks-grid">
            <div v-for="task in data?.tasks" :key="task.title" class="task-item">
              <div>
                <p class="stack-title">{{ task.title }}</p>
                <p class="stack-meta">{{ task.course }}</p>
                <p class="stack-meta">Due: {{ formatDate(task.due_date) }}</p>
              </div>
              <span
                class="tag"
                :class="{ 'tag-success': task.status === 'Selesai', 'tag-warning': task.status !== 'Selesai' }"
              >
                {{ task.status }}
              </span>
            </div>
            <p v-if="!data?.tasks.length" class="empty-state">
              Tidak ada task mingguan saat ini.
            </p>
          </div>
        </article>
      </template>
    </div>
  </section>
</template>
