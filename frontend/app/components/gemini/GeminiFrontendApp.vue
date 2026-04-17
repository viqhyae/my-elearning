<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    initialPage?: string
    initialCategory?: string
    initialCourseId?: number | null
    initialCourseSlug?: string | null
    loggedIn?: boolean
  }>(),
  {
    initialPage: 'landing',
    initialCategory: '',
    initialCourseId: null,
    initialCourseSlug: null,
    loggedIn: false,
  }
)

const {
  currentPage,
  isLoggedIn,
  isMobileMenuOpen,
  isDemoModalOpen,
  categories,
  categoriesPreview,
  navigate: stateNavigate,
  onMouseMove,
  resetMouse,
  mouseX,
  mouseY,
  stats,
  steps,
  brands,
  allCourses,
  filteredCourses,
  activeCategoryFilter,
  activeLevelFilter,
  searchQuery,
  logout: stateLogout,
  activeRoadmap,
  roadmaps,
  currentRoadmapData,
  timelineProgress,
  testimonials,
  testimonialsRow1,
  testimonialsRow2,
  mentorsList,
  faqs,
  impactStats,
  impactStatsVisible,
  landingStatsVisible,
  webinars,
  jobs,
  blogPosts,
  getStepColorClass,
  getCourseByTitle,
  activeCourse,
  activeJob,
  activePost,
  activeWebinar,
  courseSyllabus,
  featuredAlumni,
} = useGeminiFrontendState()

const auth = useAuth()
const router = useRouter()
const route = useRoute()

const loginEmail = ref('student@elearning.local')
const loginPassword = ref('password123')
const loginLoading = ref(false)
const loginError = ref('')

const registerName = ref('')
const registerEmail = ref('')
const registerPhone = ref('')
const registerRole = ref('student')
const registerPassword = ref('')
const registerPasswordConfirmation = ref('')
const registerAgree = ref(false)
const registerLoading = ref(false)
const registerNotice = ref('')
const registerNoticeTone = ref<'info' | 'error' | 'success'>('info')

const getErrorMessage = (error: unknown, fallback: string) => {
  const maybeError = error as {
    message?: string
    data?: {
      message?: string
      errors?: Record<string, string[] | string>
    }
  }

  if (typeof maybeError?.data?.message === 'string' && maybeError.data.message.trim().length > 0) {
    return maybeError.data.message
  }

  if (maybeError?.data?.errors && typeof maybeError.data.errors === 'object') {
    for (const value of Object.values(maybeError.data.errors)) {
      if (Array.isArray(value) && typeof value[0] === 'string' && value[0].trim().length > 0) {
        return value[0]
      }

      if (typeof value === 'string' && value.trim().length > 0) {
        return value
      }
    }
  }

  if (typeof maybeError?.message === 'string' && maybeError.message.trim().length > 0) {
    return maybeError.message
  }

  return fallback
}

const syncAuthState = () => {
  isLoggedIn.value = Boolean(props.loggedIn || auth.isAuthenticated.value)
}

const navigate = async (page: string, data: unknown = null) => {
  if (page !== 'login') {
    loginError.value = ''
  }

  if (page !== 'register') {
    registerNotice.value = ''
    registerNoticeTone.value = 'info'
  }

  if (page === 'login') {
    stateNavigate('login')
    if (route.path !== '/login') {
      await router.push('/login')
    }
    return
  }

  if (page === 'register') {
    stateNavigate('register')
    if (route.path !== '/register') {
      await router.push('/register')
    }
    return
  }

  if (page === 'dashboard') {
    if (!auth.isAuthenticated.value) {
      stateNavigate('login')
      if (route.path !== '/login') {
        await router.push('/login')
      }
      return
    }

    if (route.path !== '/dashboard') {
      await router.push('/dashboard')
    }
    return
  }

  stateNavigate(page, data)
}

const submitLogin = async () => {
  loginError.value = ''

  if (!loginEmail.value.trim() || !loginPassword.value.trim()) {
    loginError.value = 'Email dan password wajib diisi.'
    return
  }

  loginLoading.value = true

  try {
    const user = await auth.login(loginEmail.value.trim(), loginPassword.value)
    syncAuthState()
    await router.push(auth.defaultPathByRole(user.role))
  } catch (error) {
    loginError.value = getErrorMessage(error, 'Login gagal. Periksa email dan password Anda.')
  } finally {
    loginLoading.value = false
  }
}

const submitRegister = async () => {
  registerNotice.value = ''
  registerNoticeTone.value = 'info'

  if (!registerName.value.trim() || !registerEmail.value.trim() || !registerPassword.value.trim()) {
    registerNotice.value = 'Lengkapi data wajib terlebih dahulu.'
    registerNoticeTone.value = 'error'
    return
  }

  if (!registerAgree.value) {
    registerNotice.value = 'Anda harus menyetujui syarat penggunaan terlebih dahulu.'
    registerNoticeTone.value = 'error'
    return
  }

  if (registerPassword.value.length < 8) {
    registerNotice.value = 'Password minimal 8 karakter.'
    registerNoticeTone.value = 'error'
    return
  }

  if (registerPassword.value !== registerPasswordConfirmation.value) {
    registerNotice.value = 'Konfirmasi password tidak sesuai.'
    registerNoticeTone.value = 'error'
    return
  }

  registerLoading.value = true

  try {
    const user = await auth.register({
      name: registerName.value.trim(),
      email: registerEmail.value.trim(),
      password: registerPassword.value,
      password_confirmation: registerPasswordConfirmation.value,
      role: registerRole.value === 'mentor' ? 'mentor' : 'student',
    })

    registerNoticeTone.value = 'success'
    registerNotice.value = 'Registrasi berhasil. Mengalihkan ke dashboard...'
    syncAuthState()
    await router.push(auth.defaultPathByRole(user.role))
  } catch (error) {
    registerNoticeTone.value = 'error'
    registerNotice.value = getErrorMessage(error, 'Registrasi gagal. Coba lagi beberapa saat.')
  } finally {
    registerLoading.value = false
  }
}

const logout = async () => {
  try {
    await auth.logout()
  } catch {
    // abaikan error network logout dan tetap bersihkan state lokal
  }

  syncAuthState()
  stateLogout()

  if (route.path !== '/') {
    await router.push('/')
  }
}

const toSlug = (value: string) =>
  value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

const resolveInitialCourse = () => {
  if (props.initialCourseId !== null) {
    const byId = allCourses.value.find((course: any) => Number(course.id) === Number(props.initialCourseId))
    if (byId) {
      return byId
    }
  }

  if (props.initialCourseSlug) {
    const slug = toSlug(props.initialCourseSlug)
    const bySlug = allCourses.value.find((course: any) => {
      if (course.slug) {
        return toSlug(String(course.slug)) === slug
      }

      return toSlug(String(course.title || '')) === slug
    })
    if (bySlug) {
      return bySlug
    }
  }

  return allCourses.value[0] || null
}

watch(
  () => props.loggedIn,
  () => {
    syncAuthState()
  }
)

watch(
  () => auth.isAuthenticated.value,
  () => {
    syncAuthState()
  }
)

onMounted(async () => {
  await auth.ensureSession()
  syncAuthState()

  if (!props.initialPage || props.initialPage === 'landing') {
    return
  }

  if (props.initialPage === 'category') {
    const categoryFromQuery = typeof route.query.category === 'string' ? route.query.category : ''
    const initialCategory = categoryFromQuery || props.initialCategory || null
    await navigate('category', initialCategory)
    return
  }

  if (props.initialPage === 'course-detail') {
    const initialCourse = resolveInitialCourse()
    if (initialCourse) {
      await navigate('course-detail', initialCourse)
    }
    return
  }

  await navigate(props.initialPage)
})
</script>

