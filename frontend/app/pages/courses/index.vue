<script setup lang="ts">
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
  data: coursesData,
  pending: coursesPending,
  error: coursesError,
  refresh: refreshCourses,
} = await useFetch<Course[]>('/api/courses', {
  baseURL: apiBase,
  server: false,
  default: () => [],
})
</script>

<template>
  <section class="page-head">
    <div class="container">
      <p class="eyebrow">Katalog Kursus</p>
      <h1 class="page-title">Semua Kursus Tersedia</h1>
      <p class="page-copy">
        Jelajahi semua course yang sudah dipublish. Klik salah satu course untuk melihat detail
        materi, trailer, dan kurikulum.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="split-row section-spacer-sm">
        <p class="status-meta">Total course: {{ coursesData.length }}</p>
        <button class="btn btn-secondary btn-small" type="button" @click="refreshCourses()">
          Refresh katalog
        </button>
      </div>

      <p v-if="coursesPending" class="notice-card">Memuat data katalog...</p>
      <p v-else-if="coursesError" class="notice-card notice-error">
        Gagal memuat katalog course.
        <button class="btn btn-secondary btn-small" type="button" @click="refreshCourses()">
          Coba lagi
        </button>
      </p>
      <p v-else-if="!coursesData.length" class="empty-state">
        Katalog masih kosong. Tambahkan course dari dashboard admin.
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
