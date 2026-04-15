<script setup lang="ts">
type Course = {
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

const searchKeyword = ref('')
const selectedLevel = ref('all')
const selectedCategory = ref('all')
const sortBy = ref<'rating' | 'title-asc' | 'title-desc'>('rating')
const openDropdown = ref<'level' | 'category' | 'sort' | null>(null)
const levelDropdownRef = ref<HTMLElement | null>(null)
const categoryDropdownRef = ref<HTMLElement | null>(null)
const sortDropdownRef = ref<HTMLElement | null>(null)

const normalizeText = (value: string | null | undefined) => String(value || '').trim().toLowerCase()

const levelOptions = computed(() => {
  const options = new Set<string>()

  for (const course of coursesData.value) {
    if (course.level) {
      options.add(course.level.trim())
    }
  }

  return [...options]
})

const categoryOptions = computed(() => {
  const options = new Set<string>()

  for (const course of coursesData.value) {
    if (course.category) {
      options.add(course.category.trim())
    }
  }

  return [...options]
})

const catalogStats = computed(() => {
  const total = coursesData.value.length
  const mentors = new Set<string>()
  let ratingTotal = 0

  for (const course of coursesData.value) {
    if (course.mentor_name) {
      mentors.add(course.mentor_name.trim())
    }

    ratingTotal += Number(course.rating_avg || 0)
  }

  return {
    total,
    mentorCount: mentors.size,
    categoryCount: categoryOptions.value.length,
    averageRating: total ? ratingTotal / total : 0,
  }
})

const filteredCourses = computed(() => {
  const keyword = normalizeText(searchKeyword.value)
  const level = normalizeText(selectedLevel.value)
  const category = normalizeText(selectedCategory.value)

  const filtered = coursesData.value.filter((course) => {
    const searchableText = normalizeText([
      course.title,
      course.description,
      course.level,
      course.category,
      course.mentor_name,
      (course.tools || []).join(' '),
    ].join(' '))

    const matchesKeyword = !keyword || searchableText.includes(keyword)
    const matchesLevel = level === 'all' || normalizeText(course.level) === level
    const matchesCategory = category === 'all' || normalizeText(course.category) === category

    return matchesKeyword && matchesLevel && matchesCategory
  })

  return [...filtered].sort((left, right) => {
    if (sortBy.value === 'title-asc') {
      return left.title.localeCompare(right.title, 'id')
    }

    if (sortBy.value === 'title-desc') {
      return right.title.localeCompare(left.title, 'id')
    }

    if (right.rating_avg !== left.rating_avg) {
      return right.rating_avg - left.rating_avg
    }

    return left.title.localeCompare(right.title, 'id')
  })
})

const formatRating = (value: number) => Number(value || 0).toFixed(1)

const levelFilterOptions = computed(() => [
  { value: 'all', label: 'Semua level' },
  ...levelOptions.value.map((level) => ({ value: level, label: level })),
])

const categoryFilterOptions = computed(() => [
  { value: 'all', label: 'Semua kategori' },
  ...categoryOptions.value.map((category) => ({ value: category, label: category })),
])

const sortOptions = [
  { value: 'rating', label: 'Rating tertinggi' },
  { value: 'title-asc', label: 'Judul A-Z' },
  { value: 'title-desc', label: 'Judul Z-A' },
] as const

const selectedLevelLabel = computed(
  () => levelFilterOptions.value.find((option) => option.value === selectedLevel.value)?.label || 'Semua level'
)

const selectedCategoryLabel = computed(
  () =>
    categoryFilterOptions.value.find((option) => option.value === selectedCategory.value)?.label ||
    'Semua kategori'
)

const selectedSortLabel = computed(
  () => sortOptions.find((option) => option.value === sortBy.value)?.label || 'Rating tertinggi'
)

const toggleDropdown = (key: 'level' | 'category' | 'sort') => {
  openDropdown.value = openDropdown.value === key ? null : key
}

const closeDropdown = () => {
  openDropdown.value = null
}

const selectLevel = (value: string) => {
  selectedLevel.value = value
  closeDropdown()
}

const selectCategory = (value: string) => {
  selectedCategory.value = value
  closeDropdown()
}

const selectSort = (value: 'rating' | 'title-asc' | 'title-desc') => {
  sortBy.value = value
  closeDropdown()
}

const clearFilters = () => {
  searchKeyword.value = ''
  selectedLevel.value = 'all'
  selectedCategory.value = 'all'
  sortBy.value = 'rating'
  closeDropdown()
}

const previewTools = (course: Course) => (course.tools || []).filter((tool) => tool.trim()).slice(0, 3)

const visibleCountLabel = computed(() => `${filteredCourses.value.length} dari ${coursesData.value.length} course`)

const handleClickOutside = (event: PointerEvent) => {
  const target = event.target as Node | null

  if (!target) {
    return
  }

  const isInsideDropdown =
    levelDropdownRef.value?.contains(target) ||
    categoryDropdownRef.value?.contains(target) ||
    sortDropdownRef.value?.contains(target)

  if (!isInsideDropdown) {
    closeDropdown()
  }
}

const handleEscape = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    closeDropdown()
  }
}

