import { computed, onMounted, ref, watch } from 'vue'

type DashboardNotification = {
  id: string
  title: string
  message: string
  time: string
  tone: 'success' | 'warning' | 'info'
}

type OverviewMetric = {
  label: string
  value: string
  helper: string
}

type RoleDistribution = {
  key: string
  label: string
  color: string
  percent: number
}

type RoleOverview = {
  title: string
  subtitle: string
  dominantRole: string
  dominantPercent: number
  roleDistribution: RoleDistribution[]
  metrics: OverviewMetric[]
}

type InstructorCourse = {
  title: string
  img: string
  status: string
  students: string
  revenue: string
  category: string
  level: string
  mentor: string
  updatedAt: string
  slug?: string
  description?: string
  modulesCount?: number
  lessonsCount?: number
  completionRate?: number
}

type DashboardPayload = {
  adminStats?: Array<{
    label: string
    value: string
    icon: string
    colorClass: string
    trend: number
  }>
  recentRegistrations?: Array<{
    name: string
    course: string
    time: string
    avatar: string
  }>
  instructorCourses?: InstructorCourse[]
  notifications?: DashboardNotification[]
  studentOverview?: RoleOverview
  instructorOverview?: RoleOverview
}

type DashboardRole = 'admin' | 'instructor' | 'student'
type DashboardMenu = {
  id: string
  label: string
  icon: string
  badge?: string
}

