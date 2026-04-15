<script setup lang="ts">
import { DEMO_ACCOUNTS } from '../constants/demoAccounts'

type ApiHealth = {
  status: string
  service: string
  timestamp: string
}

type Course = {
  id: number
  title: string
  slug: string
  description: string | null
  level: string
  duration: string | null
  category: string | null
  image_url: string
  trailer_video_url: string | null
  tools: string[]
  mentor_name: string | null
  rating_avg: number
}

const runtimeConfig = useRuntimeConfig()
const apiBase = runtimeConfig.public.apiBase

const {
  data: healthData,
  pending: healthPending,
  error: healthError,
  refresh: refreshHealth,
} = await useFetch<ApiHealth>('/api/health', {
  baseURL: apiBase,
  server: false,
})

const apiStatusLabel = computed(() => {
  if (healthPending.value) {
    return 'Mengecek koneksi...'
  }

  if (healthError.value) {
    return 'Backend belum terhubung'
  }

  return healthData.value?.status === 'ok' ? 'Backend online' : 'Backend merespons'
})

const {
  data: coursesData,
  pending: coursesPending,
  error: coursesError,
  refresh: refreshCourses,
} = await useFetch<Course[]>('/api/courses', {
  baseURL: apiBase,
  server: false,
  default: () => [],
})

const features = [
  'Manajemen kursus, modul, dan materi',
  'Tracking progres belajar per pengguna',
  'Kelas live + kuis + assignment',
  'Role user: admin, mentor, student',
]

const demoAccounts = DEMO_ACCOUNTS
</script>

<template>
  <section class="hero">
    <div class="container hero-grid">
      <div>
        <p class="eyebrow">Responsive LMS Starter</p>
        <h1 class="hero-title">Platform belajar modern yang siap tumbuh jangka panjang.</h1>
        <p class="hero-copy">
          Starter ini menggunakan Nuxt + Laravel API dalam Docker. Layout sudah responsive
          untuk desktop, tablet, dan mobile.
        </p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="#kursus">Lihat Kursus</a>
        </div>
        <img
          src="/images/hero-learning.svg"
          alt="Ilustrasi LMS modern"
          class="hero-visual"
          loading="lazy"
        />
      </div>

      <div class="status-panel" id="status">
        <h2>Status Integrasi</h2>
        <p class="status-line">
          <span
            class="dot"
            :class="{
              'dot-ok': !healthPending && !healthError,
              'dot-busy': healthPending,
              'dot-down': healthError,
            }"
          />
          {{ apiStatusLabel }}
        </p>
        <p class="status-meta">Base URL: {{ apiBase }}</p>
        <p v-if="healthData?.timestamp" class="status-meta">
          Update terakhir: {{ new Date(healthData.timestamp).toLocaleString() }}
        </p>
        <button class="btn btn-secondary btn-block" type="button" @click="refreshHealth()">
          Refresh status
        </button>
      </div>
    </div>
  </section>

  <section class="section" id="fitur">
    <div class="container">
      <h2>Pilih mode tampilan</h2>
      <div class="mode-grid">
        <NuxtLink to="/student" class="mode-card">
          <img src="/images/course-project.svg" alt="Mode student" class="mode-thumb" loading="lazy" />
          <p class="stack-title">Student Dashboard</p>
          <p class="stack-meta">Halaman student: progress, session, dan task belajar.</p>
        </NuxtLink>
        <NuxtLink to="/mentor" class="mode-card">
          <img src="/images/course-data.svg" alt="Mode mentor" class="mode-thumb" loading="lazy" />
          <p class="stack-title">Mentor Dashboard</p>
          <p class="stack-meta">Upload video, susun kurikulum, dan kelola lesson.</p>
        </NuxtLink>
        <NuxtLink to="/admin" class="mode-card">
          <img src="/images/hero-admin.svg" alt="Mode admin" class="mode-thumb" loading="lazy" />
          <p class="stack-title">Admin Dashboard</p>
          <p class="stack-meta">Halaman admin: statistik, trend enrollment, dan manajemen course.</p>
        </NuxtLink>
      </div>

      <h2>Demo Login</h2>
      <div class="feature-grid">
        <article v-for="account in demoAccounts" :key="account.email" class="feature-card">
          <p class="stack-title">{{ account.label }}</p>
          <p class="stack-meta">{{ account.email }}</p>
          <p class="stack-meta">password: {{ account.password }}</p>
          <p class="stack-meta">dashboard: {{ account.defaultPath }}</p>
        </article>
      </div>

      <h2>Fitur awal LMS</h2>
      <div class="feature-grid">
        <article v-for="item in features" :key="item" class="feature-card">
          <p>{{ item }}</p>
        </article>
      </div>
    </div>
  </section>

  <section class="section" id="kursus">
    <div class="container">
      <h2>Contoh katalog kursus</h2>
      <p v-if="coursesPending" class="status-meta">Memuat data kursus...</p>
      <p v-else-if="coursesError" class="status-meta">
        Gagal memuat kursus dari API.
        <button class="btn btn-secondary" type="button" @click="refreshCourses()">
          Coba lagi
        </button>
      </p>
      <p v-else-if="!coursesData.length" class="empty-state">
        Katalog sementara kosong. Tambahkan course dari dashboard admin atau klik refresh.
      </p>
      <div v-else class="course-grid">
        <NuxtLink v-for="course in coursesData" :key="course.id" :to="`/courses/${course.slug}`" class="course-card">
          <img
            :src="course.image_url"
            :alt="`Thumbnail ${course.title}`"
            class="course-cover"
            loading="lazy"
          />
          <h3>{{ course.title }}</h3>
          <p class="course-meta">
            {{ course.level }} - {{ course.duration || 'Durasi fleksibel' }}
          </p>
          <p class="stack-meta">Mentor: {{ course.mentor_name || 'Tim Mentor' }}</p>
          <p>{{ course.description || 'Deskripsi akan ditambahkan.' }}</p>
          <p class="course-tools">Tools: {{ (course.tools || []).join(', ') || 'Segera diumumkan' }}</p>
          <p class="course-rating">Rating {{ course.rating_avg.toFixed(1) }}/5 - Lihat detail kursus</p>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>

