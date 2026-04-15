<script setup lang="ts">
type CourseReview = {
  id: number
  author: string
  rating: number
  comment: string
  created_at: string
}

type CourseLesson = {
  id: number
  title: string
  description: string | null
  video_url: string | null
  topics: string[]
  tools: string[]
  duration_minutes: number | null
  order_no: number
}

type CourseModule = {
  id: number
  title: string
  description: string | null
  order_no: number
  lessons: CourseLesson[]
}

type CourseDetail = {
  id: number
  title: string
  slug: string
  description: string | null
  level: string
  duration: string | null
  category: string | null
  price_amount: number
  currency: string
  price_label: string
  image_url: string
  trailer_video_url: string | null
  tools: string[]
  mentor: { name: string; email: string } | null
  what_you_learn: string[]
  reviews: CourseReview[]
  rating_avg: number
  modules: CourseModule[]
}

const route = useRoute()
const runtimeConfig = useRuntimeConfig()
const apiBase = runtimeConfig.public.apiBase
const auth = useAuth()
const slug = computed(() => String(route.params.slug || ''))

await auth.ensureSession()

const {
  data,
  pending,
  error,
  refresh,
} = await useFetch<CourseDetail>(() => `/api/courses/${slug.value}`, {
  baseURL: apiBase,
  server: false,
  watch: [slug],
})

const totalLessons = computed(() =>
  (data.value?.modules || []).reduce((total, module) => total + module.lessons.length, 0)
)

const trailerMode = computed<'none' | 'iframe' | 'video'>(() => {
  const url = data.value?.trailer_video_url

  if (!url) {
    return 'none'
  }

  if (url.includes('youtube.com') || url.includes('youtu.be')) {
    return 'iframe'
  }

  return 'video'
})

const stars = (value: number) => {
  const rounded = Math.max(1, Math.min(5, Math.round(value)))
  return '*'.repeat(rounded)
}

const checkoutPath = computed(() => {
  if (!data.value) {
    return '/payments'
  }

  return `/payments?course=${data.value.id}`
})
</script>

<template>
  <section class="page-head">
    <div class="container">
      <NuxtLink to="/courses" class="status-meta">&larr; Kembali ke katalog</NuxtLink>
      <p class="eyebrow">Detail Katalog</p>
      <h1 class="page-title">{{ data?.title || 'Memuat kursus...' }}</h1>
      <p class="page-copy">
        Lihat deskripsi, trailer, tools, review, dan kurikulum lengkap sebelum mulai belajar.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <article v-if="pending" class="notice-card">Memuat detail kursus...</article>
      <article v-else-if="error" class="notice-card notice-error">
        Gagal memuat detail kursus.
        <button type="button" class="btn btn-secondary btn-small" @click="refresh()">Coba lagi</button>
      </article>

      <template v-else-if="data">
        <div class="panel-grid">
          <article class="panel-card">
            <img :src="data.image_url" :alt="`Cover ${data.title}`" class="course-detail-cover" loading="lazy" />
            <p class="course-meta">{{ data.level }} - {{ data.duration || 'Durasi fleksibel' }}</p>
            <p class="stack-meta">Kategori: {{ data.category || 'General' }}</p>
            <p class="stack-meta">Mentor: {{ data.mentor?.name || 'Tim Mentor' }}</p>
            <p class="stack-meta">
              Rating: {{ data.rating_avg.toFixed(1) }}/5 ({{ data.reviews.length }} review)
            </p>
            <p class="stack-percent">Harga: {{ data.price_label }}</p>
            <p>{{ data.description || 'Deskripsi kursus belum ditambahkan.' }}</p>
            <div class="hero-actions">
              <NuxtLink v-if="auth.isAuthenticated.value" :to="checkoutPath" class="btn btn-primary">
                Beli kelas ini
              </NuxtLink>
              <NuxtLink v-else to="/login" class="btn btn-primary">Login untuk mulai belajar</NuxtLink>
            </div>
          </article>

          <article class="panel-card">
            <h2>Video Trailer</h2>
            <div v-if="trailerMode === 'iframe'" class="video-frame">
              <iframe
                :src="data.trailer_video_url || ''"
                title="Trailer Kursus"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
              />
            </div>
            <video v-else-if="trailerMode === 'video'" class="video-player" controls :src="data.trailer_video_url || ''" />
            <p v-else class="status-meta">Trailer belum ditambahkan oleh mentor.</p>

            <h2 class="section-spacer-sm">Tools Pembelajaran</h2>
            <div class="tag-list">
              <span v-for="tool in data.tools" :key="tool" class="tag">{{ tool }}</span>
              <p v-if="!data.tools.length" class="status-meta">Belum ada tools yang ditentukan.</p>
            </div>
          </article>
        </div>

        <article class="panel-card section-spacer">
          <div class="split-row">
            <h2>Kurikulum</h2>
            <p class="status-meta">{{ data.modules.length }} modul - {{ totalLessons }} lesson</p>
          </div>
          <div class="stack-list">
            <details v-for="module in data.modules" :key="module.id" class="curriculum-item curriculum-module">
              <summary class="curriculum-summary">
                <span class="curriculum-heading">
                  <span class="curriculum-caret" aria-hidden="true" />
                  <span class="stack-title">{{ module.order_no }}. {{ module.title }}</span>
                </span>
                <span class="curriculum-pill">{{ module.lessons.length }} lesson</span>
              </summary>
              <div class="curriculum-panel">
                <div class="curriculum-panel-inner">
                  <p class="stack-meta">{{ module.description || 'Deskripsi modul belum ditambahkan.' }}</p>
                  <div class="stack-list section-spacer-sm">
                    <div v-for="lesson in module.lessons" :key="lesson.id" class="curriculum-item curriculum-lesson">
                      <div class="curriculum-panel-inner">
                        <p class="stack-title">{{ lesson.order_no }}. {{ lesson.title }}</p>
                        <p class="stack-meta">{{ lesson.description || 'Materi lesson belum diisi.' }}</p>
                        <p class="stack-meta">
                          Durasi: {{ lesson.duration_minutes ? `${lesson.duration_minutes} menit` : '-' }}
                        </p>
                        <p class="stack-meta">Topik: {{ lesson.topics.join(', ') || '-' }}</p>
                        <p class="stack-meta">Tools: {{ lesson.tools.join(', ') || '-' }}</p>
                      </div>
                    </div>
                    <p v-if="!module.lessons.length" class="empty-state">
                      Belum ada lesson pada modul ini.
                    </p>
                  </div>
                </div>
              </div>
            </details>
          </div>
        </article>

        <article class="panel-card section-spacer">
          <h2>Review Kursus</h2>
          <div class="feature-grid reviews-grid">
            <article v-for="review in data.reviews" :key="review.id" class="feature-card">
              <p class="stack-title">{{ review.author }}</p>
              <p class="course-rating">{{ stars(review.rating) }} - {{ review.rating }}/5</p>
              <p class="stack-meta">{{ new Date(review.created_at).toLocaleDateString('id-ID') }}</p>
              <p>{{ review.comment }}</p>
            </article>
          </div>
        </article>
      </template>
    </div>
  </section>
</template>