export const useGeminiDashboardState = () => {
  const auth = useAuth()
  const runtimeConfig = useRuntimeConfig()
  const apiBase = runtimeConfig.public.apiBase

  const role = ref<DashboardRole>('admin')
  const isSidebarOpen = ref(false)
  const currentMenu = ref('dashboard')

  const isModalOpen = ref(false)
  const modalType = ref('')
  const toast = ref({ show: false, message: '', type: 'success' as 'success' | 'info' })

  const adminMenus: DashboardMenu[] = [
    { id: 'dashboard', label: 'Ringkasan Analytics', icon: 'ph-duotone ph-squares-four' },
    { id: 'users', label: 'Data Pengguna', icon: 'ph-duotone ph-users', badge: '12' },
    { id: 'transactions', label: 'Verifikasi Transaksi', icon: 'ph-duotone ph-receipt' },
    { id: 'content', label: 'Moderasi Instruktur', icon: 'ph-duotone ph-shield-check', badge: '2' },
    { id: 'settings', label: 'Pengaturan Global', icon: 'ph-duotone ph-gear' },
  ]

  const instructorMenus: DashboardMenu[] = [
    { id: 'dashboard', label: 'Dashboard Mentor', icon: 'ph-duotone ph-squares-four' },
    { id: 'courses', label: 'Kelola Kelas Saya', icon: 'ph-duotone ph-video-camera' },
    { id: 'discussions', label: 'Forum Q&A Kelas', icon: 'ph-duotone ph-chats-circle', badge: '5' },
    { id: 'earnings', label: 'Riwayat Pendapatan', icon: 'ph-duotone ph-wallet' },
    { id: 'profile', label: 'Pengaturan Profil', icon: 'ph-duotone ph-user' },
  ]

  const studentMenus: DashboardMenu[] = [
    { id: 'dashboard', label: 'Ruang Belajar', icon: 'ph-duotone ph-book-open' },
    { id: 'catalog', label: 'Katalog Kelas', icon: 'ph-duotone ph-compass' },
    { id: 'certificates', label: 'Sertifikat Kelulusan', icon: 'ph-duotone ph-seal-check' },
    { id: 'transactions', label: 'Riwayat Pembelian', icon: 'ph-duotone ph-receipt' },
    { id: 'settings', label: 'Pengaturan Akun', icon: 'ph-duotone ph-gear' },
  ]

  const activeMenus = computed<DashboardMenu[]>(() => {
    if (role.value === 'admin') {
      return adminMenus
    }
    if (role.value === 'instructor') {
      return instructorMenus
    }
    return studentMenus
  })

  const currentMenuLabel = computed(() => {
    const menu = activeMenus.value.find((item) => item.id === currentMenu.value)
    return menu ? menu.label : 'Dashboard'
  })

  const toDashboardRole = (value?: string | null): DashboardRole => {
    if (value === 'mentor') {
      return 'instructor'
    }
    if (value === 'student' || value === 'admin' || value === 'instructor') {
      return value
    }
    return role.value
  }

  const roleTextMap: Record<DashboardRole, string> = {
    admin: 'Super Admin',
    instructor: 'Instruktur Expert',
    student: 'Siswa Premium',
  }

  const fallbackUserByRole: Record<DashboardRole, { name: string; roleText: string; avatar: string }> = {
    admin: {
      name: 'Admin Pusat Segara Digital',
      roleText: roleTextMap.admin,
      avatar: 'https://i.pravatar.cc/150?img=11',
    },
    instructor: {
      name: 'Budi Santoso',
      roleText: roleTextMap.instructor,
      avatar: 'https://i.pravatar.cc/150?img=8',
    },
    student: {
      name: 'Andi Kusuma',
      roleText: roleTextMap.student,
      avatar: 'https://i.pravatar.cc/150?img=12',
    },
  }

  const currentUser = computed(() => {
    const authenticatedUser = auth.user.value

    if (!authenticatedUser) {
      return fallbackUserByRole[role.value]
    }

    const normalizedRole = toDashboardRole(authenticatedUser.role)
    const fallback = fallbackUserByRole[normalizedRole]

    return {
      name: authenticatedUser.name || fallback.name,
      roleText: roleTextMap[normalizedRole],
      avatar:
        authenticatedUser.avatar_url ||
        fallback.avatar ||
        `https://i.pravatar.cc/150?img=${(authenticatedUser.id % 70) + 1}`,
    }
  })

  watch(role, () => {
    currentMenu.value = 'dashboard'
  })

  const openModal = (type: string) => {
    modalType.value = type
    isModalOpen.value = true
  }

  const closeModal = () => {
    isModalOpen.value = false
    setTimeout(() => {
      modalType.value = ''
    }, 300)
  }

  const showToast = (message: string, type: 'success' | 'info' = 'success') => {
    toast.value = { show: true, message, type }
    setTimeout(() => {
      toast.value.show = false
    }, 3000)
  }

  const submitModal = (actionName: string) => {
    closeModal()
    showToast(`${actionName} berhasil diproses!`, 'success')
  }

  const adminStats = ref([
    { label: 'Total Pemasukan', value: 'Rp 84.5 Jt', icon: 'ph-fill ph-money', colorClass: 'bg-emerald-50 text-emerald-600', trend: 12 },
    { label: 'Siswa Terdaftar', value: '2,450', icon: 'ph-fill ph-users', colorClass: 'bg-blue-50 text-blue-600', trend: 5 },
    { label: 'Instruktur Aktif', value: '48', icon: 'ph-fill ph-chalkboard-teacher', colorClass: 'bg-purple-50 text-purple-600', trend: -2 },
    { label: 'Kelas Tersedia', value: '150', icon: 'ph-fill ph-play-circle', colorClass: 'bg-orange-50 text-orange-600', trend: 8 },
  ])

  const recentRegistrations = ref([
    { name: 'Diana Fitri', course: 'UI/UX Design System Pro', time: '10 Min lalu', avatar: 'https://i.pravatar.cc/150?img=1' },
    { name: 'Reza Aditya', course: 'Bootcamp Fullstack Reguler', time: '1 Jam lalu', avatar: 'https://i.pravatar.cc/150?img=13' },
    { name: 'Siti Aminah', course: 'Fundamental Golang API', time: '2 Jam lalu', avatar: 'https://i.pravatar.cc/150?img=5' },
    { name: 'Kevin Pratama', course: 'Mastering Nuxt 4 & Vue', time: '4 Jam lalu', avatar: 'https://i.pravatar.cc/150?img=11' },
  ])

  const instructorCourses = ref<InstructorCourse[]>([
    {
      title: 'Mastering PostgreSQL 17 & Redis 7 Caching',
      img: 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=400&q=80',
      status: 'Published',
      students: '800',
      revenue: 'Rp 6.4 Jt',
      category: 'Backend',
      level: 'Lanjutan',
      mentor: 'Budi Santoso',
      updatedAt: '14 Apr 2026',
      slug: 'mastering-postgresql-17-redis-7-caching',
      description: 'Optimasi query, desain indeks, dan strategi cache untuk API bertrafik tinggi.',
      modulesCount: 7,
      lessonsCount: 31,
      completionRate: 82,
    },
    {
      title: 'Golang Microservices & gRPC Architecture',
      img: 'https://images.unsplash.com/photo-1623479322729-28b25c16b011?w=400&q=80',
      status: 'Published',
      students: '420',
      revenue: 'Rp 4.2 Jt',
      category: 'Backend',
      level: 'Lanjutan',
      mentor: 'Budi Santoso',
      updatedAt: '12 Apr 2026',
      slug: 'golang-microservices-grpc-architecture',
      description: 'Rancang service modular dengan tracing, retry policy, dan observability.',
      modulesCount: 8,
      lessonsCount: 36,
      completionRate: 76,
    },
    {
      title: 'Fullstack Web: Nuxt 4 & Laravel 13 API Enterprise',
      img: 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?w=400&q=80',
      status: 'Published',
      students: '1.2k',
      revenue: 'Rp 9.1 Jt',
      category: 'Web Development',
      level: 'Menengah',
      mentor: 'Budi Santoso',
      updatedAt: '11 Apr 2026',
      slug: 'fullstack-web-nuxt-4-laravel-13-api-enterprise',
      description: 'Kelas end-to-end dari frontend SSR sampai backend API production-ready.',
      modulesCount: 12,
      lessonsCount: 58,
      completionRate: 69,
    },
    {
      title: 'Docker Compose & Nginx Reverse Proxy Setup',
      img: 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=400&q=80',
      status: 'Published',
      students: '530',
      revenue: 'Rp 3.8 Jt',
      category: 'DevOps',
      level: 'Menengah',
      mentor: 'Budi Santoso',
      updatedAt: '10 Apr 2026',
      slug: 'docker-compose-nginx-reverse-proxy-setup',
      description: 'Deploy stack frontend-backend menggunakan container dan reverse proxy yang aman.',
      modulesCount: 6,
      lessonsCount: 24,
      completionRate: 74,
    },
    {
      title: 'UI/UX Design System untuk Aplikasi SaaS',
      img: 'https://images.unsplash.com/photo-1586717791821-3f44a563fa4c?w=400&q=80',
      status: 'Published',
      students: '640',
      revenue: 'Rp 4.5 Jt',
      category: 'UI/UX',
      level: 'Pemula',
      mentor: 'Budi Santoso',
      updatedAt: '08 Apr 2026',
      slug: 'ui-ux-design-system-untuk-aplikasi-saas',
      description: 'Bangun komponen reusable, guideline visual, dan workflow kolaborasi tim produk.',
      modulesCount: 9,
      lessonsCount: 40,
      completionRate: 88,
    },
    {
      title: 'Machine Learning with Python & Scikit-Learn',
      img: 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=400&q=80',
      status: 'Draft',
      students: '128',
      revenue: 'Rp 1.1 Jt',
      category: 'Data Science',
      level: 'Menengah',
      mentor: 'Budi Santoso',
      updatedAt: '06 Apr 2026',
      slug: 'machine-learning-with-python-scikit-learn',
      description: 'Pipeline data, feature engineering, dan model evaluasi untuk use case bisnis.',
      modulesCount: 10,
      lessonsCount: 45,
      completionRate: 54,
    },
  ])

  const notifications = ref<DashboardNotification[]>([
    {
      id: 'fallback-1',
      title: 'Notifikasi Dashboard',
      message: 'Notifikasi terbaru akan tampil di sini setelah data API termuat.',
      time: 'Baru saja',
      tone: 'info',
    },
  ])

  const studentOverview = ref<RoleOverview>({
    title: 'Overview Belajar Siswa',
    subtitle: 'Distribusi roadmap berdasarkan aktivitas belajar siswa.',
    dominantRole: 'Frontend Developer',
    dominantPercent: 32,
    roleDistribution: [
      { key: 'frontend', label: 'Frontend Developer', color: '#3B82F6', percent: 32 },
      { key: 'backend', label: 'Backend Developer', color: '#0EA5E9', percent: 24 },
      { key: 'devops', label: 'DevOps Engineer', color: '#10B981', percent: 14 },
      { key: 'mobile', label: 'Mobile Developer', color: '#F97316', percent: 10 },
      { key: 'uiux', label: 'UI/UX Designer', color: '#EC4899', percent: 12 },
      { key: 'data', label: 'Data Scientist', color: '#8B5CF6', percent: 8 },
    ],
    metrics: [
      { label: 'Kelas Aktif', value: '8', helper: 'Kelas yang sedang dipelajari' },
      { label: 'Siswa Aktif', value: '32', helper: 'Siswa dengan aktivitas mingguan' },
      { label: 'Rata-rata Completion', value: '74%', helper: 'Rerata progres lintas kelas' },
      { label: 'Sertifikat Potensial', value: '5', helper: 'Estimasi sertifikat selesai' },
    ],
  })

  const instructorOverview = ref<RoleOverview>({
    title: 'Overview Performa Instruktur',
    subtitle: 'Roadmap paling diminati dari kelas yang Anda ajarkan.',
    dominantRole: 'Backend Developer',
    dominantPercent: 38,
    roleDistribution: [
      { key: 'frontend', label: 'Frontend Developer', color: '#3B82F6', percent: 22 },
      { key: 'backend', label: 'Backend Developer', color: '#0EA5E9', percent: 38 },
      { key: 'devops', label: 'DevOps Engineer', color: '#10B981', percent: 16 },
      { key: 'mobile', label: 'Mobile Developer', color: '#F97316', percent: 8 },
      { key: 'uiux', label: 'UI/UX Designer', color: '#EC4899', percent: 9 },
      { key: 'data', label: 'Data Scientist', color: '#8B5CF6', percent: 7 },
    ],
    metrics: [
      { label: 'Total Siswa', value: '1.2k', helper: 'Akumulasi peserta berbayar' },
      { label: 'Kelas Published', value: '6', helper: 'Kelas aktif di katalog' },
      { label: 'Revenue Kotor', value: 'Rp 22 Jt', helper: 'Total dari transaksi paid' },
      { label: 'Rata-rata/Kelas', value: 'Rp 3.6 Jt', helper: 'Pendapatan rata-rata per kelas' },
    ],
  })

  const loadDashboardPayload = async () => {
    try {
      const payload = await $fetch<DashboardPayload>('/api/gemini/dashboard', {
        baseURL: apiBase,
        headers: auth.authHeaders(),
      })

      if (Array.isArray(payload.adminStats) && payload.adminStats.length > 0) {
        adminStats.value = payload.adminStats
      }

      if (Array.isArray(payload.recentRegistrations) && payload.recentRegistrations.length > 0) {
        recentRegistrations.value = payload.recentRegistrations
      }

      if (Array.isArray(payload.instructorCourses) && payload.instructorCourses.length > 0) {
        instructorCourses.value = payload.instructorCourses
      }

      if (Array.isArray(payload.notifications) && payload.notifications.length > 0) {
        notifications.value = payload.notifications
      }

      if (payload.studentOverview && typeof payload.studentOverview === 'object') {
        studentOverview.value = payload.studentOverview
      }

      if (payload.instructorOverview && typeof payload.instructorOverview === 'object') {
        instructorOverview.value = payload.instructorOverview
      }
    } catch (error) {
      console.warn('Gemini dashboard API fallback ke data lokal.', error)
    }
  }

  onMounted(async () => {
    auth.initFromStorage()
    await auth.ensureSession()
    await loadDashboardPayload()
  })

  return {
    role,
    currentMenu,
    isSidebarOpen,
    activeMenus,
    currentMenuLabel,
    currentUser,
    adminStats,
    recentRegistrations,
    instructorCourses,
    notifications,
    studentOverview,
    instructorOverview,
    isModalOpen,
    modalType,
    toast,
    openModal,
    closeModal,
    showToast,
    submitModal,
    loadDashboardPayload,
  }
}