<template>
<div v-cloak class="min-h-screen flex flex-col font-sans antialiased selection:bg-primary-200 selection:text-primary-900 text-slate-800">
    
    <!-- Navbar -->
    <nav class="bg-white/90 backdrop-blur-lg border-b border-slate-200/60 sticky top-0 z-50 transition-all duration-300 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center cursor-pointer group" @click="navigate('landing')">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl bg-gradient-to-tr from-slate-900 to-slate-700 flex items-center justify-center text-white mr-3 shadow-lg group-hover:scale-105 transition-transform duration-300">
                        <i class="ph-bold ph-code text-xl md:text-2xl"></i>
                    </div>
                    <span class="font-extrabold text-xl md:text-2xl tracking-tight text-slate-900">EduTech.</span>
                </div>
                
                <div class="hidden lg:flex items-center space-x-1">
                    <template v-if="!isLoggedIn">
                        <a href="#" @click.prevent="navigate('landing')" class="px-3 xl:px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-300" :class="currentPage === 'landing' ? 'bg-slate-100 text-slate-900' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50'">Beranda</a>
                        
                        <div class="relative group cursor-pointer">
                            <button class="px-3 xl:px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 text-slate-500 hover:text-slate-900 hover:bg-slate-50 flex items-center" :class="['bootcamp', 'category', 'roadmap', 'webinar', 'course-detail'].includes(currentPage) ? 'bg-slate-100 text-slate-900' : ''">
                                Program <i class="ph-bold ph-caret-down ml-1 text-xs transition-transform group-hover:rotate-180"></i>
                            </button>
                            <div class="absolute left-0 top-full pt-2 w-56 invisible opacity-0 translate-y-2 group-hover:visible group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 z-50">
                                <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-2">
                                    <a href="#" @click.prevent="navigate('bootcamp')" class="block px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary-600 rounded-xl transition-colors">Bootcamp Premium</a>
                                    <a href="#" @click.prevent="navigate('category')" class="block px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary-600 rounded-xl transition-colors">Katalog Kelas</a>
                                    <a href="#" @click.prevent="navigate('roadmap')" class="block px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary-600 rounded-xl transition-colors">Roadmap Karir</a>
                                    <a href="#" @click.prevent="navigate('webinar')" class="block px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary-600 rounded-xl transition-colors">Webinar & Event</a>
                                </div>
                            </div>
                        </div>

                        <div class="relative group cursor-pointer">
                            <button class="px-3 xl:px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 text-slate-500 hover:text-slate-900 hover:bg-slate-50 flex items-center" :class="['about', 'mentors', 'career', 'blog', 'job-detail', 'blog-detail'].includes(currentPage) ? 'bg-slate-100 text-slate-900' : ''">
                                Perusahaan <i class="ph-bold ph-caret-down ml-1 text-xs transition-transform group-hover:rotate-180"></i>
                            </button>
                            <div class="absolute left-0 top-full pt-2 w-56 invisible opacity-0 translate-y-2 group-hover:visible group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 z-50">
                                <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-2">
                                    <a href="#" @click.prevent="navigate('about')" class="block px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary-600 rounded-xl transition-colors">Tentang Kami</a>
                                    <a href="#" @click.prevent="navigate('mentors')" class="block px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary-600 rounded-xl transition-colors">Instruktur</a>
                                    <a href="#" @click.prevent="navigate('career')" class="flex justify-between items-center px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary-600 rounded-xl transition-colors">Karir <span class="bg-primary-100 text-primary-600 text-[9px] px-2 py-0.5 rounded-full font-bold">HIRING</span></a>
                                    <a href="#" @click.prevent="navigate('blog')" class="block px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary-600 rounded-xl transition-colors">Blog & Artikel</a>
                                </div>
                            </div>
                        </div>

                        <a href="#" @click.prevent="navigate('testimonials')" class="px-3 xl:px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-300" :class="currentPage === 'testimonials' ? 'bg-slate-100 text-slate-900' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50'">Testimoni</a>
                        <a href="#" @click.prevent="navigate('cek-sertifikat')" class="px-3 xl:px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 flex items-center text-primary-600 hover:bg-primary-50" :class="currentPage === 'cek-sertifikat' ? 'bg-primary-50' : ''"><i class="ph-bold ph-seal-check mr-1.5 text-lg"></i> Cek Sertifikat</a>
                        
                        <div class="flex items-center space-x-3 border-l-2 pl-4 ml-2 xl:pl-6 xl:ml-3 border-slate-100">
                            <button @click="navigate('login')" class="text-sm font-bold text-slate-600 hover:text-slate-900 px-3 py-2 transition">Masuk</button>
                            <button @click="navigate('register')" class="text-sm font-bold bg-primary-600 text-white px-5 py-2.5 rounded-xl hover:bg-primary-700 transition-colors shadow-lg shadow-primary-500/30 hover:-translate-y-0.5 duration-300">Daftar Gratis</button>
                        </div>
                    </template>
                    <template v-else>
                        <a href="#" @click.prevent="navigate('landing')" class="px-4 py-2 rounded-xl text-sm font-bold transition-all duration-300" :class="currentPage === 'landing' ? 'bg-slate-100 text-slate-900' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50'">Beranda</a>
                        <a href="#" @click.prevent="navigate('dashboard')" class="px-4 py-2 rounded-xl text-sm font-bold transition-all duration-300" :class="currentPage === 'dashboard' ? 'bg-slate-100 text-slate-900' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50'">Dashboard Belajar</a>
                        
                        <div class="relative group cursor-pointer">
                            <button class="px-4 py-2 rounded-xl text-sm font-bold transition-all duration-300 text-slate-500 hover:text-slate-900 hover:bg-slate-50 flex items-center" :class="['bootcamp', 'category', 'roadmap', 'course-detail'].includes(currentPage) ? 'bg-slate-100 text-slate-900' : ''">
                                Eksplorasi <i class="ph-bold ph-caret-down ml-1 text-xs transition-transform group-hover:rotate-180"></i>
                            </button>
                            <div class="absolute left-0 top-full pt-2 w-48 invisible opacity-0 translate-y-2 group-hover:visible group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 z-50">
                                <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-2">
                                    <a href="#" @click.prevent="navigate('category')" class="block px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary-600 rounded-xl transition-colors">Katalog Kelas</a>
                                    <a href="#" @click.prevent="navigate('roadmap')" class="block px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary-600 rounded-xl transition-colors">Roadmap Karir</a>
                                    <a href="#" @click.prevent="navigate('bootcamp')" class="block px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary-600 rounded-xl transition-colors">Bootcamp</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ml-6 pl-6 border-l-2 border-slate-100 flex items-center relative group cursor-pointer">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold mr-3 shadow-md">BS</div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-slate-900">Budi Santoso</span>
                                    <span class="text-[10px] text-slate-500">Siswa Premium</span>
                                </div>
                                <i class="ph-bold ph-caret-down ml-3 text-slate-400"></i>
                            </div>
                            <div class="absolute right-0 top-full mt-2 w-48 bg-white rounded-2xl shadow-xl border border-slate-100 p-2 invisible opacity-0 translate-y-2 group-hover:visible group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 z-50">
                                <a href="#" class="block px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 rounded-xl">Profil Saya</a>
                                <a href="#" @click.prevent="navigate('cek-sertifikat')" class="block px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 rounded-xl">Sertifikat</a>
                                <div class="border-t border-slate-100 my-1"></div>
                                <button @click="logout" class="w-full text-left px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 rounded-xl flex items-center"><i class="ph-bold ph-sign-out mr-2"></i> Keluar</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex items-center lg:hidden">
                    <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="text-slate-600 p-2 hover:bg-slate-100 rounded-xl transition">
                        <i :class="isMobileMenuOpen ? 'ph-bold ph-x' : 'ph-bold ph-list'" class="text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu Panel -->
    <div v-if="isMobileMenuOpen" class="lg:hidden bg-white/95 backdrop-blur-2xl border-b border-slate-200 p-6 shadow-2xl z-40 absolute w-full top-20 animate-fade-down flex flex-col space-y-1 h-[calc(100vh-80px)] overflow-y-auto">
        <template v-if="!isLoggedIn">
            <a href="#" @click.prevent="navigate('landing')" class="block font-bold text-base py-3 px-4 rounded-xl hover:bg-slate-50 text-slate-800">Beranda</a>
            
            <div class="px-4 pt-4 pb-2 text-[10px] font-black text-slate-400 uppercase tracking-widest">Program Belajar</div>
            <a href="#" @click.prevent="navigate('bootcamp')" class="block font-bold text-base py-2.5 px-4 rounded-xl hover:bg-slate-50 text-slate-600">Bootcamp Premium</a>
            <a href="#" @click.prevent="navigate('category')" class="block font-bold text-base py-2.5 px-4 rounded-xl hover:bg-slate-50 text-slate-600">Katalog Kelas</a>
            <a href="#" @click.prevent="navigate('roadmap')" class="block font-bold text-base py-2.5 px-4 rounded-xl hover:bg-slate-50 text-slate-600">Roadmap Karir</a>
            <a href="#" @click.prevent="navigate('webinar')" class="block font-bold text-base py-2.5 px-4 rounded-xl hover:bg-slate-50 text-slate-600">Webinar & Event</a>
            
            <div class="px-4 pt-4 pb-2 text-[10px] font-black text-slate-400 uppercase tracking-widest">Perusahaan</div>
            <a href="#" @click.prevent="navigate('about')" class="block font-bold text-base py-2.5 px-4 rounded-xl hover:bg-slate-50 text-slate-600">Tentang Kami</a>
            <a href="#" @click.prevent="navigate('mentors')" class="block font-bold text-base py-2.5 px-4 rounded-xl hover:bg-slate-50 text-slate-600">Instruktur</a>
            <a href="#" @click.prevent="navigate('career')" class="block font-bold text-base py-2.5 px-4 rounded-xl hover:bg-slate-50 text-slate-600 flex justify-between items-center">Karir EduTech <span class="text-[9px] bg-primary-100 text-primary-600 px-2 py-1 rounded-md">HIRING</span></a>
            <a href="#" @click.prevent="navigate('blog')" class="block font-bold text-base py-2.5 px-4 rounded-xl hover:bg-slate-50 text-slate-600">Blog & Artikel</a>
            
            <div class="px-4 pt-4 pb-2 text-[10px] font-black text-slate-400 uppercase tracking-widest">Lainnya</div>
            <a href="#" @click.prevent="navigate('testimonials')" class="block font-bold text-base py-2.5 px-4 rounded-xl hover:bg-slate-50 text-slate-600">Testimoni Alumni</a>
            <a href="#" @click.prevent="navigate('cek-sertifikat')" class="block font-bold text-base py-2.5 px-4 rounded-xl hover:bg-slate-50 text-primary-600">Cek Sertifikat</a>
            <a href="#" @click.prevent="navigate('faq')" class="block font-bold text-base py-2.5 px-4 rounded-xl hover:bg-slate-50 text-slate-600">FAQ Bantuan</a>

            <hr class="my-4 border-slate-200">
            <button @click="navigate('login')" class="w-full py-4 border-2 border-slate-200 rounded-xl font-bold text-slate-700 hover:bg-slate-50 transition">Masuk Akun</button>
            <button @click="navigate('register')" class="w-full py-4 bg-primary-600 text-white rounded-xl font-bold shadow-lg shadow-primary-500/30 transition mb-6">Daftar Gratis</button>
        </template>
        <template v-else>
            <a href="#" @click.prevent="navigate('dashboard')" class="block font-bold text-base py-3 px-4 rounded-xl hover:bg-slate-50 text-slate-800">Dashboard Belajar</a>
            <a href="#" @click.prevent="navigate('roadmap')" class="block font-bold text-base py-3 px-4 rounded-xl hover:bg-slate-50 text-slate-800">Roadmap Karir</a>
            <a href="#" @click.prevent="navigate('category')" class="block font-bold text-base py-3 px-4 rounded-xl hover:bg-slate-50 text-slate-800">Eksplorasi Kelas</a>
            <a href="#" @click.prevent="navigate('cek-sertifikat')" class="block font-bold text-base py-3 px-4 rounded-xl hover:bg-slate-50 text-slate-800">Sertifikat Saya</a>
            <hr class="my-4 border-slate-200">
            <button @click="logout" class="w-full py-4 bg-red-50 text-red-600 rounded-xl font-bold flex justify-center items-center mb-6"><i class="ph-bold ph-sign-out mr-2"></i> Keluar Akun</button>
        </template>
    </div>

    <main class="flex-grow flex flex-col relative w-full">
        
        <!-- ==============================
             1. VIEW: LANDING PAGE
        =============================== -->
        <div v-if="currentPage === 'landing'" class="flex-grow flex flex-col animate-fade">
            <section @mousemove="onMouseMove" @mouseleave="resetMouse" class="bg-white pt-16 pb-24 relative overflow-hidden flex flex-col justify-center min-h-[85vh]">
                <div class="absolute inset-0 z-0 pointer-events-none overflow-hidden">
                    <div class="absolute top-0 right-0 w-[600px] md:w-[800px] h-[600px] md:h-[800px] bg-primary-50/50 rounded-full blur-3xl opacity-70 mix-blend-multiply animate-blob"></div>
                    <div class="absolute top-0 left-[-100px] w-[500px] h-[500px] bg-purple-50/50 rounded-full blur-3xl opacity-70 mix-blend-multiply animate-blob animation-delay-2000"></div>
                    
                    <div class="parallax-wrapper absolute inset-0" :style="{ transform: `translate(${mouseX * 60}px, ${mouseY * 40}px)` }">
                        <div class="absolute top-[15%] left-[10%] lg:left-[15%] animate-float">
                            <div class="w-12 h-12 md:w-14 md:h-14 bg-white border border-slate-100 rounded-2xl shadow-xl shadow-emerald-500/10 flex items-center justify-center text-emerald-500 -rotate-6">
                                <i class="ph-bold ph-code-block text-2xl md:text-3xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="parallax-wrapper absolute inset-0" :style="{ transform: `translate(${mouseX * -40}px, ${mouseY * -50}px)` }">
                        <div class="absolute bottom-[20%] right-[8%] lg:right-[15%] animate-float-delayed">
                            <div class="w-14 h-14 md:w-16 md:h-16 bg-white border border-slate-100 rounded-2xl shadow-xl shadow-purple-500/10 flex items-center justify-center text-purple-500 rotate-12">
                                <i class="ph-bold ph-database text-2xl md:text-3xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="parallax-wrapper absolute inset-0 hidden md:block" :style="{ transform: `translate(${mouseX * -70}px, ${mouseY * 30}px)` }">
                        <div class="absolute top-[25%] right-[20%] animate-float-slow">
                            <div class="w-12 h-12 md:w-14 md:h-14 bg-white border border-slate-100 rounded-full shadow-lg flex items-center justify-center text-blue-500 rotate-12">
                                <i class="ph-bold ph-cloud-check text-xl md:text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="parallax-wrapper absolute inset-0 hidden md:block" :style="{ transform: `translate(${mouseX * 40}px, ${mouseY * -20}px)` }">
                        <div class="absolute bottom-[30%] left-[18%] animate-float-fast">
                            <div class="w-10 h-10 md:w-12 md:h-12 bg-white border border-slate-100 rounded-[1rem] shadow-lg flex items-center justify-center text-pink-500 -rotate-12">
                                <i class="ph-bold ph-paint-brush text-xl md:text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="parallax-wrapper absolute inset-0 hidden lg:block" :style="{ transform: `translate(${mouseX * -20}px, ${mouseY * 60}px)` }">
                        <div class="absolute top-[50%] left-[5%] animate-float-delayed">
                            <div class="w-10 h-10 bg-white border border-slate-100 rounded-lg shadow-md flex items-center justify-center text-orange-500 rotate-45">
                                <i class="ph-bold ph-device-mobile text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="parallax-wrapper absolute inset-0 hidden lg:block" :style="{ transform: `translate(${mouseX * 50}px, ${mouseY * -30}px)` }">
                        <div class="absolute top-[60%] right-[10%] animate-float">
                            <div class="w-10 h-10 bg-white border border-slate-100 rounded-full shadow-md flex items-center justify-center text-yellow-500 -rotate-12">
                                <i class="ph-fill ph-lightning text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
                    <div class="inline-flex items-center py-1.5 px-4 rounded-full bg-slate-50 text-slate-700 text-xs font-bold mb-6 md:mb-8 border border-slate-200 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-primary-500 mr-2.5 animate-pulse"></span>
                        Platform LMS Stack Modern #1 di Indonesia
                    </div>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-slate-900 mb-6 md:mb-8 leading-[1.15] tracking-tight">
                        Bangun Karir Impian <br class="hidden md:block">
                        Dengan <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-600 via-blue-500 to-purple-600">Skill Industri Nyata</span>
                    </h1>
                    <p class="text-slate-500 max-w-2xl mx-auto mb-10 text-base md:text-lg leading-relaxed font-medium">
                        Berhenti menonton tutorial tanpa arah. Kuasai ekosistem Nuxt, Laravel, Docker, dan DevOps dengan praktik langsung standar perusahaan teknologi.
                    </p>
                    <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                        <button @click="navigate(isLoggedIn ? 'dashboard' : 'category')" class="w-full sm:w-auto px-8 py-3.5 md:py-4 bg-slate-900 text-white rounded-2xl font-bold text-base md:text-lg hover:-translate-y-1 hover:shadow-xl hover:shadow-slate-900/30 transition-all active:scale-95">
                            Eksplorasi Katalog
                        </button>
                        <button @click="navigate('roadmap')" class="w-full sm:w-auto px-8 py-3.5 md:py-4 bg-white border-2 border-slate-200 text-slate-700 rounded-2xl font-bold text-base md:text-lg hover:border-slate-300 hover:bg-slate-50 transition-all active:scale-95 flex items-center justify-center group">
                            <i class="ph-bold ph-map-pin-line text-xl mr-2 text-primary-600 group-hover:scale-110 transition-transform"></i> Lihat Roadmap
                        </button>
                    </div>
                    
                    <div class="mt-16 md:mt-20 grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 max-w-3xl mx-auto">
                        <div v-for="(stat, index) in stats" :key="index" class="text-center p-2 bg-white/40 backdrop-blur-sm rounded-2xl border border-white shadow-sm">
                            <div class="text-2xl md:text-3xl font-black text-slate-900 mb-1 flex justify-center items-end tracking-tighter">
                                <span class="slot-wrap"><span class="slot-col slot-roll" :style="{ animationDuration: stat.duration }">
                                    <span v-for="val in stat.values" :key="val">{{ val }}</span>
                                </span></span>
                                <span class="text-sm md:text-base text-slate-500 ml-1 mb-0.5">{{ stat.suffix }}</span>
                            </div>
                            <div class="text-[10px] md:text-xs font-bold text-slate-400 uppercase tracking-widest">{{ stat.label }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-10 bg-white border-t border-slate-100 overflow-hidden relative">
                <div class="absolute left-0 top-0 w-24 md:w-40 h-full bg-gradient-to-r from-white to-transparent z-10 pointer-events-none"></div>
                <div class="absolute right-0 top-0 w-24 md:w-40 h-full bg-gradient-to-l from-white to-transparent z-10 pointer-events-none"></div>
                <div class="max-w-7xl mx-auto px-4 text-center mb-8 relative z-20">
                    <p class="text-[10px] md:text-[11px] font-bold text-slate-400 uppercase tracking-[0.2em]">Alumni kami dipercaya oleh raksasa teknologi & startup</p>
                </div>
                <div class="flex overflow-hidden w-full group">
                    <div class="flex whitespace-nowrap animate-marquee items-center group-hover:[animation-play-state:paused]">
                        <template v-for="i in 3" :key="i">
                            <div v-for="brand in brands" :key="brand.name + i" class="mx-8 md:mx-12 grayscale opacity-40 hover:grayscale-0 hover:opacity-100 transition-all duration-300 cursor-pointer flex items-center justify-center">
                                <img :src="brand.img" :alt="brand.name" class="h-6 md:h-8 object-contain max-w-[100px] md:max-w-[130px]">
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <section class="py-20 bg-slate-50 relative border-t border-slate-200/50">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="text-center max-w-2xl mx-auto mb-12">
                        <h2 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight mb-3">Topik Terpopuler</h2>
                        <p class="text-slate-500 text-base">Pilih spesialisasi yang ingin Anda kuasai dan mulai pelajari sekarang.</p>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
                        <div v-for="(cat, idx) in categoriesPreview" :key="idx" @click="navigate('category', cat.name)" class="bg-white p-6 rounded-[1.5rem] border border-slate-200 hover:border-primary-300 hover:shadow-lg transition-all duration-300 cursor-pointer group text-center flex flex-col items-center">
                            <div class="relative w-16 h-16 flex items-center justify-center">
                                <i :class="[cat.icon, cat.color, cat.hoverColor, cat.hoverAnim]" class="text-5xl md:text-6xl mb-5 transition-all duration-300 absolute"></i>
                            </div>
                            <h3 class="font-bold text-slate-900 text-sm md:text-base mt-2 mb-1">{{ cat.name }}</h3>
                            <p class="text-xs text-slate-400 font-medium">{{ cat.count }} Kelas</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-20 bg-white relative">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="text-center max-w-3xl mx-auto mb-14">
                        <span class="text-primary-600 font-bold text-xs uppercase tracking-widest mb-3 block">Benefit Platform</span>
                        <h2 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight mb-4">Mengapa Memilih EduTech?</h2>
                        <p class="text-slate-500 text-base">Sistem belajar yang didesain agar Anda lebih cepat paham dan siap kerja.</p>
                    </div>
                    <div class="grid md:grid-cols-3 gap-6 md:gap-8">
                        <div class="bg-slate-50 p-8 md:p-10 rounded-[2rem] border border-slate-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="w-14 h-14 bg-white text-blue-600 rounded-2xl flex items-center justify-center text-3xl mb-6 shadow-sm"><i class="ph-fill ph-projector-screen"></i></div>
                            <h3 class="text-lg md:text-xl font-bold mb-2 text-slate-900">Kurikulum Proyek Nyata</h3>
                            <p class="text-slate-500 leading-relaxed text-sm">Setiap modul diakhiri dengan pembuatan proyek nyata yang bisa langsung dijadikan portofolio untuk melamar kerja.</p>
                        </div>
                        <div class="bg-slate-50 p-8 md:p-10 rounded-[2rem] border border-slate-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="w-14 h-14 bg-white text-purple-600 rounded-2xl flex items-center justify-center text-3xl mb-6 shadow-sm"><i class="ph-fill ph-chats-circle"></i></div>
                            <h3 class="text-lg md:text-xl font-bold mb-2 text-slate-900">Komunitas & Diskusi</h3>
                            <p class="text-slate-500 leading-relaxed text-sm">Tidak perlu pusing saat stuck. Diskusikan error langsung dengan instruktur dan sesama siswa di forum eksklusif.</p>
                        </div>
                        <div class="bg-slate-50 p-8 md:p-10 rounded-[2rem] border border-slate-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="w-14 h-14 bg-white text-emerald-600 rounded-2xl flex items-center justify-center text-3xl mb-6 shadow-sm"><i class="ph-fill ph-infinity"></i></div>
                            <h3 class="text-lg md:text-xl font-bold mb-2 text-slate-900">Akses Tanpa Batas</h3>
                            <p class="text-slate-500 leading-relaxed text-sm">Bayar sekali, kelas menjadi milik Anda selamanya. Dapatkan juga pembaruan (update) materi teknis secara gratis.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-20 bg-slate-900 text-white relative">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="flex flex-col md:flex-row justify-between items-end mb-12">
                        <div class="max-w-2xl">
                            <h2 class="text-3xl md:text-4xl font-black tracking-tight mb-3">Kelas Terfavorit Bulan Ini</h2>
                            <p class="text-slate-400 text-base">Mulailah dengan kelas yang paling banyak membantu alumni kami mendapatkan pekerjaan.</p>
                        </div>
                        <button @click="navigate('category')" class="mt-6 md:mt-0 px-6 py-3 bg-white/10 border border-white/20 rounded-xl font-bold text-white hover:bg-white/20 transition flex items-center shrink-0 text-sm">
                            Lihat Katalog <i class="ph-bold ph-arrow-right ml-2"></i>
                        </button>
                    </div>

                    <div class="grid md:grid-cols-3 gap-6">
                        <div v-for="course in allCourses.slice(0,3)" :key="course.id" @click="navigate('course-detail', course)" class="bg-slate-800 rounded-[1.5rem] overflow-hidden border border-slate-700 hover:border-primary-500 hover:shadow-xl hover:shadow-primary-500/20 hover:-translate-y-1 transition-all duration-300 cursor-pointer group flex flex-col">
                            <div class="relative aspect-video overflow-hidden bg-slate-700">
                                <img :src="course.image" :alt="course.title" class="w-full h-full object-cover opacity-90 group-hover:scale-105 group-hover:opacity-100 transition-all duration-700">
                                <div class="absolute top-3 left-3 bg-black/60 backdrop-blur-sm text-white px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider border border-white/10">{{ course.category }}</div>
                            </div>
                            <div class="p-5 flex flex-col flex-grow">
                                <div class="flex justify-between items-center text-xs font-bold text-slate-400 mb-3">
                                    <span class="flex items-center text-yellow-400"><i class="ph-fill ph-star mr-1"></i> {{ course.rating }}</span>
                                    <span class="flex items-center"><i class="ph-fill ph-users mr-1"></i> {{ course.students }}</span>
                                </div>
                                <h3 class="text-lg font-bold text-white mb-4 group-hover:text-primary-400 transition-colors line-clamp-2 leading-snug">{{ course.title }}</h3>
                                <div class="mt-auto pt-4 border-t border-slate-700 flex justify-between items-center">
                                    <div class="flex items-center">
                                        <div class="w-6 h-6 rounded-full bg-slate-700 flex items-center justify-center font-bold text-[8px] mr-2 text-white">{{ course.instructorInitials }}</div>
                                        <span class="text-xs font-semibold text-slate-300 truncate max-w-[100px]">{{ course.instructor }}</span>
                                    </div>
                                    <span class="font-black text-lg text-primary-400">{{ course.price }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-20 bg-slate-50 relative overflow-hidden border-b border-slate-200">
                <div class="max-w-7xl mx-auto px-4 text-center mb-14 relative z-10">
                    <h2 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight mb-3">Langkah Tepat Menuju Karir</h2>
                    <p class="text-slate-500 text-base max-w-2xl mx-auto">Kami merancang alur sukses untuk Anda dari titik nol hingga siap kerja.</p>
                </div>
                
                <div class="max-w-6xl mx-auto px-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 relative z-10">
                    <div class="text-center group bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:-translate-y-1 hover:shadow-xl hover:shadow-primary-500/10 hover:border-primary-200 transition-all duration-300 flex flex-col items-center" v-for="(step, idx) in steps" :key="idx">
                        <div class="w-16 h-16 bg-slate-50 rounded-xl flex items-center justify-center text-3xl mb-4 transition-all duration-300 group-hover:scale-110 group-hover:bg-primary-50" :class="step.color">
                            <i :class="step.icon"></i>
                        </div>
                        <h4 class="text-base font-bold text-slate-900 mb-2">{{ step.title }}</h4>
                        <p class="text-xs text-slate-500 leading-relaxed">{{ step.desc }}</p>
                    </div>
                </div>
                
                <div class="text-center mt-10">
                    <button @click="navigate('roadmap')" class="text-sm font-bold text-primary-600 hover:text-primary-700 hover:underline">Pelajari Roadmap Karir Detail &rarr;</button>
                </div>
            </section>

            <section class="py-20 bg-white relative z-10 border-b border-slate-100">
                <div class="max-w-5xl mx-auto px-4">
                    <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-[2.5rem] p-10 md:p-16 text-center text-white shadow-2xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-[300px] h-[300px] bg-primary-500 opacity-20 rounded-full blur-3xl translate-x-1/2 -translate-y-1/2"></div>
                        <div class="relative z-20">
                            <h2 class="text-3xl md:text-5xl font-black mb-4 tracking-tight leading-tight">Mulai Karir Tech Anda <span class="text-primary-400">Sekarang</span></h2>
                            <p class="text-slate-300 text-base md:text-lg mb-8 max-w-2xl mx-auto font-medium">Berhenti menunda. Bergabung dengan talenta lainnya dan wujudkan impian menjadi developer handal.</p>
                            <div class="flex flex-col sm:flex-row justify-center gap-4">
                                <button @click="navigate('register')" class="px-8 py-3.5 bg-primary-600 text-white rounded-xl font-bold text-base hover:bg-primary-700 transition shadow-xl hover:-translate-y-1 active:scale-95">Daftar Akun Gratis</button>
                                <button @click="navigate('roadmap')" class="px-8 py-3.5 bg-white/10 border border-white/20 text-white rounded-xl font-bold text-base hover:bg-white/20 transition active:scale-95">Lihat Roadmap Karir</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- ==============================
             2. VIEW: KATALOG KELAS
        =============================== -->
        <div v-else-if="currentPage === 'category'" class="flex-grow bg-slate-50 py-12 animate-fade">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-5 mb-8">
                    <div>
                        <span class="inline-flex items-center py-1.5 px-4 rounded-full bg-primary-100 text-primary-700 text-xs font-bold mb-4 border border-primary-200">
                            Katalog EduTech
                        </span>
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight mb-2">Temukan Kelas Sesuai Target Karir</h1>
                        <p class="text-slate-500 text-sm md:text-base max-w-2xl">Filter kelas berdasarkan kategori, level, dan kata kunci untuk menemukan materi paling relevan.</p>
                    </div>
                    <div class="text-xs font-bold uppercase tracking-widest text-slate-400 bg-white border border-slate-200 rounded-xl px-4 py-2.5 w-max">
                        {{ filteredCourses.length }} Kelas Tersedia
                    </div>
                </div>

                <div class="grid lg:grid-cols-4 gap-6 lg:gap-8">
                    <aside class="lg:col-span-1">
                        <div class="bg-white rounded-[1.5rem] border border-slate-200 shadow-sm p-5 md:p-6 sticky top-24">
                            <div class="mb-6">
                                <p class="text-xs font-black text-slate-400 uppercase tracking-wider mb-2">Cari Kelas</p>
                                <div class="relative">
                                    <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <input v-model="searchQuery" type="text" placeholder="Contoh: Laravel, Docker..." class="w-full pl-10 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:bg-white focus:border-primary-400 focus:ring-2 focus:ring-primary-500/20 transition-all">
                                </div>
                            </div>

                            <div class="mb-6">
                                <p class="text-xs font-black text-slate-400 uppercase tracking-wider mb-2">Kategori</p>
                                <div class="space-y-2">
                                    <button @click="activeCategoryFilter = 'Semua Kategori'" :class="activeCategoryFilter === 'Semua Kategori' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-bold border transition-colors">
                                        Semua Kategori
                                    </button>
                                    <button v-for="cat in categories" :key="cat" @click="activeCategoryFilter = cat" :class="activeCategoryFilter === cat ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-bold border transition-colors">
                                        {{ cat }}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-black text-slate-400 uppercase tracking-wider mb-2">Level</p>
                                <div class="space-y-2">
                                    <button @click="activeLevelFilter = 'Semua Level'" :class="activeLevelFilter === 'Semua Level' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-bold border transition-colors">
                                        Semua Level
                                    </button>
                                    <button v-for="level in ['Pemula', 'Menengah', 'Lanjutan']" :key="level" @click="activeLevelFilter = level" :class="activeLevelFilter === level ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-bold border transition-colors">
                                        {{ level }}
                                    </button>
                                </div>
                            </div>

                            <button @click="activeCategoryFilter = 'Semua Kategori'; activeLevelFilter = 'Semua Level'; searchQuery = ''" class="w-full mt-6 px-4 py-2.5 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200 transition">
                                Reset Semua Filter
                            </button>
                        </div>
                    </aside>

                    <div class="lg:col-span-3">
                        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
                            <article v-for="course in filteredCourses" :key="course.id" @click="navigate('course-detail', course)" class="bg-white rounded-[1.5rem] overflow-hidden border border-slate-200 hover:border-primary-300 hover:shadow-xl transition-all duration-300 cursor-pointer group flex flex-col">
                                <div class="aspect-video overflow-hidden relative bg-slate-100">
                                    <img :src="course.image" :alt="course.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                    <span class="absolute top-3 left-3 bg-white/90 backdrop-blur text-slate-900 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider border border-white/50 shadow-sm">
                                        {{ course.category }}
                                    </span>
                                </div>
                                <div class="p-5 flex flex-col flex-grow">
                                    <div class="flex items-center justify-between text-xs font-bold text-slate-400 mb-3">
                                        <span class="inline-flex items-center bg-slate-50 border border-slate-200 px-2 py-1 rounded-md text-[10px] text-slate-600">{{ course.level }}</span>
                                        <span class="flex items-center text-yellow-500"><i class="ph-fill ph-star mr-1"></i> {{ course.rating }}</span>
                                    </div>
                                    <h3 class="text-base font-bold text-slate-900 mb-3 line-clamp-2 leading-snug group-hover:text-primary-600 transition-colors">{{ course.title }}</h3>
                                    <div class="flex items-center text-xs font-semibold text-slate-500 mb-4">
                                        <div class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-[9px] font-bold mr-2 border border-slate-200">{{ course.instructorInitials }}</div>
                                        <span class="truncate">{{ course.instructor }}</span>
                                    </div>
                                    <div class="mt-auto pt-4 border-t border-slate-100 flex justify-between items-center">
                                        <span class="text-xs font-bold text-slate-500 flex items-center"><i class="ph-fill ph-users mr-1.5"></i> {{ course.students }}</span>
                                        <span class="font-black text-lg text-primary-600">{{ course.price }}</span>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div v-if="filteredCourses.length === 0" class="text-center py-20 bg-white rounded-[2rem] border border-slate-200 border-dashed mt-4 shadow-sm">
                            <i class="ph-duotone ph-magnifying-glass text-4xl text-slate-300 mb-3 inline-block"></i>
                            <h3 class="text-lg font-bold text-slate-700">Kelas tidak ditemukan</h3>
                            <p class="text-slate-500 text-sm mt-1">Sesuaikan kata kunci atau bersihkan filter.</p>
                            <button @click="activeCategoryFilter = 'Semua Kategori'; activeLevelFilter = 'Semua Level'; searchQuery=''" class="mt-4 px-5 py-2 bg-slate-900 text-white rounded-lg text-xs font-bold hover:bg-slate-800 transition">Reset Filter</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             2. VIEW: COURSE DETAIL
        =============================== -->
        <div v-else-if="currentPage === 'course-detail' && activeCourse" class="flex-grow bg-slate-50 py-10 animate-fade">
            <div class="max-w-7xl mx-auto px-4">
                <nav class="flex items-center text-xs font-semibold text-slate-500 mb-8 space-x-2">
                    <a href="#" @click.prevent="navigate('landing')" class="hover:text-slate-900 transition">Beranda</a>
                    <i class="ph-bold ph-caret-right"></i>
                    <a href="#" @click.prevent="navigate('category')" class="hover:text-slate-900 transition">Katalog</a>
                    <i class="ph-bold ph-caret-right"></i>
                    <a href="#" @click.prevent="navigate('category', activeCourse.category)" class="hover:text-slate-900 transition">{{ activeCourse.category }}</a>
                </nav>

                <div class="grid lg:grid-cols-3 gap-8 items-start">
                    <div class="lg:col-span-2 space-y-8">
                        <div class="rounded-[2rem] overflow-hidden aspect-video bg-slate-800 relative group cursor-pointer shadow-sm border border-slate-200" @click="isDemoModalOpen = true">
                            <img :src="activeCourse.image" class="w-full h-full object-cover opacity-80 group-hover:scale-105 transition-transform duration-700">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="w-16 h-16 bg-white/30 backdrop-blur rounded-full flex items-center justify-center text-white border border-white/50 group-hover:scale-110 transition-transform shadow-lg">
                                    <i class="ph-fill ph-play text-2xl ml-1"></i>
                                </div>
                            </div>
                            <div class="absolute top-4 left-4 flex gap-2">
                                <span class="bg-white/90 backdrop-blur text-slate-900 px-3 py-1.5 rounded-lg text-[10px] font-bold shadow-sm uppercase tracking-wider">{{ activeCourse.category }}</span>
                                <span class="bg-slate-900/80 backdrop-blur text-white px-3 py-1.5 rounded-lg text-[10px] font-bold shadow-sm uppercase tracking-wider">{{ activeCourse.level || 'Semua Level' }}</span>
                            </div>
                        </div>

                        <div>
                            <h1 class="text-3xl md:text-4xl font-black text-slate-900 mb-4 leading-tight">{{ activeCourse.title }}</h1>
                            <div class="flex flex-wrap items-center text-sm font-bold text-slate-600 gap-4 md:gap-6 mb-6">
                                <span class="flex items-center text-yellow-500"><i class="ph-fill ph-star mr-1.5 text-lg"></i> {{ activeCourse.rating }} / 5.0</span>
                                <span class="flex items-center"><i class="ph-fill ph-users mr-1.5 text-slate-400 text-lg"></i> {{ activeCourse.students }} Terdaftar</span>
                                <span class="flex items-center"><i class="ph-fill ph-translate mr-1.5 text-slate-400 text-lg"></i> Bahasa Indonesia</span>
                                <span class="flex items-center"><i class="ph-fill ph-certificate mr-1.5 text-slate-400 text-lg"></i> Sertifikat Tersedia</span>
                            </div>
                            <p class="text-slate-600 leading-relaxed text-base">Pelajari konsep inti hingga implementasi tingkat lanjut dari teknologi ini. Disusun berdasarkan *best practice* industri, kelas ini akan membimbing Anda membangun proyek nyata yang bisa ditambahkan ke portofolio Anda.</p>
                        </div>

                        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex items-center">
                            <div class="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center font-bold text-lg mr-4 text-slate-600 border border-slate-200">{{ activeCourse.instructorInitials }}</div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Instruktur Kelas</p>
                                <h4 class="font-bold text-slate-900 text-lg">{{ activeCourse.instructor }}</h4>
                                <p class="text-xs text-slate-500 font-medium">Senior Software Engineer & Tech Lead</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-[2rem] p-8 border border-slate-200 shadow-sm">
                            <h3 class="text-xl font-bold text-slate-900 mb-6">Materi Pembelajaran</h3>
                            <div class="space-y-3">
                                <div v-for="(module, index) in courseSyllabus" :key="index" class="border border-slate-200 rounded-xl overflow-hidden transition-all duration-300">
                                    <button @click="module.open = !module.open" class="w-full p-4 flex items-center justify-between hover:bg-slate-50 transition-colors focus:outline-none group">
                                        <div class="flex items-center text-left">
                                            <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center text-slate-500 font-bold mr-4 shrink-0 group-hover:bg-primary-50 group-hover:text-primary-600 transition">{{ index + 1 }}</div>
                                            <div>
                                                <h4 class="font-bold text-slate-900 text-sm mb-0.5 group-hover:text-primary-600 transition-colors">{{ module.title }}</h4>
                                                <p class="text-xs text-slate-500">{{ module.duration }}</p>
                                            </div>
                                        </div>
                                        <div class="w-8 h-8 shrink-0 rounded-full flex items-center justify-center transition-transform duration-300 text-slate-400" :class="module.open ? 'rotate-180 bg-primary-50 text-primary-600' : ''">
                                            <i class="ph-bold ph-caret-down"></i>
                                        </div>
                                    </button>
                                    
                                    <div class="grid transition-all duration-300 ease-in-out" :class="module.open ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'">
                                        <div class="overflow-hidden">
                                            <div class="px-4 pb-4">
                                                <div class="pl-[3.25rem] pr-2 pt-2 border-t border-slate-100 mt-2">
                                                    <ul class="space-y-3 text-sm text-slate-600 font-medium">
                                                        <li v-for="(item, i) in module.items" :key="i" class="flex items-center group/item cursor-pointer">
                                                            <i class="ph-fill ph-play-circle text-slate-300 mr-2.5 text-lg group-hover/item:text-primary-500 transition-colors"></i> 
                                                            <span class="group-hover/item:text-slate-900 transition-colors">{{ item }}</span>
                                                            <span v-if="i===0 && index===0" class="ml-auto text-[9px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded font-bold uppercase">Preview</span>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             4. VIEW: ROADMAP KARIR
        =============================== -->
        <div v-else-if="currentPage === 'roadmap'" class="flex-grow bg-slate-50 py-12 animate-fade relative overflow-hidden">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9IiNlNGE4YTMiLz48L3N2Zz4=')] opacity-50 z-0"></div>
            <div class="max-w-6xl mx-auto px-4 relative z-10">
                <div class="text-center mb-12">
                    <div class="inline-flex items-center py-1.5 px-4 rounded-full bg-primary-100 text-primary-700 text-xs font-bold mb-4 border border-primary-200 shadow-sm">Panduan Terstruktur</div>
                    <h1 class="text-3xl md:text-4xl font-black text-slate-900 mb-3 tracking-tight">Roadmap Karir Tech</h1>
                    <p class="text-slate-500 text-base max-w-2xl mx-auto">Pilih peran atau *role* yang ingin Anda capai, dan ikuti langkah demi langkah materi yang harus dipelajari agar Anda siap direkrut industri.</p>
                </div>
                <div class="flex flex-wrap justify-center gap-3 mb-12 pb-4">
                    <button v-for="role in roadmaps" :key="role.id" @click="activeRoadmap = role.id"
                            class="px-5 py-2.5 rounded-xl font-bold text-sm transition-all duration-300 border flex items-center shrink-0"
                            :class="activeRoadmap === role.id ? 'bg-slate-900 text-white border-slate-900 shadow-md' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100'">
                        <i :class="role.icon + ' mr-2 text-lg'"></i> {{ role.title }}
                    </button>
                </div>
                <div v-if="currentRoadmapData" class="bg-white rounded-[2rem] border border-slate-200 shadow-xl shadow-slate-200/50 p-6 md:p-12 animate-fade relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-48 bg-gradient-to-b from-slate-50 to-transparent z-0 pointer-events-none"></div>
                    <div class="text-center mb-16 pb-8 border-b border-slate-100 relative z-10">
                        <div class="w-20 h-20 mx-auto bg-white border border-slate-200 text-slate-700 rounded-2xl flex items-center justify-center text-4xl mb-5 shadow-sm"><i :class="currentRoadmapData.icon"></i></div>
                        <h2 class="text-2xl md:text-3xl font-black text-slate-900 mb-3">Jalur Belajar: {{ currentRoadmapData.title }}</h2>
                        <p class="text-slate-500 text-sm max-w-lg mx-auto">{{ currentRoadmapData.description }}</p>
                    </div>
                    <div id="roadmap-timeline" class="relative pb-8 z-10 md:pt-4">
                        <div class="absolute left-[16px] md:left-1/2 md:-ml-[1.5px] top-0 bottom-4 w-0 border-l-[3px] border-dashed border-slate-200 z-0"></div>
                        <div class="absolute left-[16px] md:left-1/2 md:-ml-[1.5px] top-0 w-0 border-l-[3px] border-dashed border-primary-500 z-10 transition-all duration-200 ease-out" 
                             :style="{ height: `${timelineProgress}%`, maxHeight: 'calc(100% - 2rem)' }"></div>
                        <div class="space-y-12 md:space-y-0 relative">
                            <div v-for="(step, index) in currentRoadmapData.steps" :key="index" class="relative flex flex-col md:flex-row items-center w-full md:mb-16">
                                <div class="absolute left-[16px] -translate-x-1/2 md:static md:translate-x-0 w-10 h-10 rounded-full border-[4px] flex items-center justify-center z-20 transition-all duration-500 md:order-2 md:mx-auto"
                                     :class="[(timelineProgress > ((index / currentRoadmapData.steps.length) * 100)) ? 'scale-110 shadow-md ' + getStepColorClass(index).dotActive : 'border-slate-200 bg-slate-50 scale-75']">
                                    <span class="text-[12px] font-black transition-all duration-300" :class="(timelineProgress > ((index / currentRoadmapData.steps.length) * 100)) ? 'text-white opacity-100 scale-100' : 'opacity-0 scale-50'">{{ index + 1 }}</span>
                                </div>
                                <div class="w-full md:w-[45%] pl-12 md:pl-0 flex" :class="index % 2 === 0 ? 'md:order-1 md:pr-10 md:justify-end' : 'md:order-3 md:pl-10 md:justify-start'">
                                    <div :class="['w-full border rounded-[1.5rem] p-6 md:p-8 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group relative overflow-hidden', getStepColorClass(index).bg, index % 2 === 0 ? 'md:text-right' : 'md:text-left']">
                                        <div class="relative z-10">
                                            <span :class="['text-[10px] font-bold uppercase tracking-wider mb-2 block', getStepColorClass(index).text]">Langkah {{ index + 1 }}</span>
                                            <h3 class="text-xl font-bold text-slate-900 mb-3">{{ step.title }}</h3>
                                            <p class="text-sm text-slate-600 mb-6 leading-relaxed">{{ step.desc }}</p>
                                            <div class="flex items-center bg-white/70 backdrop-blur-sm p-4 rounded-xl border border-white/80 group-hover:bg-white transition-colors cursor-pointer shadow-sm" :class="index % 2 === 0 ? 'md:flex-row-reverse' : 'flex-row'" @click="navigate('course-detail', getCourseByTitle(step.courseTitle))">
                                                <div :class="['w-10 h-10 md:w-12 md:h-12 shrink-0 rounded-xl flex items-center justify-center shadow-sm bg-white', getStepColorClass(index).text, index % 2 === 0 ? 'mr-0 md:ml-4' : 'mr-4']">
                                                    <i class="ph-fill ph-book-open text-xl md:text-2xl"></i>
                                                </div>
                                                <div class="flex flex-col flex-grow" :class="index % 2 === 0 ? 'text-left md:text-right' : 'text-left'">
                                                    <span class="text-[9px] md:text-[10px] text-slate-500 font-bold uppercase tracking-wide">Kelas Rekomendasi</span>
                                                    <span class="text-sm md:text-base font-bold text-slate-800 line-clamp-1">{{ step.courseTitle }}</span>
                                                </div>
                                                <div class="hidden sm:flex shrink-0 w-8 h-8 bg-white rounded-lg items-center justify-center transition shadow-sm ml-3" :class="getStepColorClass(index).btn"><i class="ph-bold ph-arrow-right"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="hidden md:block md:w-[45%]" :class="index % 2 === 0 ? 'md:order-3' : 'md:order-1'"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-16 text-center bg-slate-900 rounded-[2rem] p-10 shadow-xl relative overflow-hidden">
                        <div class="absolute right-[-50px] top-[-50px] w-64 h-64 bg-primary-500 rounded-full blur-[80px] opacity-40"></div>
                        <div class="relative z-10">
                            <h4 class="text-2xl font-bold text-white mb-3">Siap Untuk Memulai?</h4>
                            <p class="text-sm text-slate-400 mb-8 max-w-md mx-auto">Buat akun gratis sekarang dan akses semua roadmap, fitur diskusi, dan diskon kelas khusus member baru.</p>
                            <button @click="navigate('register')" class="px-8 py-4 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-500 transition shadow-lg active:scale-95">Daftar & Mulai Belajar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             5. VIEW: BOOTCAMP PREMIUM
        =============================== -->
        <div v-else-if="currentPage === 'bootcamp'" class="flex-grow bg-white py-16 animate-fade">
            <div class="max-w-6xl mx-auto px-4 mb-20 text-center">
                <div class="inline-flex items-center py-1.5 px-4 rounded-full bg-red-100 text-red-700 text-xs font-bold mb-6 border border-red-200"><i class="ph-fill ph-fire mr-1.5"></i> Batch 12 Dibuka</div>
                <h1 class="text-4xl md:text-6xl font-black text-slate-900 mb-6 tracking-tight">Intensive <span class="text-primary-600">Bootcamp</span></h1>
                <p class="text-slate-500 text-lg max-w-2xl mx-auto mb-10">Program intensif 12 minggu dengan kurikulum terarah, 1-on-1 mentoring, penyaluran kerja (*hiring partner*), dan garansi uang kembali.</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <button @click="navigate('login')" class="w-full sm:w-auto px-8 py-3.5 bg-slate-900 text-white rounded-xl font-bold shadow-lg hover:bg-primary-600 transition-colors">Daftar Seleksi Batch 12</button>
                    <button class="w-full sm:w-auto px-8 py-3.5 bg-white border border-slate-200 text-slate-700 rounded-xl font-bold hover:bg-slate-50 transition">Download Silabus</button>
                </div>
            </div>
            
            <div class="bg-slate-50 py-20 border-y border-slate-100">
                <div class="max-w-6xl mx-auto px-4 grid md:grid-cols-3 gap-8">
                    <div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm">
                        <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl mb-6"><i class="ph-bold ph-chalkboard-teacher"></i></div>
                        <h3 class="text-xl font-bold mb-2">Live Mentoring 1-on-1</h3>
                        <p class="text-slate-500 text-sm leading-relaxed">Sesi privat mingguan dengan Senior Engineer untuk review kode dan diskusi kendala belajar.</p>
                    </div>
                    <div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm">
                        <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl mb-6"><i class="ph-bold ph-briefcase"></i></div>
                        <h3 class="text-xl font-bold mb-2">Hiring Partner</h3>
                        <p class="text-slate-500 text-sm leading-relaxed">Kami bekerja sama dengan 50+ perusahaan teknologi untuk memprioritaskan CV lulusan bootcamp.</p>
                    </div>
                    <div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm">
                        <div class="w-14 h-14 bg-yellow-50 text-yellow-600 rounded-2xl flex items-center justify-center text-2xl mb-6"><i class="ph-bold ph-medal"></i></div>
                        <h3 class="text-xl font-bold mb-2">Job Guarantee</h3>
                        <p class="text-slate-500 text-sm leading-relaxed">Tidak mendapat tawaran pekerjaan dalam 6 bulan setelah lulus? Kami kembalikan uang Anda 100%.</p>
                    </div>
                </div>
            </div>

            <div class="max-w-5xl mx-auto px-4 py-24">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-black text-slate-900 mb-4">Pilihan Paket Belajar</h2>
                    <p class="text-slate-500">Pilih skema yang sesuai dengan komitmen investasi karir Anda.</p>
                </div>
                <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                    <div class="bg-white rounded-[2rem] border border-slate-200 p-8 shadow-sm hover:border-primary-300 transition-colors">
                        <h3 class="text-2xl font-bold text-slate-900 mb-2">Bootcamp Reguler</h3>
                        <p class="text-sm text-slate-500 mb-6">Cocok untuk yang ingin fokus ke ilmu tanpa kontrak penyaluran kerja.</p>
                        <div class="mb-6"><span class="text-4xl font-black text-slate-900">Rp 4.500.000</span></div>
                        <button @click="navigate('login')" class="w-full py-3.5 bg-slate-50 text-slate-700 border border-slate-200 rounded-xl font-bold hover:bg-slate-100 transition mb-8">Pilih Reguler</button>
                        <div class="space-y-4">
                            <div class="flex items-center text-sm text-slate-600"><i class="ph-bold ph-check text-emerald-500 mr-3"></i> 12 Minggu Live Session</div>
                            <div class="flex items-center text-sm text-slate-600"><i class="ph-bold ph-check text-emerald-500 mr-3"></i> Final Project Review</div>
                            <div class="flex items-center text-sm text-slate-600"><i class="ph-bold ph-check text-emerald-500 mr-3"></i> Sertifikat Kelulusan</div>
                            <div class="flex items-center text-sm text-slate-400 opacity-50"><i class="ph-bold ph-x text-slate-300 mr-3"></i> Prioritas Penyaluran Kerja</div>
                            <div class="flex items-center text-sm text-slate-400 opacity-50"><i class="ph-bold ph-x text-slate-300 mr-3"></i> Refund Job Guarantee</div>
                        </div>
                    </div>
                    <div class="bg-slate-900 rounded-[2rem] border border-slate-800 p-8 shadow-2xl relative overflow-hidden transform md:-translate-y-4">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-primary-600 rounded-full blur-[60px] opacity-50"></div>
                        <div class="relative z-10">
                            <div class="inline-block bg-primary-500 text-white text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-4">Paling Direkomendasikan</div>
                            <h3 class="text-2xl font-bold text-white mb-2">Job Guarantee</h3>
                            <p class="text-sm text-slate-400 mb-6">Jaminan langsung disalurkan kerja ke Hiring Partner kami.</p>
                            <div class="mb-6"><span class="text-4xl font-black text-white">Rp 8.000.000</span></div>
                            <button @click="navigate('login')" class="w-full py-3.5 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-500 transition shadow-lg active:scale-95 mb-8">Pilih Job Guarantee</button>
                            <div class="space-y-4">
                                <div class="flex items-center text-sm text-slate-300"><i class="ph-bold ph-check text-primary-400 mr-3"></i> 12 Minggu Live Session</div>
                                <div class="flex items-center text-sm text-slate-300"><i class="ph-bold ph-check text-primary-400 mr-3"></i> Final Project + Review Portofolio</div>
                                <div class="flex items-center text-sm text-slate-300"><i class="ph-bold ph-check text-primary-400 mr-3"></i> Review CV & Simulasi Interview</div>
                                <div class="flex items-center text-sm text-white font-bold"><i class="ph-bold ph-check text-primary-400 mr-3"></i> Penyaluran ke 50+ Hiring Partner</div>
                                <div class="flex items-center text-sm text-white font-bold"><i class="ph-bold ph-check text-primary-400 mr-3"></i> Uang Kembali 100% Jika Gagal</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             6. VIEW: WEBINAR & EVENT 
        =============================== -->
        <div v-else-if="currentPage === 'webinar'" class="flex-grow bg-slate-50 py-16 animate-fade">
            <div class="max-w-6xl mx-auto px-4">
                <div class="text-center mb-16">
                    <h1 class="text-4xl md:text-5xl font-black text-slate-900 mb-4 tracking-tight">Webinar & Event</h1>
                    <p class="text-slate-500 text-base max-w-2xl mx-auto">Tingkatkan wawasan industri Anda dengan mengikuti sesi *live class* bersama expert secara gratis maupun berbayar.</p>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="(event, idx) in webinars" :key="idx" class="bg-white border border-slate-200 rounded-[1.5rem] overflow-hidden hover:shadow-xl hover:border-primary-300 transition-all group flex flex-col cursor-pointer" @click="navigate('webinar-detail', event)">
                        <div class="aspect-video bg-slate-800 relative p-6 flex flex-col justify-end overflow-hidden">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent z-10"></div>
                            <img :src="event.img" class="absolute inset-0 w-full h-full object-cover opacity-50 group-hover:scale-105 transition-transform duration-700">
                            <div class="relative z-20">
                                <span class="bg-primary-500 text-white text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded mb-2 inline-block">{{ event.type }}</span>
                                <h3 class="text-white font-bold text-lg leading-snug line-clamp-2">{{ event.title }}</h3>
                            </div>
                        </div>
                        <div class="p-6 flex flex-col flex-grow">
                            <div class="flex items-center text-sm text-slate-500 mb-4 font-medium">
                                <i class="ph-bold ph-calendar-blank mr-2 text-primary-500"></i> {{ event.date }}
                            </div>
                            <div class="flex items-center mt-auto border-t border-slate-100 pt-4">
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 mr-3">{{ event.speakerInitials }}</div>
                                <span class="text-sm font-bold text-slate-800">{{ event.speaker }}</span>
                            </div>
                            <button @click.stop="navigate('webinar-detail', event)" class="w-full mt-5 py-2.5 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-900 hover:text-white transition-colors">Lihat Detail & Daftar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             6.5 VIEW: WEBINAR DETAIL (NEW)
        =============================== -->
        <div v-else-if="currentPage === 'webinar-detail' && activeWebinar" class="flex-grow bg-slate-50 py-12 animate-fade">
            <div class="max-w-6xl mx-auto px-4">
                <button @click="navigate('webinar')" class="mb-6 text-slate-500 hover:text-primary-600 flex items-center font-bold text-sm transition">
                    <i class="ph-bold ph-arrow-left mr-2"></i> Kembali ke Daftar Event
                </button>

                <div class="grid lg:grid-cols-3 gap-8 items-start">
                    <!-- Kiri: Info Lengkap Webinar -->
                    <div class="lg:col-span-2 space-y-8">
                        <div class="rounded-[2rem] overflow-hidden aspect-video bg-slate-800 relative shadow-sm border border-slate-200">
                            <img :src="activeWebinar.img" class="w-full h-full object-cover opacity-90">
                            <div class="absolute top-4 left-4 flex gap-2">
                                <span class="bg-white/90 backdrop-blur text-slate-900 px-3 py-1.5 rounded-lg text-[10px] font-bold shadow-sm uppercase tracking-wider">Live Event</span>
                                <span :class="activeWebinar.type === 'Gratis' ? 'bg-emerald-500/90 text-white' : 'bg-primary-600/90 text-white'" class="backdrop-blur px-3 py-1.5 rounded-lg text-[10px] font-bold shadow-sm uppercase tracking-wider">{{ activeWebinar.type }}</span>
                            </div>
                        </div>

                        <div class="bg-white rounded-[2rem] p-8 md:p-10 border border-slate-200 shadow-sm">
                            <h1 class="text-3xl md:text-4xl font-black text-slate-900 mb-6 leading-tight">{{ activeWebinar.title }}</h1>
                            
                            <div class="flex flex-wrap gap-6 border-b border-slate-100 pb-8 mb-8">
                                <div class="flex items-center text-slate-600">
                                    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center text-primary-500 mr-4 border border-slate-100"><i class="ph-fill ph-calendar-blank text-2xl"></i></div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Jadwal Pelaksanaan</p>
                                        <p class="text-sm font-bold text-slate-800">{{ activeWebinar.date }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center text-slate-600">
                                    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center text-primary-500 mr-4 border border-slate-100"><i class="ph-fill ph-video-camera text-2xl"></i></div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Platform</p>
                                        <p class="text-sm font-bold text-slate-800">Zoom / Google Meet</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6 text-slate-600 leading-relaxed text-sm md:text-base">
                                <h3 class="text-xl font-bold text-slate-900">Tentang Webinar Ini</h3>
                                <p>{{ activeWebinar.description }}</p>
                                
                                <h3 class="text-xl font-bold text-slate-900 pt-4">Apa yang akan Anda dapatkan?</h3>
                                <ul class="grid md:grid-cols-2 gap-3">
                                    <li v-for="(benefit, i) in activeWebinar.benefits" :key="i" class="flex items-center">
                                        <i class="ph-bold ph-check text-emerald-500 mr-2.5 text-lg shrink-0"></i> {{ benefit }}
                                    </li>
                                </ul>
                            </div>

                            <div class="mt-10 pt-8 border-t border-slate-100 flex items-center">
                                <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center font-bold text-xl mr-5 text-slate-600 border border-slate-200">{{ activeWebinar.speakerInitials }}</div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Speaker</p>
                                    <h4 class="font-bold text-slate-900 text-lg leading-tight">{{ activeWebinar.speaker }}</h4>
                                    <p class="text-xs text-slate-500 font-medium">{{ activeWebinar.speakerRole }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kanan: Kotak Registrasi (Sticky) -->
                    <div class="lg:col-span-1 lg:sticky lg:top-28">
                        <div class="bg-white rounded-[2rem] p-8 border border-slate-200 shadow-xl shadow-slate-200/50">
                            <div class="mb-6 text-center">
                                <p class="text-sm font-bold text-slate-500 mb-2">Tiket Registrasi</p>
                                <h2 class="text-4xl font-black" :class="activeWebinar.price === 'Gratis' ? 'text-emerald-500' : 'text-primary-600'">{{ activeWebinar.price }}</h2>
                            </div>
                            
                            <button @click="isLoggedIn ? navigate('dashboard') : navigate('login')" class="w-full py-4 bg-slate-900 text-white rounded-xl font-bold text-base hover:bg-primary-600 transition shadow-lg shadow-slate-900/20 active:scale-95 mb-4">
                                Daftar Event Sekarang
                            </button>
                            
                            <p class="text-xs text-center text-slate-500 font-medium mb-6">Kuota terbatas. Tautan kelas (meeting link) akan dikirimkan via Email H-1 acara.</p>
                            
                            <hr class="border-slate-100 mb-6">
                            
                            <p class="font-bold text-xs text-slate-400 uppercase tracking-wider mb-4 text-center">Bagikan Event Ini</p>
                            <div class="flex justify-center gap-3">
                                <button class="w-10 h-10 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-400 hover:text-blue-500 transition-colors"><i class="ph-fill ph-twitter-logo text-lg"></i></button>
                                <button class="w-10 h-10 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-400 hover:text-blue-600 transition-colors"><i class="ph-fill ph-linkedin-logo text-lg"></i></button>
                                <button class="w-10 h-10 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-400 hover:text-emerald-500 transition-colors"><i class="ph-fill ph-whatsapp-logo text-lg"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             7. VIEW: MENTORS / INSTRUKTUR 
        =============================== -->
        <div v-else-if="currentPage === 'mentors'" class="flex-grow bg-slate-50 py-16 animate-fade">
            <div class="max-w-7xl mx-auto px-4">
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <span class="inline-flex items-center py-1.5 px-4 rounded-full bg-white text-slate-600 text-xs font-bold mb-4 border border-slate-200 shadow-sm">Pengajar Berpengalaman</span>
                    <h1 class="text-4xl md:text-5xl font-black text-slate-900 mb-4 tracking-tight">Belajar Langsung Dari <span class="text-primary-600">Pakar</span></h1>
                    <p class="text-slate-500 text-base">Mentor kami adalah *senior engineer* & spesialis aktif dari berbagai startup Unicorn dan perusahaan teknologi global.</p>
                </div>

                <!-- NEW: Kriteria Mentor Section -->
                <div class="grid md:grid-cols-3 gap-6 mb-16 max-w-5xl mx-auto">
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm text-center">
                        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl mx-auto mb-4"><i class="ph-bold ph-briefcase"></i></div>
                        <h3 class="font-bold text-slate-900 mb-2">Praktisi Aktif Industri</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">Semua mentor kami masih aktif bekerja di industri, memastikan ilmu yang diajarkan selalu relevan dengan tren terbaru.</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm text-center">
                        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xl mx-auto mb-4"><i class="ph-bold ph-chalkboard-teacher"></i></div>
                        <h3 class="font-bold text-slate-900 mb-2">Terseleksi Ketat (Top 1%)</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">Melewati berbagai tahap seleksi teknis dan pedagogi untuk memastikan mereka mahir mengajar, bukan hanya pandai coding.</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm text-center">
                        <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-xl mx-auto mb-4"><i class="ph-bold ph-chats-circle"></i></div>
                        <h3 class="font-bold text-slate-900 mb-2">Responsif & Solutif</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">Berdedikasi untuk membantu memecahkan bug (*troubleshooting*) Anda di forum diskusi secara cepat dan ramah.</p>
                    </div>
                </div>

                <div class="text-center mb-8 border-b border-slate-200 pb-4 max-w-5xl mx-auto">
                    <h2 class="text-2xl font-black text-slate-900">Daftar Instruktur EduTech</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-12 mt-10">
                    <div v-for="(mentor, idx) in mentorsList" :key="idx" class="bg-white rounded-[2rem] p-1 pt-12 relative border border-slate-200 hover:shadow-2xl hover:shadow-primary-500/10 hover:border-primary-200 hover:-translate-y-2 transition-all duration-300 group mt-6">
                        <div class="absolute -top-10 left-1/2 -translate-x-1/2 w-24 h-24 rounded-full border-4 border-white shadow-lg overflow-hidden group-hover:scale-110 transition-transform duration-500 z-10 bg-slate-100">
                            <img :src="mentor.image" :alt="mentor.name" class="w-full h-full object-cover">
                        </div>
                        <div class="absolute top-0 left-0 w-full h-16 rounded-t-[2rem] bg-gradient-to-r opacity-20 z-0" :class="idx % 2 === 0 ? 'from-primary-400 to-blue-300' : 'from-purple-400 to-pink-300'"></div>
                        <div class="px-5 pb-6 pt-6 text-center relative z-10">
                            <h3 class="font-black text-slate-900 text-lg mb-1">{{ mentor.name }}</h3>
                            <p class="text-primary-600 text-[10px] font-bold uppercase tracking-wider mb-4">{{ mentor.role }}</p>
                            <div class="flex items-center justify-center bg-slate-50 py-2 px-3 rounded-xl border border-slate-100 w-max mx-auto mb-4">
                                <span class="text-[10px] font-semibold text-slate-500 mr-1.5">Active at</span>
                                <span class="font-bold text-slate-800 text-xs">{{ mentor.company }}</span>
                            </div>
                            <div class="flex justify-center space-x-2 border-t border-slate-100 pt-4 mt-2">
                                <a href="#" class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary-600 hover:border-primary-200 hover:bg-primary-50 transition-colors"><i class="ph-fill ph-linkedin-logo"></i></a>
                                <a href="#" class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-900 hover:border-slate-300 hover:bg-slate-50 transition-colors"><i class="ph-fill ph-github-logo"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NEW: CTA Join Mentor -->
                <div class="mt-20 max-w-4xl mx-auto bg-slate-900 rounded-[2.5rem] p-10 md:p-14 text-center text-white shadow-2xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-48 h-48 bg-primary-600 rounded-full blur-[80px] opacity-40"></div>
                    <div class="absolute bottom-0 left-0 w-48 h-48 bg-emerald-500 rounded-full blur-[80px] opacity-20"></div>
                    <div class="relative z-10">
                        <i class="ph-duotone ph-chalkboard-teacher text-5xl mb-4 text-primary-400 inline-block"></i>
                        <h2 class="text-3xl font-black text-white mb-4">Punya Passion Mengajar?</h2>
                        <p class="text-slate-400 text-sm md:text-base max-w-xl mx-auto mb-8">Bantu ribuan talenta digital Indonesia meraih mimpi mereka sekaligus dapatkan penghasilan tambahan yang kompetitif. Bergabunglah bersama puluhan expert lainnya di EduTech.</p>
                        <button @click="navigate('join-mentor')" class="px-8 py-3.5 bg-primary-600 text-white rounded-xl font-bold text-sm hover:bg-primary-500 transition shadow-lg active:scale-95">Daftar Menjadi Instruktur</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             7.5 VIEW: JOIN MENTOR (DAFTAR INSTRUKTUR)
        =============================== -->
        <div v-else-if="currentPage === 'join-mentor'" class="flex-grow bg-slate-50 py-12 animate-fade">
            <div class="max-w-4xl mx-auto px-4">
                <button @click="navigate('mentors')" class="mb-6 text-slate-500 hover:text-primary-600 flex items-center font-bold text-sm transition">
                    <i class="ph-bold ph-arrow-left mr-2"></i> Kembali ke Instruktur
                </button>

                <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
                    <!-- Hero Banner -->
                    <div class="bg-slate-900 p-10 md:p-14 text-center text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-primary-600 rounded-full blur-[80px] opacity-40 -translate-y-1/2 translate-x-1/2"></div>
                        <div class="absolute bottom-0 left-0 w-64 h-64 bg-purple-600 rounded-full blur-[80px] opacity-30 translate-y-1/2 -translate-x-1/2"></div>
                        <div class="relative z-10">
                            <span class="inline-block bg-white/10 text-white px-3 py-1 rounded-full text-xs font-bold border border-white/20 mb-4 uppercase tracking-wider">Hiring Experts</span>
                            <h1 class="text-3xl md:text-5xl font-black mb-4 tracking-tight">Bergabung Sebagai Instruktur</h1>
                            <p class="text-slate-300 text-sm md:text-base max-w-2xl mx-auto leading-relaxed">Bagikan keahlian Anda, bangun *personal branding*, dan dapatkan penghasilan tambahan dengan mengajar ribuan talenta digital di seluruh Indonesia.</p>
                        </div>
                    </div>

                    <!-- Registration Form -->
                    <div class="p-8 md:p-12">
                        <div class="mb-8 pb-6 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-slate-900 mb-1">Formulir Pendaftaran</h3>
                                <p class="text-sm text-slate-500">Lengkapi data diri Anda agar tim kurikulum kami dapat melakukan evaluasi.</p>
                            </div>
                            <div class="hidden sm:flex w-12 h-12 bg-primary-50 text-primary-600 rounded-xl items-center justify-center text-2xl">
                                <i class="ph-fill ph-file-text"></i>
                            </div>
                        </div>

                        <!-- Agar tidak refresh halaman saat disubmit, gunakan navigasi tiruan -->
                        <form @submit.prevent="navigate('mentors'); alert('Terima kasih! Aplikasi Anda sebagai instruktur telah kami terima. Tim kami akan menghubungi Anda maksimal 3 hari kerja.')" class="space-y-6">
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1.5 ml-1">Nama Lengkap <span class="text-red-500">*</span></label>
                                    <input type="text" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-primary-500/50 transition-all text-sm font-medium text-slate-800" placeholder="Budi Santoso">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1.5 ml-1">Email Profesional <span class="text-red-500">*</span></label>
                                    <input type="email" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-primary-500/50 transition-all text-sm font-medium text-slate-800" placeholder="budi@example.com">
                                </div>
                            </div>

                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1.5 ml-1">Nomor WhatsApp <span class="text-red-500">*</span></label>
                                    <input type="tel" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-primary-500/50 transition-all text-sm font-medium text-slate-800" placeholder="+62 812-3456-7890">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1.5 ml-1">Pekerjaan & Perusahaan Saat Ini <span class="text-red-500">*</span></label>
                                    <input type="text" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-primary-500/50 transition-all text-sm font-medium text-slate-800" placeholder="Senior Backend Engineer di Gojek">
                                </div>
                            </div>

                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1.5 ml-1">URL LinkedIn <span class="text-red-500">*</span></label>
                                    <input type="url" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-primary-500/50 transition-all text-sm font-medium text-slate-800" placeholder="https://linkedin.com/in/username">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1.5 ml-1">Topik yang Ingin Diajarkan <span class="text-red-500">*</span></label>
                                    <select required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-primary-500/50 transition-all text-sm font-medium text-slate-700 cursor-pointer">
                                        <option value="" disabled selected>Pilih Spesialisasi...</option>
                                        <option>Web Development (Frontend/Backend)</option>
                                        <option>Mobile App Development</option>
                                        <option>UI/UX Design</option>
                                        <option>DevOps & Cloud Architecture</option>
                                        <option>Data Science & AI</option>
                                        <option>Lainnya</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1.5 ml-1">Upload CV / Portofolio <span class="text-red-500">*</span></label>
                                <input type="file" accept=".pdf,.doc,.docx" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-primary-500/50 transition-all text-sm font-medium text-slate-800 file:mr-4 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 cursor-pointer">
                                <p class="text-[10px] text-slate-400 mt-1.5 ml-1">Format: PDF, DOC, atau DOCX. Maksimal 5MB.</p>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1.5 ml-1">Mengapa Anda Ingin Mengajar di EduTech? <span class="text-red-500">*</span></label>
                                <textarea required rows="4" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-primary-500/50 transition-all text-sm font-medium text-slate-800 resize-none" placeholder="Ceritakan motivasi singkat Anda serta pengalaman mengajar jika ada..."></textarea>
                            </div>

                            <div class="pt-6 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-4">
                                <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider text-center md:text-left">Semua data akan dienkripsi dan dijaga kerahasiaannya.</p>
                                <button type="submit" class="w-full md:w-auto px-10 py-4 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-700 transition shadow-lg active:scale-95 flex items-center justify-center">
                                    Kirim Aplikasi Instruktur <i class="ph-bold ph-paper-plane-tilt ml-2 text-lg"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             8. VIEW: TESTIMONIALS 
        =============================== -->
        <div v-else-if="currentPage === 'testimonials'" class="flex-grow bg-slate-50 py-16 animate-fade relative overflow-hidden">
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-primary-100 rounded-full blur-[100px] opacity-60 z-0"></div>
            <div class="absolute bottom-0 left-[-100px] w-[400px] h-[400px] bg-purple-100 rounded-full blur-[100px] opacity-60 z-0"></div>

            <div class="relative z-10">
                <div class="max-w-3xl mx-auto px-4 text-center mb-16">
                    <span class="inline-flex items-center py-1.5 px-4 rounded-full bg-white text-slate-700 text-xs font-bold mb-4 border border-slate-200 shadow-sm"><i class="ph-fill ph-star mr-1 text-yellow-400"></i> Ulasan Nyata Siswa</span>
                    <h1 class="text-4xl md:text-5xl font-black text-slate-900 mb-4 tracking-tight">Kisah Sukses <span class="text-primary-600">Alumni</span></h1>
                    <p class="text-slate-500 text-base">Ribuan karir telah ditransformasi. Berikut adalah apa yang mereka katakan setelah menerapkan materi dari EduTech.</p>
                    
                    <!-- NEW: Rating Platform Badge -->
                    <div class="mt-8 inline-flex items-center justify-center space-x-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
                        <div class="flex items-center">
                            <span class="text-3xl font-black text-slate-900 mr-2">4.9</span>
                            <div class="flex flex-col items-start">
                                <div class="flex text-yellow-400 text-sm"><i class="ph-fill ph-star" v-for="n in 5" :key="n"></i></div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Berdasarkan 5.000+ Ulasan</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HIGHLIGHTED SUCCESS STORIES -->
                <div class="max-w-6xl mx-auto px-4 mb-20">
                    <div class="text-center mb-10">
                        <h2 class="text-2xl md:text-3xl font-black text-slate-900">Perjalanan Karir Inspiratif</h2>
                    </div>
                    <div class="grid md:grid-cols-2 gap-8">
                        <div v-for="(alumni, idx) in featuredAlumni" :key="idx" class="bg-white rounded-[2rem] p-8 shadow-lg shadow-slate-200/50 border border-slate-200 flex flex-col sm:flex-row gap-6 items-center sm:items-start group hover:-translate-y-1 transition-transform duration-300">
                            <div class="shrink-0 relative">
                                <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-slate-50 shadow-md relative z-10">
                                    <img :src="alumni.image" :alt="alumni.name" class="w-full h-full object-cover">
                                </div>
                                <div class="absolute -bottom-3 -right-3 w-10 h-10 bg-primary-50 rounded-full flex items-center justify-center text-primary-600 border border-primary-100 z-20 shadow-sm group-hover:scale-110 transition-transform">
                                    <i class="ph-fill ph-briefcase"></i>
                                </div>
                            </div>
                            <div class="text-center sm:text-left flex-grow">
                                <h3 class="text-xl font-bold text-slate-900 mb-1">{{ alumni.name }}</h3>
                                <p class="text-primary-600 text-xs font-bold mb-1" v-html="alumni.transition"></p>
                                <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-4">Sekarang di: <span class="text-slate-800">{{ alumni.company }}</span></p>
                                <p class="text-slate-600 text-sm italic leading-relaxed">"{{ alumni.quote }}"</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ALUMNI PLACEMENT LOGOS (NEW) -->
                <div class="max-w-5xl mx-auto px-4 mb-20 text-center">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-[0.2em] mb-8">Alumni kami telah direkrut oleh perusahaan top</p>
                    <div class="flex flex-wrap justify-center gap-8 md:gap-12 opacity-50 grayscale hover:grayscale-0 transition-all duration-500">
                        <img v-for="(brand, i) in brands" :key="i" :src="brand.img" class="h-6 md:h-8 object-contain" :alt="brand.name">
                    </div>
                </div>

                <div class="text-center mb-8">
                    <h2 class="text-2xl md:text-3xl font-black text-slate-900">Lebih Banyak Ulasan Alumni</h2>
                </div>

                <div class="flex flex-col space-y-6 mb-24 overflow-hidden [mask-image:_linear-gradient(to_right,transparent_0,_black_10%,_black_90%,transparent_100%)]">
                    <div class="flex whitespace-nowrap animate-marquee hover:[animation-play-state:paused] w-max">
                        <div class="flex space-x-6 px-3" v-for="i in 2" :key="'row1-'+i">
                            <div v-for="(testi, idx) in testimonialsRow1" :key="idx" class="w-[320px] md:w-[400px] shrink-0 relative p-6 md:p-8 rounded-[2rem] shadow-sm border border-slate-100 bg-white whitespace-normal group hover:shadow-md transition-all">
                                <i class="ph-fill ph-quotes text-5xl text-slate-100 absolute top-4 right-4 z-0 group-hover:text-primary-100 transition-colors"></i>
                                <div class="relative z-10">
                                    <div class="flex text-yellow-400 text-sm mb-4"><i class="ph-fill ph-star" v-for="n in 5" :key="n"></i></div>
                                    <p class="text-slate-600 font-medium leading-relaxed mb-6 text-sm">"{{ testi.quote }}"</p>
                                    <div class="flex items-center pt-5 mt-auto border-t border-slate-50">
                                        <img :src="testi.avatar" :alt="testi.name" class="w-10 h-10 rounded-full object-cover border border-slate-200">
                                        <div class="ml-3">
                                            <h4 class="font-bold text-slate-900 text-sm">{{ testi.name }}</h4>
                                            <p class="text-[10px] font-bold text-slate-500 uppercase">{{ testi.role }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex whitespace-nowrap animate-marquee-reverse hover:[animation-play-state:paused] w-max">
                        <div class="flex space-x-6 px-3" v-for="i in 2" :key="'row2-'+i">
                            <div v-for="(testi, idx) in testimonialsRow2" :key="idx" class="w-[320px] md:w-[400px] shrink-0 relative p-6 md:p-8 rounded-[2rem] shadow-sm border border-slate-100 bg-white whitespace-normal group hover:shadow-md transition-all">
                                <i class="ph-fill ph-quotes text-5xl text-slate-100 absolute top-4 right-4 z-0 group-hover:text-primary-100 transition-colors"></i>
                                <div class="relative z-10">
                                    <div class="flex text-yellow-400 text-sm mb-4"><i class="ph-fill ph-star" v-for="n in 5" :key="n"></i></div>
                                    <p class="text-slate-600 font-medium leading-relaxed mb-6 text-sm">"{{ testi.quote }}"</p>
                                    <div class="flex items-center pt-5 mt-auto border-t border-slate-50">
                                        <img :src="testi.avatar" :alt="testi.name" class="w-10 h-10 rounded-full object-cover border border-slate-200">
                                        <div class="ml-3">
                                            <h4 class="font-bold text-slate-900 text-sm">{{ testi.name }}</h4>
                                            <p class="text-[10px] font-bold text-slate-500 uppercase">{{ testi.role }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="max-w-5xl mx-auto px-4 mb-24">
                    <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-8 md:p-12">
                        <div class="text-center mb-10">
                            <h2 class="text-2xl md:text-3xl font-black text-slate-900 mb-2">Dampak Nyata Pembelajaran</h2>
                            <p class="text-slate-500 text-sm">Bukan sekadar angka, ini adalah bukti kualitas kurikulum kami di industri.</p>
                        </div>
                        <div id="impact-stats-section" class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center divide-y md:divide-y-0 md:divide-x divide-slate-100">
                            <div v-for="(stat, idx) in impactStats" :key="idx" class="pt-6 md:pt-0 flex flex-col items-center">
                                <h3 class="text-4xl font-black text-primary-600 mb-2 flex justify-center items-end tracking-tighter">
                                    <span v-if="stat.prefix" class="mr-1.5">{{ stat.prefix }}</span>
                                    <span class="slot-wrap"><span class="slot-col" :class="{'slot-roll': impactStatsVisible}" :style="{ animationDuration: stat.duration }">
                                        <span v-for="val in stat.values" :key="val">{{ val }}</span>
                                    </span></span>
                                    <span v-if="stat.suffix" :class="stat.suffixClass">{{ stat.suffix }}</span>
                                </h3>
                                <p class="text-slate-900 font-bold text-sm mb-1">{{ stat.title }}</p>
                                <p class="text-slate-500 text-xs px-4" v-html="stat.desc"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="max-w-4xl mx-auto px-4 text-center pb-12">
                    <h2 class="text-3xl font-black text-slate-900 mb-4">Siap Menulis Kisah Sukses Anda?</h2>
                    <p class="text-slate-500 mb-8">Bergabung dengan platform belajar teknologi terbaik dan bangun masa depanmu.</p>
                    <button @click="navigate('register')" class="px-8 py-3.5 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-700 transition shadow-md active:scale-95">Mulai Perjalananmu Sekarang</button>
                </div>
            </div>
        </div>

        <!-- ==============================
             9. VIEW: KARIR / HIRING 
        =============================== -->
        <div v-else-if="currentPage === 'career'" class="flex-grow bg-white py-16 animate-fade">
            <div class="max-w-5xl mx-auto px-4">
                <div class="bg-slate-900 rounded-[3rem] p-12 md:p-16 text-center text-white mb-16 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-64 h-64 bg-primary-600 rounded-full blur-[80px] opacity-30"></div>
                    <div class="relative z-10">
                        <span class="bg-white/10 text-white px-3 py-1 rounded-full text-xs font-bold border border-white/20 mb-6 inline-block">We are Hiring!</span>
                        <h1 class="text-4xl md:text-5xl font-black mb-4 tracking-tight">Bergabung Bersama Kami</h1>
                        <p class="text-slate-300 text-base max-w-xl mx-auto">Jadilah bagian dari misi kami mencetak 1 Juta talenta digital Indonesia. Kami mencari individu yang penuh semangat dan inovatif.</p>
                    </div>
                </div>

                <h3 class="text-xl font-bold text-slate-900 mb-6">Posisi Terbuka ({{ jobs.length }})</h3>
                <div class="space-y-4">
                    <div v-for="(job, idx) in jobs" :key="idx" class="flex flex-col md:flex-row md:items-center justify-between p-6 border border-slate-200 rounded-2xl hover:border-primary-400 hover:shadow-md transition-all group">
                        <div class="mb-4 md:mb-0">
                            <h4 class="text-lg font-bold text-slate-900 mb-2 group-hover:text-primary-600 transition">{{ job.title }}</h4>
                            <div class="flex items-center text-sm text-slate-500 space-x-4">
                                <span class="flex items-center"><i class="ph-bold ph-briefcase mr-1.5 text-primary-500"></i> {{ job.type }}</span>
                                <span class="flex items-center"><i class="ph-bold ph-map-pin mr-1.5 text-primary-500"></i> {{ job.location }}</span>
                            </div>
                        </div>
                        <button @click="navigate('job-detail', job)" class="w-full md:w-auto px-6 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-sm text-slate-700 group-hover:bg-slate-900 group-hover:text-white transition">Lihat Detail & Apply</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             10. VIEW: JOB DETAIL (APPLY)
        =============================== -->
        <div v-else-if="currentPage === 'job-detail' && activeJob" class="flex-grow bg-slate-50 py-12 animate-fade">
            <div class="max-w-5xl mx-auto px-4">
                <button @click="navigate('career')" class="mb-6 text-slate-500 hover:text-primary-600 flex items-center font-bold text-sm transition">
                    <i class="ph-bold ph-arrow-left mr-2"></i> Kembali ke Lowongan
                </button>

                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Left: JD Content -->
                    <div class="md:col-span-2 bg-white rounded-[2rem] p-8 md:p-10 border border-slate-200 shadow-sm">
                        <div class="mb-8 pb-8 border-b border-slate-100">
                            <h1 class="text-3xl font-black text-slate-900 mb-4">{{ activeJob.title }}</h1>
                            <div class="flex flex-wrap items-center text-sm font-semibold text-slate-600 gap-4">
                                <span class="bg-primary-50 text-primary-700 px-3 py-1 rounded-md border border-primary-100">{{ activeJob.type }}</span>
                                <span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-md border border-slate-200">{{ activeJob.location }}</span>
                            </div>
                        </div>

                        <div class="space-y-6 text-slate-600 leading-relaxed text-sm md:text-base">
                            <h3 class="text-xl font-bold text-slate-900">Deskripsi Pekerjaan</h3>
                            <p>{{ activeJob.description }}</p>
                            
                            <h3 class="text-xl font-bold text-slate-900 pt-4">Persyaratan (*Requirements*)</h3>
                            <ul class="list-disc pl-5 space-y-2">
                                <li v-for="(req, i) in activeJob.requirements" :key="i">{{ req }}</li>
                            </ul>

                            <h3 class="text-xl font-bold text-slate-900 pt-4">Keuntungan (*Benefits*)</h3>
                            <ul class="list-disc pl-5 space-y-2">
                                <li>Gaji kompetitif sesuai standar industri.</li>
                                <li>BPJS Kesehatan & Ketenagakerjaan.</li>
                                <li>Fasilitas WFA (*Work From Anywhere*).</li>
                                <li>Budaya kerja yang kolaboratif dan minim birokrasi.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Right: Apply Box -->
                    <div class="md:col-span-1">
                        <div class="bg-white rounded-[2rem] p-8 border border-slate-200 shadow-xl shadow-slate-200/50 sticky top-28 text-center">
                            <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl text-slate-400">
                                <i class="ph-duotone ph-paper-plane-tilt"></i>
                            </div>
                            <h3 class="font-bold text-lg text-slate-900 mb-2">Tertarik dengan posisi ini?</h3>
                            <p class="text-sm text-slate-500 mb-6">Siapkan CV terbaru Anda dan portofolio pendukung (jika ada).</p>
                            <button @click="isDemoModalOpen = true" class="w-full py-3.5 bg-primary-600 text-white rounded-xl font-bold shadow-md hover:bg-primary-700 transition-colors active:scale-95 mb-4">
                                Lamar Pekerjaan Ini
                            </button>
                            <p class="text-xs text-slate-400 font-medium">Batas akhir: 30 Hari lagi</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             11. VIEW: BLOG & ARTIKEL 
        =============================== -->
        <div v-else-if="currentPage === 'blog'" class="flex-grow bg-slate-50 py-16 animate-fade">
            <div class="max-w-7xl mx-auto px-4">
                <div class="text-center mb-16">
                    <h1 class="text-4xl md:text-5xl font-black text-slate-900 mb-4 tracking-tight">Blog & Artikel</h1>
                    <p class="text-slate-500 text-base max-w-2xl mx-auto">Tutorial coding, tren industri, dan tips pengembangan karir khusus untuk *developer*.</p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div v-for="(post, idx) in blogPosts" :key="idx" @click="navigate('blog-detail', post)" class="bg-white border border-slate-200 rounded-[1.5rem] overflow-hidden hover:shadow-xl transition-all group cursor-pointer flex flex-col">
                        <div class="aspect-video bg-slate-100 overflow-hidden relative">
                            <img :src="post.img" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        </div>
                        <div class="p-6 flex flex-col flex-grow">
                            <div class="flex items-center justify-between text-xs font-bold text-slate-400 mb-3">
                                <span class="text-primary-600 uppercase tracking-wider bg-primary-50 px-2 py-1 rounded">{{ post.category }}</span>
                                <span>{{ post.readTime }} read</span>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 mb-3 group-hover:text-primary-600 line-clamp-2 leading-snug">{{ post.title }}</h3>
                            <p class="text-sm text-slate-500 line-clamp-2 mt-auto">{{ post.excerpt }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             12. VIEW: BLOG DETAIL (BACA ARTIKEL)
        =============================== -->
        <div v-else-if="currentPage === 'blog-detail' && activePost" class="flex-grow bg-white py-12 animate-fade">
            <div class="max-w-3xl mx-auto px-4">
                <button @click="navigate('blog')" class="mb-6 text-slate-500 hover:text-primary-600 flex items-center font-bold text-sm transition">
                    <i class="ph-bold ph-arrow-left mr-2"></i> Kembali ke Artikel
                </button>

                <span class="text-primary-600 font-bold text-xs uppercase tracking-wider bg-primary-50 px-3 py-1.5 rounded-lg mb-4 inline-block">{{ activePost.category }}</span>
                <h1 class="text-3xl md:text-5xl font-black text-slate-900 mb-6 leading-tight tracking-tight">{{ activePost.title }}</h1>
                
                <div class="flex items-center text-sm text-slate-500 font-medium mb-10 pb-8 border-b border-slate-100">
                    <img src="https://i.pravatar.cc/150?img=11" class="w-10 h-10 rounded-full mr-3">
                    <div>
                        <p class="text-slate-900 font-bold">Tim Redaksi EduTech</p>
                        <p>Diterbitkan pada: 2 Hari yang lalu - {{ activePost.readTime }} read</p>
                    </div>
                </div>

                <div class="rounded-[2rem] overflow-hidden aspect-video bg-slate-100 mb-10 shadow-sm border border-slate-200">
                    <img :src="activePost.img" class="w-full h-full object-cover">
                </div>

                <!-- Blog Content -->
                <div class="prose prose-slate max-w-none text-slate-700 leading-relaxed text-base md:text-lg">
                    <p class="mb-6"><span class="font-bold text-xl text-slate-900">EduTech -</span> {{ activePost.excerpt }} Artikel ini akan membahas langkah-langkah praktis dan konsep inti yang perlu Anda ketahui.</p>
                    
                    <h3 class="text-2xl font-bold text-slate-900 mt-8 mb-4">Kenapa Anda Harus Belajar Ini?</h3>
                    <p class="mb-6">Di era teknologi modern, skalabilitas dan efisiensi adalah kunci. Teknologi yang kita bahas tidak hanya meningkatkan produktivitas tim, tetapi juga menjamin performa aplikasi di tahap *production* yang sangat krusial bagi kepuasan pengguna akhir (User Experience).</p>

                    <h3 class="text-2xl font-bold text-slate-900 mt-8 mb-4">Implementasi Dasar</h3>
                    <p class="mb-6">Langkah pertama adalah memastikan *environment* Anda sudah siap. Instalasi paket (*package*) dan konfigurasi dasar sangat penting sebelum Anda menulis baris kode pertama. Pastikan Anda merujuk pada dokumentasi resmi untuk menghindari *breaking changes* versi terbaru.</p>
                    
                    <div class="bg-slate-800 text-slate-200 p-6 rounded-2xl font-mono text-sm mb-6 border border-slate-700">
                        <span class="text-slate-500">// Contoh pseudocode instalasi</span><br>
                        <span class="text-emerald-400">npm</span> install @paket-teknologi-terbaru<br>
                        <span class="text-blue-400">import</span> { FiturUtama } <span class="text-blue-400">from</span> 'paket-teknologi';<br><br>
                        <span class="text-slate-500">// Inisialisasi</span><br>
                        FiturUtama.init();
                    </div>

                    <h3 class="text-2xl font-bold text-slate-900 mt-8 mb-4">Kesimpulan</h3>
                    <p class="mb-6">Mengadopsi pola pikir dan teknologi ini akan memisahkan seorang *coder* amatir dari *Software Engineer* profesional. Jangan ragu untuk bereksperimen dan mencoba di *project* lokal Anda sendiri.</p>
                </div>

                <!-- Related Posts (Baca Juga Artikel Lainnya) -->
                <div class="mt-20 pt-10 border-t border-slate-200">
                    <h3 class="text-2xl font-black text-slate-900 mb-8">Baca Juga Artikel Lainnya</h3>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div v-for="(post, idx) in blogPosts.filter(p => p.title !== activePost.title).slice(0, 2)" :key="idx" @click="navigate('blog-detail', post)" class="bg-white border border-slate-200 rounded-[1.5rem] overflow-hidden hover:shadow-xl transition-all group cursor-pointer flex flex-col">
                            <div class="aspect-video bg-slate-100 overflow-hidden relative">
                                <img :src="post.img" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            </div>
                            <div class="p-5 flex flex-col flex-grow">
                                <div class="flex items-center justify-between text-[10px] font-bold text-slate-400 mb-2">
                                    <span class="text-primary-600 uppercase tracking-wider bg-primary-50 px-2 py-1 rounded">{{ post.category }}</span>
                                    <span>{{ post.readTime }} read</span>
                                </div>
                                <h3 class="text-base font-bold text-slate-900 mb-2 group-hover:text-primary-600 line-clamp-2 leading-snug">{{ post.title }}</h3>
                                <p class="text-xs text-slate-500 line-clamp-2 mt-auto">{{ post.excerpt }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             13. VIEW: TENTANG KAMI 
        =============================== -->
        <div v-else-if="currentPage === 'about'" class="flex-grow bg-white py-16 animate-fade">
            <div class="max-w-6xl mx-auto px-4">
                <div class="text-center mb-16 max-w-3xl mx-auto">
                    <span class="inline-flex items-center py-1.5 px-4 rounded-full bg-primary-50 text-primary-700 text-xs font-bold mb-4 border border-primary-100">Our Story</span>
                    <h1 class="text-4xl md:text-5xl font-black text-slate-900 mb-6 tracking-tight">Misi Kami Mencetak <span class="text-primary-600">1 Juta</span> Talenta Digital Indonesia</h1>
                    <p class="text-slate-500 text-lg leading-relaxed">EduTech didirikan pada tahun 2024 untuk memecahkan masalah kesenjangan antara pendidikan akademis konvensional dengan kebutuhan keahlian (*skill*) yang nyata di industri teknologi saat ini.</p>
                </div>
                
                <div class="rounded-[3rem] overflow-hidden aspect-video md:aspect-[21/9] mb-20 shadow-2xl relative">
                    <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=2000" alt="Tim EduTech" class="w-full h-full object-cover">
                </div>

                <div class="grid md:grid-cols-3 gap-8 text-center mb-24 border-y border-slate-100 py-12">
                    <div>
                        <h3 class="text-5xl font-black text-slate-900 mb-2">10K+</h3>
                        <p class="text-slate-500 font-bold">Siswa Lulusan</p>
                    </div>
                    <div>
                        <h3 class="text-5xl font-black text-slate-900 mb-2">50+</h3>
                        <p class="text-slate-500 font-bold">Hiring Partner</p>
                    </div>
                    <div>
                        <h3 class="text-5xl font-black text-slate-900 mb-2">150+</h3>
                        <p class="text-slate-500 font-bold">Kelas Premium</p>
                    </div>
                </div>

                <!-- NEW: Core Values Section -->
                <div class="mb-24">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl font-black text-slate-900 mb-4">Nilai Inti EduTech</h2>
                        <p class="text-slate-500 max-w-2xl mx-auto">Kami berpegang teguh pada prinsip-prinsip ini dalam setiap fitur dan baris kode yang kami ajarkan.</p>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-8 max-w-4xl mx-auto">
                        <div class="flex gap-5 p-6 bg-slate-50 rounded-3xl border border-slate-100">
                            <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shrink-0 shadow-sm text-blue-500 text-2xl border border-slate-200"><i class="ph-bold ph-rocket-launch"></i></div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 mb-2">Relevansi Industri</h3>
                                <p class="text-sm text-slate-600 leading-relaxed">Kami terus memutakhirkan kurikulum setiap 6 bulan agar 100% selaras dengan apa yang digunakan oleh startup dan Enterprise saat ini.</p>
                            </div>
                        </div>
                        <div class="flex gap-5 p-6 bg-slate-50 rounded-3xl border border-slate-100">
                            <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shrink-0 shadow-sm text-emerald-500 text-2xl border border-slate-200"><i class="ph-bold ph-users-three"></i></div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 mb-2">Komunitas Kolaboratif</h3>
                                <p class="text-sm text-slate-600 leading-relaxed">Tidak ada yang sukses sendirian. Kami membangun ekosistem forum tanya jawab yang responsif, ramah pemula, dan suportif.</p>
                            </div>
                        </div>
                        <div class="flex gap-5 p-6 bg-slate-50 rounded-3xl border border-slate-100">
                            <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shrink-0 shadow-sm text-purple-500 text-2xl border border-slate-200"><i class="ph-bold ph-star"></i></div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 mb-2">Kualitas Tanpa Kompromi</h3>
                                <p class="text-sm text-slate-600 leading-relaxed">Mulai dari resolusi video 4K, audio jernih, hingga platform LMS yang super mulus. Kualitas adalah bentuk hormat kami pada siswa.</p>
                            </div>
                        </div>
                        <div class="flex gap-5 p-6 bg-slate-50 rounded-3xl border border-slate-100">
                            <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shrink-0 shadow-sm text-orange-500 text-2xl border border-slate-200"><i class="ph-bold ph-heart"></i></div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 mb-2">Aksesibilitas</h3>
                                <p class="text-sm text-slate-600 leading-relaxed">Pendidikan premium tidak harus mahal. Kami merancang harga dan skema pembayaran yang terjangkau bagi talenta seluruh Indonesia.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NEW: Culture Gallery -->
                <div class="text-center mb-10">
                    <h2 class="text-3xl font-black text-slate-900 mb-4">Galeri & Kehidupan Kami</h2>
                    <p class="text-slate-500 max-w-2xl mx-auto">Sekilas tentang kehidupan tim di balik layar pengembangan EduTech LMS.</p>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="aspect-square rounded-2xl overflow-hidden shadow-sm hover:scale-105 transition-transform"><img src="https://images.unsplash.com/photo-1522071901873-411886a10004?q=80&w=400" class="w-full h-full object-cover"></div>
                    <div class="col-span-2 md:col-span-2 aspect-[2/1] md:aspect-[2/1] rounded-2xl overflow-hidden shadow-sm hover:scale-[1.02] transition-transform"><img src="https://images.unsplash.com/photo-1542744173-8e7e53415bb0?q=80&w=800" class="w-full h-full object-cover"></div>
                    <div class="aspect-square rounded-2xl overflow-hidden shadow-sm hover:scale-105 transition-transform"><img src="https://images.unsplash.com/photo-1515162816999-a0c47dc192f7?q=80&w=400" class="w-full h-full object-cover"></div>
                    <div class="col-span-2 md:col-span-2 aspect-[2/1] md:aspect-[2/1] rounded-2xl overflow-hidden shadow-sm hover:scale-[1.02] transition-transform"><img src="https://images.unsplash.com/photo-1600880292203-757bb62b4baf?q=80&w=800" class="w-full h-full object-cover"></div>
                    <div class="aspect-square rounded-2xl overflow-hidden shadow-sm hover:scale-105 transition-transform"><img src="https://images.unsplash.com/photo-1552664730-d307ca884978?q=80&w=400" class="w-full h-full object-cover"></div>
                    <div class="aspect-square rounded-2xl overflow-hidden shadow-sm hover:scale-105 transition-transform"><img src="https://images.unsplash.com/photo-1528605248644-14dd04022da1?q=80&w=400" class="w-full h-full object-cover"></div>
                </div>
            </div>
        </div>

        <!-- ==============================
             14. VIEW: DASHBOARD REDIRECT
        =============================== -->
        <div v-else-if="currentPage === 'dashboard'" class="flex-grow bg-slate-50 py-20 animate-fade px-4">
            <div class="max-w-3xl mx-auto bg-white border border-slate-200 rounded-[2rem] p-8 md:p-12 shadow-sm text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-primary-50 border border-primary-100 text-primary-600 flex items-center justify-center text-3xl mb-5">
                    <i class="ph-duotone ph-squares-four"></i>
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 mb-3">Masuk ke Dasbor Pembelajaran</h1>
                <p class="text-slate-500 text-sm md:text-base mb-8">Gunakan dashboard utama untuk mengakses progress belajar, transaksi, dan pengaturan akun Anda.</p>
                <button @click="navigate('dashboard')" class="px-8 py-3.5 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-primary-600 transition shadow-lg">
                    Buka Dashboard
                </button>
            </div>
        </div>

        <!-- ==============================
             15. VIEW: LOGIN
        =============================== -->
        <div v-else-if="currentPage === 'login'" class="flex-grow bg-slate-50 py-14 md:py-20 animate-fade px-4">
            <div class="max-w-5xl mx-auto grid lg:grid-cols-2 gap-8 items-stretch">
                <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-[2.5rem] p-8 md:p-10 text-white shadow-2xl relative overflow-hidden">
                    <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary-500/30 rounded-full blur-3xl"></div>
                    <div class="relative z-10">
                        <span class="inline-flex items-center py-1.5 px-4 rounded-full bg-white/10 border border-white/20 text-xs font-bold mb-5">Selamat Datang Kembali</span>
                        <h2 class="text-3xl md:text-4xl font-black leading-tight mb-4">Akses Dashboard EduTech Anda</h2>
                        <p class="text-slate-300 text-sm md:text-base mb-8">Masuk untuk melanjutkan progress belajar, membuka sertifikat, dan melihat transaksi terbaru.</p>
                        <div class="space-y-3 text-xs md:text-sm">
                            <div class="flex items-center"><i class="ph-fill ph-check-circle text-emerald-400 mr-2"></i> Sinkronisasi progres lintas perangkat</div>
                            <div class="flex items-center"><i class="ph-fill ph-check-circle text-emerald-400 mr-2"></i> Sertifikat digital terverifikasi</div>
                            <div class="flex items-center"><i class="ph-fill ph-check-circle text-emerald-400 mr-2"></i> Akses kelas premium & roadmap</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-[2.5rem] p-8 md:p-10 shadow-sm">
                    <h3 class="text-2xl font-black text-slate-900 mb-2">Masuk Akun</h3>
                    <p class="text-slate-500 text-sm mb-6">Gunakan akun yang sudah terdaftar.</p>

                    <form @submit.prevent="submitLogin" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email</label>
                            <input v-model="loginEmail" type="email" autocomplete="username" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all text-sm font-medium text-slate-800" placeholder="email@anda.com">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password</label>
                            <input v-model="loginPassword" type="password" autocomplete="current-password" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all text-sm font-medium text-slate-800" placeholder="Minimal 8 karakter">
                        </div>

                        <p v-if="loginError" class="text-sm font-semibold text-red-600 bg-red-50 border border-red-100 rounded-xl px-4 py-3">{{ loginError }}</p>

                        <button :disabled="loginLoading" type="submit" class="w-full py-3.5 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-primary-600 disabled:bg-slate-400 disabled:cursor-not-allowed transition shadow-lg">
                            {{ loginLoading ? 'Memproses...' : 'Masuk Sekarang' }}
                        </button>
                    </form>

                    <div class="mt-6 p-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-600 space-y-1.5">
                        <p class="font-black uppercase tracking-wider text-[10px] text-slate-400">Akun Demo</p>
                        <p><span class="font-bold">Admin:</span> admin@elearning.local / password123</p>
                        <p><span class="font-bold">Mentor:</span> mentor@elearning.local / password123</p>
                        <p><span class="font-bold">Siswa:</span> student@elearning.local / password123</p>
                    </div>

                    <button @click="navigate('register')" class="w-full mt-5 py-3 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-50 transition">
                        Belum punya akun? Daftar
                    </button>
                </div>
            </div>
        </div>

        <!-- ==============================
             16. VIEW: REGISTER
        =============================== -->
        <div v-else-if="currentPage === 'register'" class="flex-grow bg-slate-50 py-14 md:py-20 animate-fade px-4">
            <div class="max-w-3xl mx-auto bg-white border border-slate-200 rounded-[2.5rem] p-8 md:p-10 shadow-sm">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-primary-50 border border-primary-100 text-primary-600 flex items-center justify-center text-3xl mb-4">
                        <i class="ph-duotone ph-user-plus"></i>
                    </div>
                    <h1 class="text-3xl font-black text-slate-900 mb-2">Daftar Akun EduTech</h1>
                    <p class="text-slate-500 text-sm md:text-base">Buat akun baru dan langsung masuk ke dashboard belajar Anda.</p>
                </div>

                <form @submit.prevent="submitRegister" class="grid md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nama Lengkap</label>
                        <input v-model="registerName" type="text" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all text-sm font-medium text-slate-800" placeholder="Nama Anda">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email</label>
                        <input v-model="registerEmail" type="email" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all text-sm font-medium text-slate-800" placeholder="email@anda.com">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">No. WhatsApp</label>
                        <input v-model="registerPhone" type="tel" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all text-sm font-medium text-slate-800" placeholder="08xxxxxxxxxx">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Tujuan Belajar</label>
                        <select v-model="registerRole" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all text-sm font-medium text-slate-800">
                            <option value="student">Menjadi Siswa</option>
                            <option value="mentor">Mendaftar Instruktur</option>
                            <option value="career">Mencari Roadmap Karir</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password</label>
                        <input v-model="registerPassword" type="password" autocomplete="new-password" required minlength="8" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all text-sm font-medium text-slate-800" placeholder="Minimal 8 karakter">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Konfirmasi Password</label>
                        <input v-model="registerPasswordConfirmation" type="password" autocomplete="new-password" required minlength="8" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all text-sm font-medium text-slate-800" placeholder="Ulangi password">
                    </div>

                    <label class="md:col-span-2 flex items-start text-sm text-slate-600 cursor-pointer">
                        <input v-model="registerAgree" type="checkbox" class="mt-0.5 mr-3 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        Saya menyetujui syarat penggunaan dan kebijakan privasi EduTech.
                    </label>

                    <p v-if="registerNotice" class="md:col-span-2 text-sm font-semibold rounded-xl px-4 py-3 border" :class="registerNoticeTone === 'error' ? 'text-red-700 bg-red-50 border-red-100' : registerNoticeTone === 'success' ? 'text-emerald-700 bg-emerald-50 border-emerald-100' : 'text-amber-700 bg-amber-50 border-amber-100'">
                        {{ registerNotice }}
                    </p>

                    <div class="md:col-span-2 grid sm:grid-cols-2 gap-3 mt-2">
                        <button :disabled="registerLoading" type="submit" class="py-3.5 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-primary-600 disabled:bg-slate-400 disabled:cursor-not-allowed transition shadow-lg">{{ registerLoading ? 'Memproses...' : 'Simpan Data Pendaftaran' }}</button>
                        <button type="button" @click="navigate('login')" class="py-3.5 border border-slate-200 rounded-xl font-bold text-sm text-slate-700 hover:bg-slate-50 transition">Kembali ke Login</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ==============================
             14. VIEW: CEK SERTIFIKAT 
        =============================== -->
        <div v-else-if="currentPage === 'cek-sertifikat'" class="flex-grow bg-slate-50 py-20 animate-fade flex items-center justify-center px-4">
            <div class="max-w-2xl w-full bg-white rounded-[3rem] shadow-xl border border-slate-200 p-8 md:p-14 text-center">
                <div class="w-20 h-20 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-6 border border-primary-100">
                    <i class="ph-duotone ph-seal-check text-5xl text-primary-500"></i>
                </div>
                <h1 class="text-3xl font-black text-slate-900 mb-4">Verifikasi Sertifikat</h1>
                <p class="text-slate-500 mb-10 text-sm max-w-md mx-auto">Pastikan keaslian sertifikat lulusan EduTech dengan memasukkan Credential ID yang tertera pada dokumen.</p>
                
                <div class="relative max-w-md mx-auto mb-4">
                    <input type="text" placeholder="Contoh: EDT-2026-XYZ123" class="w-full pl-5 pr-32 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 font-medium text-slate-700 placeholder-slate-400">
                    <button class="absolute right-2 top-2 bottom-2 px-5 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-primary-600 transition">Verifikasi</button>
                </div>
                <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider mt-6">Sertifikat terenkripsi secara digital</p>
            </div>
        </div>

        <!-- ==============================
             15. VIEW: FAQ / PUSAT BANTUAN
        =============================== -->
        <div v-else-if="currentPage === 'faq'" class="flex-grow bg-slate-50 py-16 animate-fade">
            <div class="max-w-3xl mx-auto px-4">
                <div class="text-center mb-10">
                    <div class="w-14 h-14 bg-white rounded-2xl mx-auto flex items-center justify-center text-2xl text-primary-600 mb-4 border border-slate-200 shadow-sm"><i class="ph-fill ph-question"></i></div>
                    <h1 class="text-3xl md:text-4xl font-black text-slate-900 mb-3 tracking-tight">Pusat Bantuan (FAQ)</h1>
                    <p class="text-slate-500 text-base">Jawaban cepat untuk pertanyaan yang sering ditanyakan mengenai kelas dan sistem belajar kami.</p>
                </div>

                <div class="space-y-3">
                    <div v-for="(item, index) in faqs" :key="index" class="bg-white border border-slate-200 rounded-2xl overflow-hidden transition-all duration-300" :class="item.open ? 'shadow-md ring-1 ring-primary-500' : 'hover:border-slate-300'">
                        <button @click="item.open = !item.open" class="w-full px-5 py-4 flex items-center justify-between font-bold text-slate-900 text-left focus:outline-none">
                            <span class="text-sm md:text-base pr-4">{{ item.q }}</span>
                            <div class="w-7 h-7 shrink-0 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center transition-transform duration-300" :class="item.open ? 'rotate-180 bg-primary-50 text-primary-600 border-primary-100' : ''">
                                <i class="ph-bold ph-caret-down text-sm"></i>
                            </div>
                        </button>
                        <div class="grid transition-all duration-300 ease-in-out" :class="item.open ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'">
                            <div class="overflow-hidden">
                                <div class="px-5 pb-5 pt-0 text-slate-600 text-sm leading-relaxed">
                                    <hr class="border-slate-100 mb-3">
                                    {{ item.a }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             18. VIEW: SYARAT & KETENTUAN 
        =============================== -->
        <div v-else-if="currentPage === 'terms'" class="flex-grow bg-white py-16 animate-fade">
            <div class="max-w-4xl mx-auto px-4">
                <button @click="navigate('landing')" class="mb-8 text-slate-500 hover:text-primary-600 flex items-center font-bold text-sm transition">
                    <i class="ph-bold ph-arrow-left mr-2"></i> Kembali ke Beranda
                </button>

                <div class="mb-12 border-b border-slate-100 pb-8">
                    <span class="inline-flex items-center py-1 px-3 rounded-md bg-slate-100 text-slate-600 text-[10px] font-bold uppercase tracking-wider mb-4 border border-slate-200">Dokumen Legal</span>
                    <h1 class="text-3xl md:text-5xl font-black text-slate-900 mb-4 tracking-tight">Syarat & Ketentuan Layanan</h1>
                    <p class="text-slate-500 text-sm font-medium">Terakhir diperbarui: 15 April 2026</p>
                </div>

                <div class="space-y-8 text-slate-600 leading-relaxed text-sm md:text-base">
                    <section>
                        <h2 class="text-xl font-bold text-slate-900 mb-3">1. Penerimaan Persyaratan</h2>
                        <p>Dengan mendaftar, mengakses, atau menggunakan platform EduTech LMS, Anda secara sadar setuju untuk terikat dengan Syarat dan Ketentuan ini. Jika Anda tidak setuju dengan bagian mana pun dari ketentuan ini, Anda tidak diperkenankan untuk menggunakan layanan kami.</p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900 mb-3">2. Akun Pengguna</h2>
                        <ul class="list-disc pl-5 space-y-2">
                            <li>Anda bertanggung jawab untuk menjaga kerahasiaan kata sandi dan akun Anda.</li>
                            <li>Satu akun hanya diperbolehkan untuk digunakan oleh satu individu. Berbagi kredensial akun (*account sharing*) dengan pihak lain adalah pelanggaran keras yang dapat mengakibatkan pemblokiran permanen tanpa pengembalian dana.</li>
                            <li>Anda harus memberikan informasi yang akurat, lengkap, dan terkini saat melakukan pendaftaran.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900 mb-3">3. Akses Kelas & Lisensi Konten</h2>
                        <p class="mb-3">EduTech memberikan Anda lisensi terbatas, non-eksklusif, dan tidak dapat dialihkan untuk mengakses dan melihat materi pembelajaran murni untuk keperluan pendidikan pribadi dan non-komersial.</p>
                        <p>Anda dilarang keras untuk mengunduh (selain materi pendukung yang disediakan secara eksplisit), merekam layar, mereproduksi, mendistribusikan ulang, atau menjual kembali konten video maupun dokumen yang ada di platform ini.</p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900 mb-3">4. Pembayaran & Kebijakan Refund</h2>
                        <ul class="list-disc pl-5 space-y-2">
                            <li>Semua harga kelas tertera dalam mata uang Rupiah (IDR) dan sudah termasuk pajak yang berlaku.</li>
                            <li>Kami menawarkan kebijakan <strong>Garansi Uang Kembali 7 Hari</strong> untuk kelas reguler jika Anda merasa materi tidak sesuai dengan ekspektasi, dengan syarat progres belajar belum melebihi 20%.</li>
                            <li>Khusus program *Bootcamp Job Guarantee*, ketentuan pengembalian dana mengikuti kontrak terpisah yang ditandatangani saat masa pendaftaran ulang.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900 mb-3">5. Perubahan Layanan</h2>
                        <p>EduTech berhak untuk mengubah, menangguhkan, atau menghentikan fitur platform apa pun kapan saja secara sepihak. Kami juga dapat merevisi Syarat & Ketentuan ini secara berkala. Perubahan material akan diberitahukan melalui email yang terdaftar atau pengumuman di dashboard platform.</p>
                    </section>
                </div>
            </div>
        </div>

        <!-- ==============================
             19. VIEW: PRIVASI DATA 
        =============================== -->
        <div v-else-if="currentPage === 'privacy'" class="flex-grow bg-white py-16 animate-fade">
            <div class="max-w-4xl mx-auto px-4">
                <button @click="navigate('landing')" class="mb-8 text-slate-500 hover:text-primary-600 flex items-center font-bold text-sm transition">
                    <i class="ph-bold ph-arrow-left mr-2"></i> Kembali ke Beranda
                </button>

                <div class="mb-12 border-b border-slate-100 pb-8">
                    <span class="inline-flex items-center py-1 px-3 rounded-md bg-slate-100 text-slate-600 text-[10px] font-bold uppercase tracking-wider mb-4 border border-slate-200">Dokumen Legal</span>
                    <h1 class="text-3xl md:text-5xl font-black text-slate-900 mb-4 tracking-tight">Kebijakan Privasi Data</h1>
                    <p class="text-slate-500 text-sm font-medium">Terakhir diperbarui: 15 April 2026</p>
                </div>

                <div class="space-y-8 text-slate-600 leading-relaxed text-sm md:text-base">
                    <div class="bg-blue-50 text-blue-800 p-5 rounded-2xl text-sm border border-blue-100 font-medium">
                        Privasi Anda adalah prioritas utama kami. Dokumen ini menjelaskan bagaimana EduTech mengumpulkan, menggunakan, dan melindungi data pribadi Anda saat menggunakan platform kami.
                    </div>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900 mb-3">1. Informasi yang Kami Kumpulkan</h2>
                        <p class="mb-3">Kami mengumpulkan informasi dari Anda ketika Anda mendaftar di situs kami, memesan kelas, berlangganan newsletter, atau mengisi formulir profil. Informasi yang dikumpulkan meliputi:</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Nama lengkap dan alamat email.</li>
                            <li>Nomor telepon / WhatsApp (untuk keperluan administrasi Bootcamp).</li>
                            <li>Data demografis seperti lokasi dan riwayat pendidikan (opsional).</li>
                            <li>Aktivitas belajar, skor kuis, dan interaksi di forum diskusi.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900 mb-3">2. Bagaimana Kami Menggunakan Data Anda</h2>
                        <p class="mb-3">Data yang kami kumpulkan digunakan untuk tujuan berikut:</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li><strong>Personalisasi:</strong> Menyesuaikan pengalaman belajar dan merekomendasikan roadmap yang tepat.</li>
                            <li><strong>Layanan Pelanggan:</strong> Membantu merespon keluhan teknis maupun non-teknis secara efektif.</li>
                            <li><strong>Proses Transaksi:</strong> Memproses pembayaran dan mencegah tindak penipuan (*fraud detection*).</li>
                            <li><strong>Penyaluran Karir:</strong> Mengkompilasi portofolio Anda menjadi CV digital yang akan disalurkan ke Hiring Partner (khusus peserta Bootcamp).</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900 mb-3">3. Perlindungan & Keamanan Data Pribadi</h2>
                        <p>Kami menerapkan berbagai langkah keamanan teknis dan organisasional untuk menjaga keamanan informasi pribadi Anda. Seluruh transaksi pembayaran diproses melalui gerbang pembayaran terenkripsi (*Payment Gateway*) pihak ketiga (seperti Midtrans/Stripe) dan kami tidak pernah menyimpan data kartu kredit atau PIN Anda di server kami.</p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900 mb-3">4. Berbagi Informasi dengan Pihak Ketiga</h2>
                        <p>Kami <strong>tidak pernah menjual, memperdagangkan, atau menyewakan</strong> informasi identitas pribadi Anda kepada pihak lain. Kami hanya membagikan data profil dan portofolio ke <em>Hiring Partners</em> (perusahaan pencari kerja) jika dan hanya jika Anda secara eksplisit memberikan izin melalui program penyaluran kerja (*Job Guarantee*).</p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900 mb-3">5. Hak Pengguna (Penghapusan Data)</h2>
                        <p>Sesuai dengan regulasi pelindungan data pribadi (UU PDP Republik Indonesia), Anda memiliki hak penuh untuk meminta salinan data, mengubah data, atau meminta kami untuk menghapus seluruh data akun Anda dari server kami kapan saja dengan menghubungi kami di <strong>support@edutech.id</strong>.</p>
                    </section>
                </div>
            </div>
        </div>

        <!-- ==============================
             20. FALLBACK PENGEMBANGAN
        =============================== -->
        <div v-else class="flex-grow bg-slate-50 py-24 px-4 animate-fade flex flex-col items-center justify-center">
             <div class="text-center max-w-lg">
                 <div class="w-20 h-20 bg-white rounded-[2rem] mx-auto flex items-center justify-center text-4xl text-slate-300 mb-6 border border-slate-200 shadow-sm"><i class="ph-fill ph-hammer"></i></div>
                 <h1 class="text-4xl font-black mb-4 text-slate-900 capitalize">{{ currentPage.replace('-', ' ') }}</h1>
                 <p class="text-slate-500 text-base mb-8 leading-relaxed">Halaman ini masih dalam tahap pengembangan. Silakan kembali lagi nanti.</p>
                 <button @click="navigate('landing')" class="px-8 py-3.5 bg-slate-900 text-white rounded-xl font-bold text-sm transition shadow-lg hover:-translate-y-1">Kembali ke Beranda</button>
             </div>
        </div>

        <!-- Video Demo Modal -->
        <transition name="fade">
            <div v-if="isDemoModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center px-4">
                <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm" @click="isDemoModalOpen = false"></div>
                <div class="relative bg-black rounded-2xl w-full max-w-4xl aspect-video shadow-2xl overflow-hidden z-10 border border-slate-700">
                    <button @click="isDemoModalOpen = false" class="absolute top-4 right-4 z-20 w-10 h-10 bg-white/10 text-white rounded-full flex items-center justify-center hover:bg-white/20 transition"><i class="ph-bold ph-x"></i></button>
                    <video autoplay controls class="w-full h-full object-cover"><source src="https://www.w3schools.com/html/mov_bbb.mp4" type="video/mp4"></video>
                </div>
            </div>
        </transition>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 pt-16 pb-8 mt-auto">
        <div class="max-w-7xl mx-auto px-4 grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-8 lg:gap-8 mb-12">
            <div class="md:col-span-2 lg:pr-8">
                <div class="flex items-center mb-5">
                    <div class="w-8 h-8 rounded-lg bg-slate-900 flex items-center justify-center text-white mr-2.5">
                        <i class="ph-bold ph-code text-base"></i>
                    </div>
                    <span class="font-black text-xl text-slate-900">EduTech.</span>
                </div>
                <p class="text-sm text-slate-500 leading-relaxed mb-6 font-medium">Mencetak talenta digital Indonesia berstandar industri melalui kurikulum ekosistem Vue & Laravel yang modern.</p>
                
                <!-- Contact Info Added -->
                <div class="mb-6 space-y-3">
                    <div class="flex items-start">
                        <i class="ph-fill ph-map-pin text-primary-500 mt-0.5 mr-2 text-lg shrink-0"></i>
                        <p class="text-sm text-slate-600 font-medium leading-relaxed">Jl. Amir Mahmud No.51,<br>Gunung Anyar, Surabaya</p>
                    </div>
                    <div class="flex items-center">
                        <i class="ph-fill ph-whatsapp-logo text-primary-500 mr-2 text-lg shrink-0"></i>
                        <p class="text-sm text-slate-600 font-medium">+62 856-5484-31749</p>
                    </div>
                </div>

                <div class="flex space-x-2.5 text-slate-400">
                    <a href="#" class="w-9 h-9 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center hover:bg-primary-50 hover:text-primary-600 hover:border-primary-200 transition-all"><i class="ph-fill ph-instagram-logo text-base"></i></a>
                    <a href="#" class="w-9 h-9 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center hover:bg-primary-50 hover:text-primary-600 hover:border-primary-200 transition-all"><i class="ph-fill ph-youtube-logo text-base"></i></a>
                    <a href="#" class="w-9 h-9 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center hover:bg-primary-50 hover:text-primary-600 hover:border-primary-200 transition-all"><i class="ph-fill ph-linkedin-logo text-base"></i></a>
                </div>
            </div>
            <div>
                <h4 class="text-slate-900 font-bold mb-4 text-xs uppercase tracking-wider">Program</h4>
                <ul class="space-y-2.5 text-sm font-medium text-slate-500">
                    <li><a href="#" @click.prevent="navigate('bootcamp')" class="hover:text-primary-600 transition">Bootcamp Premium</a></li>
                    <li><a href="#" @click.prevent="navigate('category')" class="hover:text-primary-600 transition">Katalog Kelas</a></li>
                    <li><a href="#" @click.prevent="navigate('roadmap')" class="hover:text-primary-600 transition">Roadmap Karir</a></li>
                    <li><a href="#" @click.prevent="navigate('webinar')" class="hover:text-primary-600 transition">Webinar & Event</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-slate-900 font-bold mb-4 text-xs uppercase tracking-wider">Perusahaan</h4>
                <ul class="space-y-2.5 text-sm font-medium text-slate-500">
                    <li><a href="#" @click.prevent="navigate('about')" class="hover:text-primary-600 transition">Tentang Kami</a></li>
                    <li><a href="#" @click.prevent="navigate('mentors')" class="hover:text-primary-600 transition">Instruktur</a></li>
                    <li><a href="#" @click.prevent="navigate('career')" class="hover:text-primary-600 transition">Karir <span class="text-[9px] bg-primary-50 text-primary-600 px-1.5 py-0.5 rounded ml-1">HIRING</span></a></li>
                    <li><a href="#" @click.prevent="navigate('blog')" class="hover:text-primary-600 transition">Blog & Artikel</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-slate-900 font-bold mb-4 text-xs uppercase tracking-wider">Bantuan & Legal</h4>
                <ul class="space-y-2.5 text-sm font-medium text-slate-500">
                    <li><a href="#" @click.prevent="navigate('faq')" class="hover:text-primary-600 transition">FAQ Bantuan</a></li>
                    <li><a href="#" @click.prevent="navigate('cek-sertifikat')" class="hover:text-primary-600 transition">Verifikasi Sertifikat</a></li>
                    <li><a href="#" @click.prevent="navigate('terms')" class="hover:text-primary-600 transition">Syarat & Ketentuan</a></li>
                    <li><a href="#" @click.prevent="navigate('privacy')" class="hover:text-primary-600 transition">Privasi Data</a></li>
                </ul>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center pt-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
            <p>&copy; 2026 EduTech LMS. Hak Cipta Dilindungi.</p>
            <div class="mt-3 md:mt-0 flex space-x-4">
                <span class="flex items-center"><i class="ph-bold ph-check mr-1 text-emerald-500"></i> Nuxt 4</span>
                <span class="flex items-center"><i class="ph-bold ph-check mr-1 text-emerald-500"></i> Laravel 13</span>
            </div>
        </div>
    </footer>

</div>
</template>

<style>
[v-cloak] { display: none; }
        .parallax-wrapper { transition: transform 0.2s cubic-bezier(0.2, 0.8, 0.2, 1); will-change: transform; }
        @keyframes slot-roll { 0% { transform: translateY(0); } 100% { transform: translateY(calc(-100% + 1em)); } }
        .slot-wrap { display: inline-block; height: 1em; line-height: 1em; overflow: hidden; vertical-align: bottom; }
        .slot-col { display: inline-flex; flex-direction: column; will-change: transform; }
        .slot-col span { height: 1em; line-height: 1em; }
        .slot-roll { animation: slot-roll cubic-bezier(0.22, 1, 0.36, 1) forwards; }
        .glass { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.5); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .animation-delay-2000 { animation-delay: 2s; }
        .line-clamp-1 { display: -webkit-box; line-clamp: 1; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-2 { display: -webkit-box; line-clamp: 2; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-3 { display: -webkit-box; line-clamp: 3; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>