onMounted(() => {
  document.addEventListener('pointerdown', handleClickOutside)
  document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', handleClickOutside)
  document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
  <section class="page-head catalog-head">
    <div class="container catalog-head-grid">
      <div>
        <p class="eyebrow">Katalog Kursus</p>
        <h1 class="page-title">Temukan jalur belajar yang tepat untuk upgrade skill.</h1>
        <p class="page-copy">
          Semua course dirangkum dengan filter cepat supaya kamu bisa pilih materi yang paling relevan,
          dari level dasar sampai project lanjutan.
        </p>
        <div class="catalog-pill-row">
          <span class="catalog-pill">Kurikulum praktis</span>
          <span class="catalog-pill">Mentor berpengalaman</span>
          <span class="catalog-pill">Belajar fleksibel</span>
        </div>
      </div>

      <article class="catalog-highlight">
        <p class="catalog-highlight-label">Ringkasan Katalog</p>
        <div class="catalog-stats-grid">
          <div class="catalog-stat">
            <p class="catalog-stat-value">{{ catalogStats.total }}</p>
            <p class="catalog-stat-label">Total Course</p>
          </div>
          <div class="catalog-stat">
            <p class="catalog-stat-value">{{ catalogStats.mentorCount }}</p>
            <p class="catalog-stat-label">Mentor Aktif</p>
          </div>
          <div class="catalog-stat">
            <p class="catalog-stat-value">{{ catalogStats.categoryCount }}</p>
            <p class="catalog-stat-label">Kategori</p>
          </div>
          <div class="catalog-stat">
            <p class="catalog-stat-value">{{ formatRating(catalogStats.averageRating) }}</p>
            <p class="catalog-stat-label">Rata-rata Rating</p>
          </div>
        </div>
      </article>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="split-row">
        <p class="status-meta">{{ visibleCountLabel }}</p>
        <button class="btn btn-secondary btn-small" type="button" @click="refreshCourses()">
          Refresh katalog
        </button>
      </div>

      <div
        v-if="!coursesPending && !coursesError && coursesData.length"
        class="catalog-toolbar section-spacer-sm"
      >
        <label class="catalog-search-field">
          <span>Cari Course</span>
          <input
            v-model="searchKeyword"
            type="search"
            class="catalog-input"
            placeholder="Cari judul, mentor, kategori, tools..."
          />
        </label>

        <div class="catalog-select-field">
          <span>Level</span>
          <div
            ref="levelDropdownRef"
            class="catalog-dropdown"
            :class="{ 'is-open': openDropdown === 'level' }"
          >
            <button
              type="button"
              class="catalog-dropdown-trigger"
              :aria-expanded="openDropdown === 'level'"
              @click="toggleDropdown('level')"
            >
              <span>{{ selectedLevelLabel }}</span>
              <span class="catalog-dropdown-caret" aria-hidden="true" />
            </button>
            <div v-if="openDropdown === 'level'" class="catalog-dropdown-menu" role="listbox" aria-label="Pilih level course">
              <button
                v-for="option in levelFilterOptions"
                :key="option.value"
                type="button"
                class="catalog-dropdown-option"
                :class="{ 'is-active': selectedLevel === option.value }"
                @click="selectLevel(option.value)"
              >
                {{ option.label }}
              </button>
            </div>
          </div>
        </div>

        <div class="catalog-select-field">
          <span>Kategori</span>
          <div
            ref="categoryDropdownRef"
            class="catalog-dropdown"
            :class="{ 'is-open': openDropdown === 'category' }"
          >
            <button
              type="button"
              class="catalog-dropdown-trigger"
              :aria-expanded="openDropdown === 'category'"
              @click="toggleDropdown('category')"
            >
              <span>{{ selectedCategoryLabel }}</span>
              <span class="catalog-dropdown-caret" aria-hidden="true" />
            </button>
            <div
              v-if="openDropdown === 'category'"
              class="catalog-dropdown-menu"
              role="listbox"
              aria-label="Pilih kategori course"
            >
              <button
                v-for="option in categoryFilterOptions"
                :key="option.value"
                type="button"
                class="catalog-dropdown-option"
                :class="{ 'is-active': selectedCategory === option.value }"
                @click="selectCategory(option.value)"
              >
                {{ option.label }}
              </button>
            </div>
          </div>
        </div>

        <div class="catalog-select-field">
          <span>Urutkan</span>
          <div
            ref="sortDropdownRef"
            class="catalog-dropdown"
            :class="{ 'is-open': openDropdown === 'sort' }"
          >
            <button
              type="button"
              class="catalog-dropdown-trigger"
              :aria-expanded="openDropdown === 'sort'"
              @click="toggleDropdown('sort')"
            >
              <span>{{ selectedSortLabel }}</span>
              <span class="catalog-dropdown-caret" aria-hidden="true" />
            </button>
            <div
              v-if="openDropdown === 'sort'"
              class="catalog-dropdown-menu"
              role="listbox"
              aria-label="Urutkan data course"
            >
              <button
                v-for="option in sortOptions"
                :key="option.value"
                type="button"
                class="catalog-dropdown-option"
                :class="{ 'is-active': sortBy === option.value }"
                @click="selectSort(option.value)"
              >
                {{ option.label }}
              </button>
            </div>
          </div>
        </div>

        <button class="btn btn-secondary btn-small" type="button" @click="clearFilters()">
          Reset filter
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
      <div v-else-if="!filteredCourses.length" class="empty-state catalog-empty">
        <span>Tidak ada course yang cocok dengan filter saat ini.</span>
        <button class="btn btn-secondary btn-small" type="button" @click="clearFilters()">Reset filter</button>
      </div>

      <div v-else class="course-grid catalog-grid section-spacer-sm">
        <NuxtLink
          v-for="course in filteredCourses"
          :key="course.id"
          :to="`/courses/${course.slug}`"
          class="course-card catalog-card"
        >
          <div class="catalog-cover-wrap">
            <img
              :src="course.image_url"
              :alt="`Thumbnail ${course.title}`"
              class="course-cover catalog-cover"
              loading="lazy"
            />
            <span class="catalog-rating-chip">Rating {{ formatRating(course.rating_avg) }}</span>
          </div>

          <div class="catalog-card-head">
            <h3>{{ course.title }}</h3>
            <span class="tag catalog-level">{{ course.level }}</span>
          </div>

          <p class="catalog-submeta">{{ course.category || 'General' }} - {{ course.duration || 'Durasi fleksibel' }}</p>
          <p class="stack-percent">{{ course.price_label }}</p>
          <p class="stack-meta">Mentor: {{ course.mentor_name || 'Tim Mentor' }}</p>
          <p class="catalog-description">{{ course.description || 'Deskripsi akan ditambahkan.' }}</p>

          <div class="catalog-tools">
            <span v-for="tool in previewTools(course)" :key="`${course.id}-${tool}`" class="tag">{{ tool }}</span>
            <span v-if="!(course.tools || []).length" class="tag">Tools segera diumumkan</span>
          </div>
          <p class="course-rating">Lihat detail kursus</p>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>
