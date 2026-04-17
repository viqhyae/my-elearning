import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'

export const useGeminiFrontendState = () => {
            const runtimeConfig = useRuntimeConfig()
            const apiBase = runtimeConfig.public.apiBase

            const currentPage = ref('landing')
            const isLoggedIn = ref(false)
            const isMobileMenuOpen = ref(false)
            const isDemoModalOpen = ref(false)
            
            const activeCourse = ref(null)
            const activeJob = ref(null)
            const activePost = ref(null)
            const activeWebinar = ref(null)
            
            const activeCategoryFilter = ref('Semua Kategori')
            const activeLevelFilter = ref('Semua Level')
            const searchQuery = ref('')
            
            const impactStatsVisible = ref(false)
            const landingStatsVisible = ref(false)

            const mouseX = ref(0); const mouseY = ref(0)
            const onMouseMove = (e) => { 
                if(window.innerWidth < 768) return; 
                mouseX.value = (e.clientX / window.innerWidth - 0.5) * 2
                mouseY.value = (e.clientY / window.innerHeight - 0.5) * 2 
            }
            const resetMouse = () => { mouseX.value = 0; mouseY.value = 0; }

            const timelineProgress = ref(0)
            const calculateTimelineProgress = () => {
                if (currentPage.value !== 'roadmap') return;
                const timeline = document.getElementById('roadmap-timeline');
                if (timeline) {
                    const rect = timeline.getBoundingClientRect();
                    const drawPoint = window.innerHeight * 0.55; 
                    let progress = (drawPoint - rect.top) / rect.height;
                    progress = Math.max(0, Math.min(1, progress));
                    timelineProgress.value = progress * 100;
                }
            };

            const handleScroll = () => {
                calculateTimelineProgress();
                
                if (currentPage.value === 'testimonials' && !impactStatsVisible.value) {
                    const el = document.getElementById('impact-stats-section');
                    if (el && el.getBoundingClientRect().top < window.innerHeight * 0.85) {
                        impactStatsVisible.value = true;
                    }
                }
                if (currentPage.value === 'landing' && !landingStatsVisible.value) {
                    landingStatsVisible.value = true;
                }
            };

            onMounted(async () => {
                window.addEventListener('scroll', handleScroll);
                handleScroll(); 
                await loadFrontendPayload();
            });
            
            onUnmounted(() => {
                window.removeEventListener('scroll', handleScroll);
            });

            const categoryAliasMap: Record<string, string> = {
                'Backend & API': 'Backend & Database',
                'DevOps & Server': 'DevOps & Cloud',
            }

            const navigate = (page, data = null) => {
                currentPage.value = page
                isMobileMenuOpen.value = false
                window.scrollTo(0, 0)
                
                if (page === 'category') {
                    if (typeof data === 'string' && data.trim().length > 0) {
                        const normalizedCategory = categoryAliasMap[data] || data
                        activeCategoryFilter.value = normalizedCategory
                    } else {
                        activeCategoryFilter.value = 'Semua Kategori'
                    }
                }
                if (page === 'course-detail' && data) {
                    activeCourse.value = data;
                    courseSyllabus.value.forEach((mod, idx) => mod.open = (idx === 0));
                }
                if (page === 'job-detail' && data) activeJob.value = data
                if (page === 'blog-detail' && data) activePost.value = data
                if (page === 'webinar-detail' && data) activeWebinar.value = data
                
                if (page === 'testimonials') impactStatsVisible.value = false;
                if (page === 'landing') landingStatsVisible.value = false;

                nextTick(() => handleScroll());
            }

            const categories = ref(['Web Development', 'Backend & Database', 'Mobile App', 'DevOps & Cloud', 'UI/UX Design', 'Data Science'])
            const categoriesPreview = ref([
                { name: 'Web Development', icon: 'ph-duotone ph-browser', count: 24, color: 'text-blue-500', hoverColor: 'group-hover:text-blue-400', hoverAnim: 'group-hover:animate-bounce-gentle group-hover:drop-shadow-[0_0_15px_rgba(59,130,246,0.6)]' },
                { name: 'Backend & API', icon: 'ph-duotone ph-database', count: 18, color: 'text-emerald-500', hoverColor: 'group-hover:text-emerald-400', hoverAnim: 'group-hover:animate-pulse-fast group-hover:drop-shadow-[0_0_15px_rgba(16,185,129,0.6)]' },
                { name: 'DevOps & Server', icon: 'ph-duotone ph-cloud-arrow-up', count: 12, color: 'text-purple-500', hoverColor: 'group-hover:text-purple-400', hoverAnim: 'group-hover:animate-fly-up group-hover:drop-shadow-[0_0_15px_rgba(168,85,247,0.6)]' },
                { name: 'UI/UX Design', icon: 'ph-duotone ph-paint-brush-broad', count: 15, color: 'text-pink-500', hoverColor: 'group-hover:text-pink-400', hoverAnim: 'group-hover:animate-swing group-hover:drop-shadow-[0_0_15px_rgba(236,72,153,0.6)]' }
            ])
            
            const brands = ref([
                { name: 'Google', img: 'https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg' },
                { name: 'Netflix', img: 'https://upload.wikimedia.org/wikipedia/commons/0/08/Netflix_2015_logo.svg' },
                { name: 'Spotify', img: 'https://upload.wikimedia.org/wikipedia/commons/2/26/Spotify_logo_with_text.svg' },
                { name: 'Tokopedia', img: 'https://upload.wikimedia.org/wikipedia/commons/a/a7/Tokopedia.svg' },
                { name: 'Stripe', img: 'https://upload.wikimedia.org/wikipedia/commons/b/ba/Stripe_Logo%2C_revised_2016.svg' },
                { name: 'PayPal', img: 'https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg' }
            ])

            const stats = ref([
                { label: 'Siswa Belajar', duration: '4.0s', values: ['0', '1', '3', '5', '6', '7', '8', '9', '10'], suffix: 'K+' },
                { label: 'Total Kelas', duration: '4.5s', values: ['0', '20', '45', '60', '80', '100', '120', '135', '150'], suffix: '+' },
                { label: 'Mentor Aktif', duration: '4.2s', values: ['0', '5', '15', '25', '35', '40', '45', '48', '50'], suffix: '+' },
                { label: 'Rating Global', duration: '4.8s', values: ['0.0', '1.5', '2.8', '3.5', '4.0', '4.5', '4.7', '4.8', '4.9'], suffix: '/5' }
            ])

            const mentorsList = ref([
                { name: 'Ahmad Fauzi', role: 'Frontend Engineer', company: 'Tokopedia', image: 'https://i.pravatar.cc/150?img=11' },
                { name: 'Budi Santoso', role: 'Backend Lead', company: 'Gojek', image: 'https://i.pravatar.cc/150?img=8' },
                { name: 'Sarah Wijaya', role: 'UI/UX Designer', company: 'Traveloka', image: 'https://i.pravatar.cc/150?img=5' },
                { name: 'Fajar Nugraha', role: 'Cloud Architect', company: 'Netflix', image: 'https://i.pravatar.cc/150?img=15' },
                { name: 'Rizky Maulana', role: 'Data Scientist', company: 'Telkomsel', image: 'https://i.pravatar.cc/150?img=33' },
                { name: 'Siska Nirmala', role: 'Mobile Dev (Flutter)', company: 'Grab', image: 'https://i.pravatar.cc/150?img=41' },
                { name: 'David Gunawan', role: 'Cyber Security', company: 'Bank Mandiri', image: 'https://i.pravatar.cc/150?img=53' },
                { name: 'Lisa Permata', role: 'Product Manager', company: 'Ruangguru', image: 'https://i.pravatar.cc/150?img=20' },
            ])

            const faqs = ref([
                { q: 'Apakah pemula bisa mengikuti kelas ini?', a: 'Sangat bisa! Kurikulum kami disusun berjenjang mulai dari Fundamental (HTML, CSS, JS) hingga materi Advanced (Nuxt & Laravel).', open: false },
                { q: 'Berapa lama batas waktu akses materi?', a: 'Anda mendapatkan akses materi Seumur Hidup (Lifetime) cukup dengan satu kali pembayaran.', open: false },
                { q: 'Apakah ada forum tanya jawab jika saya error?', a: 'Ya, setiap siswa mendapatkan akses ke grup komunitas Discord khusus untuk bertanya langsung ke instruktur.', open: false },
                { q: 'Apakah kelas ini mengajarkan deploy ke VPS?', a: 'Tentu. Di kelas DevOps & Backend kami mengajarkan setup VPS, Docker Compose, dan konfigurasi Nginx.', open: false }
            ])

            const impactStats = ref([
                { prefix: '', values: ['0', '15', '30', '45', '60', '75', '85', '92', '96'], suffix: '%', suffixClass: 'ml-0.5', duration: '3.5s', title: 'Tingkat Penyerapan Kerja', desc: 'Alumni mendapatkan pekerjaan <br>dalam waktu 3 bulan setelah lulus.' },
                { prefix: 'Rp', values: ['0.0', '1.5', '3.0', '4.5', '6.0', '7.0', '7.8', '8.2', '8.5'], suffix: 'JT+', suffixClass: 'text-2xl ml-0.5 mb-0.5', duration: '4.0s', title: 'Rata-rata Gaji Pertama', desc: 'Kompensasi entry-level untuk posisi<br>Software Engineer alumni EduTech.' },
                { prefix: '', values: ['0', '300', '700', '1.200', '1.800', '2.300', '2.600', '2.800', '3.000'], suffix: '+', suffixClass: 'ml-0.5', duration: '4.5s', title: 'Sertifikat Diterbitkan', desc: 'Telah diakui kredibilitasnya oleh<br>berbagai perusahaan teknologi.' }
            ])

            const courseSyllabus = ref([
                { title: 'Fundamental & Instalasi Persiapan', duration: '4 Video - 1 Kuis', open: true, items: ['Pengenalan Konsep Dasar', 'Instalasi Environment', 'Struktur Direktori'] },
                { title: 'Konsep Inti & Arsitektur', duration: '6 Video - 2 Tugas', open: false, items: ['Routing & Navigation', 'State Management', 'Lifecycle Hooks'] },
                { title: 'Integrasi Database & API', duration: '5 Video - 1 Kuis', open: false, items: ['Fetch Data dengan Axios/Fetch', 'CRUD Operations', 'Error Handling'] },
                { title: 'Otentikasi & Keamanan', duration: '4 Video - 1 Tugas', open: false, items: ['Login & Register Auth', 'Middleware / Route Guards', 'JWT Token Management'] },
                { title: 'Deployment & Final Project', duration: '3 Video - 1 Project', open: false, items: ['Build Production', 'Setup VPS / Hosting', 'Presentasi Project'] },
            ])

            const webinars = ref([
                { title: 'Masterclass: Migrasi Vue 2 ke Vue 3 Composition API', date: '24 Mei 2026 - 19:00 WIB', speaker: 'Ahmad Fauzi', speakerInitials: 'AF', speakerRole: 'Senior Frontend Engineer', type: 'Gratis', price: 'Gratis', img: 'https://images.unsplash.com/photo-1552664730-d307ca884978?q=80&w=800', description: 'Pelajari cara efektif dan best practice untuk melakukan migrasi dari Vue 2 (Options API) ke Vue 3 (Composition API). Sesi ini akan membahas perubahan fundamental, penggunaan script setup, dan reaktivitas di Vue 3.', benefits: ['Pemahaman dasar Composition API', 'Strategi refactoring komponen lama', 'Live Q&A dengan instruktur', 'E-Certificate kehadiran'] },
                { title: 'Optimasi Database PostgreSQL skala Enterprise', date: '28 Mei 2026 - 20:00 WIB', speaker: 'Budi Santoso', speakerInitials: 'BS', speakerRole: 'Backend Lead Engineer', type: 'Premium', price: 'Rp 99.000', img: 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?q=80&w=800', description: 'Tingkatkan performa aplikasi Anda dengan menguasai teknik optimasi query, indexing, dan konfigurasi tuning pada PostgreSQL untuk menangani trafik skala besar (Enterprise level).', benefits: ['Teknik Advanced Indexing', 'Menganalisa Query Plan', 'Studi Kasus Performa Database', 'Rekaman Sesi & Modul Materi'] },
                { title: 'Kupas Tuntas Docker & Nginx untuk Pemula', date: '05 Juni 2026 - 19:30 WIB', speaker: 'Fajar Nugraha', speakerInitials: 'FN', speakerRole: 'Cloud Architect', type: 'Gratis', price: 'Gratis', img: 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800', description: 'Webinar pengenalan mengenai kontainerisasi menggunakan Docker dan cara setup Nginx sebagai Reverse Proxy. Sangat cocok untuk pemula yang ingin memahami dasar-dasar DevOps dan deployment.', benefits: ['Konsep Container vs VM', 'Menulis Dockerfile Dasar', 'Setup Nginx Reverse Proxy', 'E-Certificate kehadiran'] }
            ])

            const jobs = ref([
                { title: 'Senior Frontend Engineer (Vue/Nuxt)', type: 'Full-time', location: 'Remote / Surabaya', description: 'Kami mencari Senior Frontend Engineer yang berpengalaman dengan ekosistem Vue.js (khususnya Nuxt 4) untuk memimpin pengembangan platform LMS kami.', requirements: ['Minimal 3 tahun pengalaman menggunakan Vue.js/Nuxt.js', 'Mahir menggunakan Tailwind CSS', 'Memahami konsep SSR/SSG dengan baik', 'Pengalaman menggunakan Pinia untuk State Management'] },
                { title: 'Backend Developer (Laravel/Golang)', type: 'Full-time', location: 'Surabaya, ID', description: 'Anda akan bertanggung jawab untuk membangun dan memelihara REST API yang scalable menggunakan Laravel 13 dan melakukan migrasi beberapa service ke Golang.', requirements: ['Minimal 2 tahun pengalaman menggunakan Laravel', 'Pengalaman dasar dengan Golang adalah nilai plus', 'Fasih dengan PostgreSQL dan Redis', 'Memahami konsep Microservices'] },
                { title: 'Technical Content Creator', type: 'Part-time', location: 'Remote', description: 'Bergabung dengan tim edukasi kami untuk membuat artikel tutorial, video pendek, dan materi belajar terkait pengembangan web terkini.', requirements: ['Pernah membuat konten teknis (Blog/YouTube/Tiktok)', 'Memiliki basic pemrograman web', 'Mampu menjelaskan konsep rumit menjadi sederhana'] },
                { title: 'UI/UX Designer', type: 'Full-time', location: 'Remote / Surabaya', description: 'Rancang antarmuka pengguna yang estetik dan fungsional. Anda akan bekerja erat dengan tim frontend untuk mengimplementasikan Design System.', requirements: ['Mahir menggunakan Figma', 'Memiliki portfolio UI/UX yang kuat', 'Paham dengan konsep Design System'] }
            ])

            const blogPosts = ref([
                { title: 'Cara Setup Tailwind CSS di Nuxt 4 dalam 5 Menit', category: 'Tutorial', readTime: '4 Min', excerpt: 'Panduan praktis dan cepat untuk mengintegrasikan Tailwind CSS versi terbaru di project Nuxt 4 Anda.', img: 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?q=80&w=800' },
                { title: 'Pentingnya Redis Caching di Laravel 13', category: 'Backend', readTime: '7 Min', excerpt: 'Pelajari bagaimana Redis dapat mempercepat response API Laravel Anda hingga 10x lipat untuk skala besar.', img: 'https://images.unsplash.com/photo-1623479322729-28b25c16b011?q=80&w=800' },
                { title: 'Roadmap Menjadi DevOps Engineer 2026', category: 'Karir', readTime: '6 Min', excerpt: 'Tools dan teknologi yang wajib Anda kuasai jika ingin berkarir sebagai DevOps Engineer tahun ini.', img: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=800' }
            ])

            const allCourses = ref([
                { id: 1, title: 'Fullstack Web: Nuxt 4 & Laravel 13 API Enterprise', category: 'Web Development', level: 'Menengah', instructor: 'Ahmad Fauzi', instructorInitials: 'AF', price: 'Rp 499.000', rating: '4.9', students: '1.2k', image: 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?w=800&q=80' },
                { id: 2, title: 'Mastering PostgreSQL 17 & Redis 7 Caching', category: 'Backend & Database', level: 'Lanjutan', instructor: 'Budi Santosa', instructorInitials: 'BS', price: 'Rp 399.000', rating: '4.8', students: '800', image: 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=800&q=80' },
                { id: 3, title: 'Docker Compose & Nginx Reverse Proxy Setup', category: 'DevOps & Cloud', level: 'Menengah', instructor: 'Fajar Nugraha', instructorInitials: 'FN', price: 'Rp 549.000', rating: '5.0', students: '500', image: 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=800&q=80' },
                { id: 4, title: 'Dasar Pemrograman Web: HTML, CSS & JS', category: 'Web Development', level: 'Pemula', instructor: 'Rina Melati', instructorInitials: 'RM', price: 'Gratis', rating: '4.7', students: '2.1k', image: 'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=800&q=80' },
                { id: 5, title: 'Laravel Sanctum Auth & Rest API Security', category: 'Backend & Database', level: 'Menengah', instructor: 'Reza Rahadian', instructorInitials: 'RR', price: 'Rp 450.000', rating: '4.8', students: '900', image: 'https://images.unsplash.com/photo-1555099962-4199c345e5dd?w=800&q=80' },
                { id: 6, title: 'UI/UX Design System untuk Aplikasi SaaS', category: 'UI/UX Design', level: 'Pemula', instructor: 'Sarah Wijaya', instructorInitials: 'SW', price: 'Rp 450.000', rating: '4.9', students: '1.5k', image: 'https://images.unsplash.com/photo-1586717791821-3f44a563fa4c?w=800&q=80' },
                { id: 7, title: 'Belajar Fundamental Python Data Science', category: 'Data Science', level: 'Pemula', instructor: 'Andi Kusuma', instructorInitials: 'AK', price: 'Rp 350.000', rating: '4.7', students: '1.1k', image: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&q=80' },
                { id: 8, title: 'Mastering React Native 2026', category: 'Mobile App', level: 'Menengah', instructor: 'Dian Sastro', instructorInitials: 'DS', price: 'Rp 599.000', rating: '4.9', students: '850', image: 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=800&q=80' },
                { id: 9, title: 'Golang Microservices & gRPC Architecture', category: 'Backend & Database', level: 'Lanjutan', instructor: 'Budi Santosa', instructorInitials: 'BS', price: 'Rp 650.000', rating: '5.0', students: '420', image: 'https://images.unsplash.com/photo-1623479322729-28b25c16b011?w=800&q=80' },
                { id: 10, title: 'Advanced Vue 3, Pinia & Composition API', category: 'Web Development', level: 'Menengah', instructor: 'Rina Melati', instructorInitials: 'RM', price: 'Rp 400.000', rating: '4.8', students: '1.5k', image: 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&q=80' },
                { id: 11, title: 'Kubernetes (K8s) & CI/CD Pipelines Mastery', category: 'DevOps & Cloud', level: 'Lanjutan', instructor: 'Fajar Nugraha', instructorInitials: 'FN', price: 'Rp 750.000', rating: '4.9', students: '350', image: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=800&q=80' },
                { id: 12, title: 'Figma to Code: UI/UX Advanced Prototyping', category: 'UI/UX Design', level: 'Menengah', instructor: 'Sarah Wijaya', instructorInitials: 'SW', price: 'Rp 390.000', rating: '4.8', students: '1.8k', image: 'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=800&q=80' },
                { id: 13, title: 'Flutter State Management: Bloc & Riverpod', category: 'Mobile App', level: 'Lanjutan', instructor: 'Dian Sastro', instructorInitials: 'DS', price: 'Rp 500.000', rating: '4.7', students: '600', image: 'https://images.unsplash.com/photo-1526498460520-4c246339dccb?w=800&q=80' },
                { id: 14, title: 'Machine Learning with Python & Scikit-Learn', category: 'Data Science', level: 'Menengah', instructor: 'Andi Kusuma', instructorInitials: 'AK', price: 'Rp 550.000', rating: '4.9', students: '950', image: 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&q=80' },
                { id: 15, title: 'Modern JavaScript Masterclass (ES6+)', category: 'Web Development', level: 'Pemula', instructor: 'Ahmad Fauzi', instructorInitials: 'AF', price: 'Rp 299.000', rating: '4.8', students: '3.5k', image: 'https://images.unsplash.com/photo-1579468118864-1b9ea3c0db4a?w=800&q=80' },
                { id: 16, title: 'Dasar Logika PHP & OOP untuk Pemula', category: 'Backend & Database', level: 'Pemula', instructor: 'Reza Rahadian', instructorInitials: 'RR', price: 'Rp 250.000', rating: '4.7', students: '2.8k', image: 'https://images.unsplash.com/photo-1555099962-4199c345e5dd?w=800&q=80' }
            ])

            const loadFrontendPayload = async () => {
                try {
                    const payload = await $fetch<{
                        categories?: string[]
                        categoriesPreview?: Array<{
                            name: string
                            icon: string
                            count: number
                            color: string
                            hoverColor: string
                            hoverAnim: string
                        }>
                        stats?: Array<{
                            label: string
                            duration: string
                            values: string[]
                            suffix: string
                        }>
                        allCourses?: Array<{
                            id: number
                            title: string
                            category: string
                            level: string
                            instructor: string
                            instructorInitials: string
                            price: string
                            rating: string
                            students: string
                            image: string
                        }>
                    }>('/api/gemini/frontend', {
                        baseURL: apiBase,
                    })

                    if (Array.isArray(payload.categories) && payload.categories.length > 0) {
                        categories.value = Array.from(new Set([...categories.value, ...payload.categories]))
                    }

                    if (Array.isArray(payload.categoriesPreview) && payload.categoriesPreview.length > 0) {
                        const previewMap = new Map(
                            categoriesPreview.value.map((item) => [String(item.name), item])
                        )

                        payload.categoriesPreview.forEach((item) => {
                            previewMap.set(String(item.name), item)
                        })

                        categoriesPreview.value = Array.from(previewMap.values())
                    }

                    if (Array.isArray(payload.stats) && payload.stats.length > 0) {
                        const statsMap = new Map(payload.stats.map((item) => [String(item.label), item]))

                        stats.value = stats.value.map((item) =>
                            statsMap.has(String(item.label))
                                ? { ...item, ...statsMap.get(String(item.label)) }
                                : item
                        )
                    }

                    if (Array.isArray(payload.allCourses) && payload.allCourses.length > 0) {
                        const localById = new Map(
                            allCourses.value.map((course) => [Number(course.id), course])
                        )

                        const mergedLocal = allCourses.value.map((course) => {
                            const remote = payload.allCourses?.find(
                                (item) => Number(item.id) === Number(course.id)
                            )
                            return remote ? { ...course, ...remote } : course
                        })

                        const extraRemote = payload.allCourses.filter(
                            (course) => !localById.has(Number(course.id))
                        )

                        allCourses.value = [...mergedLocal, ...extraRemote]
                    }
                } catch (error) {
                    console.warn('Gemini frontend API fallback ke data lokal.', error)
                }
            }

            const testimonials = ref([
                { name: 'Rina Melati', role: 'Frontend Engineer di Tokopedia', avatar: 'https://i.pravatar.cc/150?img=47', quote: 'Materi Nuxt 4 di sini paling update. Saya yang sebelumnya bingung dengan konsep SSR jadi sangat paham dan langsung implementasi di kantor.', colorClass: 'bg-blue-50/50 hover:bg-blue-50/80 border-blue-100' },
                { name: 'Andi Kusuma', role: 'Backend Engineer', avatar: 'https://i.pravatar.cc/150?img=15', quote: 'Pembahasan Laravel 13 dan API Security sangat mendalam. Tidak hanya teori, tapi *best practice* yang dipakai industri.', colorClass: 'bg-white hover:bg-slate-50 border-slate-100' },
                { name: 'Fajar Nugraha', role: 'DevOps Specialist', avatar: 'https://i.pravatar.cc/150?img=11', quote: 'EduTech berhasil mengubah cara saya setup Docker & Nginx. Dulu sering error, sekarang deploy aplikasi semulus jalan tol.', colorClass: 'bg-purple-50/50 hover:bg-purple-50/80 border-purple-100' },
                { name: 'Sarah Wijaya', role: 'Mahasiswa IT', avatar: 'https://i.pravatar.cc/150?img=5', quote: 'Sangat cocok untuk pemula. Instrukturnya menjelaskan dengan bahasa manusia, bukan bahasa alien. Sangat terbantu!', colorClass: 'bg-white hover:bg-slate-50 border-slate-100' },
                { name: 'Reza Aditya', role: 'Freelance Developer', avatar: 'https://i.pravatar.cc/150?img=12', quote: 'Berkat portofolio hasil kelas dari EduTech, saya dipercaya klien luar negeri dengan *rate* dollar yang memuaskan.', colorClass: 'bg-emerald-50/50 hover:bg-emerald-50/80 border-emerald-100' },
                { name: 'Diana Fitri', role: 'UI/UX Designer', avatar: 'https://i.pravatar.cc/150?img=1', quote: 'Platform ini luar biasa. Saya belajar pembuatan design system dan langsung bisa diterapkan secara konsisten di proyek UI kantor saya.', colorClass: 'bg-pink-50/50 hover:bg-pink-50/80 border-pink-100' },
                { name: 'Kevin Pratama', role: 'Data Analyst', avatar: 'https://i.pravatar.cc/150?img=13', quote: 'Roadmap data science-nya sangat terstruktur. Tidak membingungkan untuk pemula yang baru belajar Python dan analisa data mentah.', colorClass: 'bg-yellow-50/50 hover:bg-yellow-50/80 border-yellow-100' },
                { name: 'Citra Kirana', role: 'Mobile Developer', avatar: 'https://i.pravatar.cc/150?img=9', quote: 'Materi Flutter State Management-nya yang terbaik sejauh ini. Riverpod dijelaskan sampai ke akar-akarnya, gak cuma disuruh ketik doang.', colorClass: 'bg-indigo-50/50 hover:bg-indigo-50/80 border-indigo-100' },
                { name: 'Dimas Anggara', role: 'Fullstack Developer', avatar: 'https://i.pravatar.cc/150?img=61', quote: 'Awalnya ragu beli kelas premium, tapi worth it banget! Grup diskusinya sangat aktif dan mentor fast response menjawab bug.', colorClass: 'bg-white hover:bg-slate-50 border-slate-100' },
                { name: 'Wulan Guritno', role: 'Software QA', avatar: 'https://i.pravatar.cc/150?img=22', quote: 'Penyampaian video 4K dan suaranya bening banget. Sangat nyaman ditonton berjam-jam tanpa bikin mata lelah.', colorClass: 'bg-orange-50/50 hover:bg-orange-50/80 border-orange-100' },
                { name: 'Hendra Saputra', role: 'Game Developer', avatar: 'https://i.pravatar.cc/150?img=33', quote: 'Integrasi dengan payment gateway dan third party API diajarkan sangat jelas. Ilmu yang mahal harganya!', colorClass: 'bg-sky-50/50 hover:bg-sky-50/80 border-sky-100' },
                { name: 'Maya Indah', role: 'Content Creator', avatar: 'https://i.pravatar.cc/150?img=42', quote: 'Platform LMS-nya sendiri sangat responsif dan UI-nya memanjakan mata. Belajar jadi semangat terus tiap hari.', colorClass: 'bg-white hover:bg-slate-50 border-slate-100' }
            ])
            
            const testimonialsRow1 = computed(() => testimonials.value.slice(0, 6))
            const testimonialsRow2 = computed(() => testimonials.value.slice(6, 12))

            const activeRoadmap = ref('frontend')
            const roadmaps = ref([
                { id: 'frontend', title: 'Frontend Developer', icon: 'ph-bold ph-browser', description: 'Kuasai pembuatan antarmuka web interaktif yang cepat dan SEO-friendly menggunakan Vue 3 & Nuxt 4.', steps: [
                    { title: 'Web Fundamental', desc: 'Pemahaman mendalam tentang struktur HTML5, styling CSS3, dan logika JavaScript Vanilla.', courseTitle: 'Dasar Pemrograman Web: HTML, CSS & JS', courseCategory: 'Web Development' },
                    { title: 'Modern JavaScript', desc: 'Eksplorasi sintaks modern ES6+, Async/Await, dan manipulasi DOM lanjutan.', courseTitle: 'Modern JavaScript Masterclass (ES6+)', courseCategory: 'Web Development' },
                    { title: 'Vue & Nuxt Mastery', desc: 'Membangun aplikasi SSR/SSG skala industri menggunakan framework Vue 3 dan Nuxt 4.', courseTitle: 'Fullstack Web: Nuxt 4 & Laravel 13 API Enterprise', courseCategory: 'Web Development' }
                ]},
                { id: 'backend', title: 'Backend Developer', icon: 'ph-bold ph-database', description: 'Rancang arsitektur REST API yang tangguh, aman, dan scalable menggunakan ekosistem Laravel.', steps: [
                    { title: 'PHP & OOP Fundamental', desc: 'Pahami dasar bahasa PHP, Object Oriented Programming (OOP), dan struktur data.', courseTitle: 'Dasar Logika PHP & OOP untuk Pemula', courseCategory: 'Backend & Database' },
                    { title: 'Database Design & Cache', desc: 'Merancang relasi database menggunakan PostgreSQL dan optimasi query dengan Redis.', courseTitle: 'Mastering PostgreSQL 17 & Redis 7 Caching', courseCategory: 'Backend & Database' },
                    { title: 'Laravel API & Microservices', desc: 'Membangun RESTful API dengan otentikasi Sanctum dan beralih ke arsitektur Golang.', courseTitle: 'Golang Microservices & gRPC Architecture', courseCategory: 'Backend & Database' }
                ]},
                { id: 'devops', title: 'DevOps Engineer', icon: 'ph-bold ph-cloud-arrow-up', description: 'Otomatisasi deployment dan manajemen infrastruktur server modern menggunakan Docker & K8s.', steps: [
                    { title: 'Linux & Docker Containerization', desc: 'Membungkus aplikasi backend/frontend ke dalam container Docker yang terisolasi.', courseTitle: 'Docker Compose & Nginx Reverse Proxy Setup', courseCategory: 'DevOps & Cloud' },
                    { title: 'Kubernetes Orchestration', desc: 'Manajemen kontainer skala besar dengan K8s dan CI/CD Pipelines.', courseTitle: 'Kubernetes (K8s) & CI/CD Pipelines Mastery', courseCategory: 'DevOps & Cloud' }
                ]},
                { id: 'mobile', title: 'Mobile Developer', icon: 'ph-bold ph-device-mobile', description: 'Buat aplikasi mobile cross-platform Android & iOS dengan performa native.', steps: [
                    { title: 'React Native Basics', desc: 'Membangun UI aplikasi mobile menggunakan komponen React Native.', courseTitle: 'Mastering React Native 2026', courseCategory: 'Mobile App' },
                    { title: 'Flutter State Management', desc: 'Mengelola state kompleks aplikasi menggunakan arsitektur Bloc & Riverpod di Flutter.', courseTitle: 'Flutter State Management: Bloc & Riverpod', courseCategory: 'Mobile App' }
                ]},
                { id: 'uiux', title: 'UI/UX Designer', icon: 'ph-bold ph-paint-brush-broad', description: 'Rancang antarmuka pengguna yang estetik, fungsional, dan memiliki user experience tinggi.', steps: [
                    { title: 'Design System & Prototyping', desc: 'Membangun komponen UI yang konsisten dan interaktif untuk aplikasi SaaS.', courseTitle: 'UI/UX Design System untuk Aplikasi SaaS', courseCategory: 'UI/UX Design' },
                    { title: 'Figma Advanced', desc: 'Menerjemahkan desain figma menjadi prototype yang siap di-coding (handoff) ke frontend.', courseTitle: 'Figma to Code: UI/UX Advanced Prototyping', courseCategory: 'UI/UX Design' }
                ]},
                { id: 'data', title: 'Data Scientist', icon: 'ph-bold ph-chart-line-up', description: 'Olah data mentah menjadi wawasan bisnis menggunakan Python dan algoritma Machine Learning.', steps: [
                    { title: 'Python Fundamentals', desc: 'Dasar-dasar sintaks Python dan pemrosesan data (Pandas/Numpy).', courseTitle: 'Belajar Fundamental Python Data Science', courseCategory: 'Data Science' },
                    { title: 'Machine Learning', desc: 'Menerapkan model prediktif dan klasifikasi menggunakan Scikit-Learn.', courseTitle: 'Machine Learning with Python & Scikit-Learn', courseCategory: 'Data Science' }
                ]}
            ])
            const currentRoadmapData = computed(() => roadmaps.value.find(r => r.id === activeRoadmap.value))

            const getStepColorClass = (index) => {
                const colors = [
                    { bg: 'bg-gradient-to-br from-blue-50/50 to-white border-blue-200', text: 'text-blue-600', dot: 'border-blue-500 bg-white', dotActive: 'bg-blue-50 border-blue-500', btn: 'text-slate-400 group-hover:bg-blue-50 group-hover:text-blue-600' },
                    { bg: 'bg-gradient-to-br from-purple-50/50 to-white border-purple-200', text: 'text-purple-600', dot: 'border-purple-500 bg-white', dotActive: 'bg-purple-50 border-purple-500', btn: 'text-slate-400 group-hover:bg-purple-50 group-hover:text-purple-600' },
                    { bg: 'bg-gradient-to-br from-emerald-50/50 to-white border-emerald-200', text: 'text-emerald-600', dot: 'border-emerald-500 bg-white', dotActive: 'bg-emerald-50 border-emerald-500', btn: 'text-slate-400 group-hover:bg-emerald-50 group-hover:text-emerald-600' },
                    { bg: 'bg-gradient-to-br from-orange-50/50 to-white border-orange-200', text: 'text-orange-600', dot: 'border-orange-500 bg-white', dotActive: 'bg-orange-50 border-orange-500', btn: 'text-slate-400 group-hover:bg-orange-50 group-hover:text-orange-600' }
                ];
                return colors[index % colors.length];
            };

            const getCourseByTitle = (title) => {
                return allCourses.value.find(c => c.title === title) || allCourses.value[0];
            };

            const filteredCourses = computed(() => {
                return allCourses.value.filter(c => {
                    const matchCategory = activeCategoryFilter.value === 'Semua Kategori' || c.category === activeCategoryFilter.value;
                    const matchLevel = activeLevelFilter.value === 'Semua Level' || c.level === activeLevelFilter.value;
                    const matchSearch = c.title.toLowerCase().includes(searchQuery.value.toLowerCase());
                    return matchCategory && matchLevel && matchSearch;
                });
            })

            const steps = ref([
                { title: 'Tentukan Alur', desc: 'Pilih role dari Roadmap.', icon: 'ph-fill ph-map-pin-line', color: 'text-primary-500' },
                { title: 'Pelajari Teori', desc: 'Tonton modul premium 4K.', icon: 'ph-fill ph-play-circle', color: 'text-purple-500' },
                { title: 'Ngoding Praktik', desc: 'Buat project nyata industri.', icon: 'ph-fill ph-terminal-window', color: 'text-emerald-500' },
                { title: 'Lamar Kerja', desc: 'Gunakan sertifikat & portofolio.', icon: 'ph-fill ph-rocket', color: 'text-yellow-500' }
            ])

            const featuredAlumni = ref([
                {
                    name: 'Arief Rahman',
                    transition: 'Non-IT &rarr; Frontend Developer',
                    company: 'Tokopedia',
                    quote: 'Saya lulusan akuntansi yang banting setir ke IT. Berkat roadmap Frontend dari EduTech, saya bisa membangun portofolio yang memikat HRD dalam waktu 4 bulan saja.',
                    image: 'https://i.pravatar.cc/150?img=60'
                },
                {
                    name: 'Nadia Salsabila',
                    transition: 'Fresh Graduate &rarr; UI/UX Designer',
                    company: 'Traveloka',
                    quote: 'Materi Figma to Code sangat membuka wawasan. Saya tidak hanya belajar mendesain, tapi juga mengerti bagaimana desain saya diimplementasikan oleh tim developer.',
                    image: 'https://i.pravatar.cc/150?img=43'
                },
                {
                    name: 'Bima Mahendra',
                    transition: 'Gojek Driver &rarr; Backend Dev',
                    company: 'Gojek',
                    quote: 'Sempat pesimis karena tidak punya latar belakang IT sama sekali. Tapi instruktur EduTech sabar banget bimbing dari nol sampai saya tembus kerja di Gojek.',
                    image: 'https://i.pravatar.cc/150?img=12'
                },
                {
                    name: 'Lestari Putri',
                    transition: 'Guru Honorer &rarr; Data Analyst',
                    company: 'Bank Mandiri',
                    quote: 'Modul Python dan Data Science di EduTech sangat aplikatif. Sekarang saya membantu menganalisa ribuan data transaksi per hari.',
                    image: 'https://i.pravatar.cc/150?img=35'
                }
            ]);

            const logout = () => { isLoggedIn.value = false; navigate('landing'); }

            return {
                currentPage, isLoggedIn, isMobileMenuOpen, isDemoModalOpen, categories, categoriesPreview, navigate, 
                onMouseMove, resetMouse, mouseX, mouseY, stats, steps, brands, allCourses, filteredCourses, 
                activeCategoryFilter, activeLevelFilter, searchQuery, logout, activeRoadmap, roadmaps, currentRoadmapData,
                timelineProgress, testimonials, testimonialsRow1, testimonialsRow2, mentorsList, faqs, impactStats,
                impactStatsVisible, landingStatsVisible, webinars, jobs, blogPosts, getStepColorClass, getCourseByTitle, activeCourse, activeJob, activePost, activeWebinar, courseSyllabus, featuredAlumni
            }
}
